<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\SpannerClient;
use Google\Cloud\Spanner\V1\TypeCode;
use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\BulkData\BulkDataManager;
use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\Exception\SpannerSQLException;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableIndexColumn;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

class SpannerDatabaseConnection extends BaseDatabaseConnection {

    // Match the generic VARCHAR default so STRING(MAX) compares as unchanged in schema updates.
    public const DEFAULT_STRING_LENGTH = 255;

    /**
     * @var Database
     */
    private $spannerConnection;

    /**
     * Connect with config parameters
     *
     * @param array $configParams
     * @return bool
     */
    public function connect(array $configParams = []): bool {

        $instanceId = $configParams["instanceId"];
        $databaseId = $configParams["databaseId"];

        $client = new SpannerClient();
        $this->spannerConnection = $client->connect($instanceId, $databaseId);
        return true;

    }

    /**
     * @param string $sql
     * @param array ...$placeholders
     * @return mixed
     * @throws SpannerSQLException
     */
    public function execute(string $sql, ...$placeholders) {
        $parameters = BindParameter::createAll($placeholders);
        return $this->doQuery($sql, $parameters, false);
    }


    /**
     * Execute a query and return a result set
     *
     * @param string $sql
     * @param BindParameter[] $parameters
     * @param bool $returnResults
     * @param int $timeoutMs
     * @return mixed
     */
    public function doQuery(string $sql, array $parameters, bool $returnResults = true, int $timeoutMs = 10000): ResultSet {

        try {
            $index = 1;
            $spannerSql = preg_replace_callback('/\?/', function () use (&$index) {
                return '@p' . $index++;
            }, $sql);

            $options = [];
            if (!empty($parameters)) {
                $spannerParams = [];

                // Check if the array is associative (named parameters) or indexed (positional)
                // If it's indexed, we need to convert '?' in SQL to '@p1, @p2...'
                $isNamed = count(array_filter(array_keys($parameters), 'is_string')) > 0;

                if (!$isNamed) {
                    $index = 1;
                    $spannerSql = preg_replace_callback('/\?/', function () use (&$index) {
                        return '@p' . $index++;
                    }, $sql);
                }

                $index = 1;
                foreach ($parameters as $key => $value) {
                    $value = SpannerPreparedStatement::bindParameter($value);

                    if ($value === "TRUE") $value = true;
                    if ($value === "FALSE") $value = false;

                    $paramName = $isNamed ? $key : 'p' . $index++;
                    $spannerParams[$paramName] = $value;
                }

                $options['parameters'] = $spannerParams;
            }

            Logger::log($options);
            Logger::log($spannerSql);

            if ($returnResults) {
                $results = $this->spannerConnection->execute($spannerSql, $options);
                return new SpannerResultSet($results);
            } else {
                $this->spannerConnection->runTransaction(function ($transaction) use ($spannerSql, $options) {
                    $transaction->executeUpdate($spannerSql, $options);
                    $transaction->commit();
                });
                return true;
            }

        } catch (\Exception $e) {
            Logger::log($e->getTraceAsString());
            throw new SpannerSQLException($e->getMessage(), $e->getCode());
        }
    }


    /**
     * @param string $sql
     * @return SpannerPreparedStatement
     */
    public function doCreatePreparedStatement(string $sql): PreparedStatement {
        return new SpannerPreparedStatement($sql, $this->spannerConnection);
    }

    /**
     * @param string $tableName
     * @return TableColumn[]
     */
    public function getTableColumnMetaData(string $tableName): array {

        $sql = "SELECT COLUMN_NAME, SPANNER_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = @tableName";

        $resultSet = $this->query($sql, ['tableName' => $tableName]);
        $results = $resultSet->fetchAll();

        $pkSQL = "SELECT COLUMN_NAME 
                  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                  WHERE TABLE_NAME = @tableName 
                    AND CONSTRAINT_NAME LIKE 'PK_%'";

        $pkResults = $this->query($pkSQL, ['tableName' => $tableName])->fetchAll();

        $pks = array_map(fn($pk) => $pk['COLUMN_NAME'], $pkResults);


        $columns = [];
        foreach ($results as $row) {
            $explodedType = explode("(", rtrim($row['SPANNER_TYPE'], ")"));
            $spannerType = TypeCode::value(strtoupper($explodedType[0]));
            $tableColumnType = SpannerResultSet::SPANNER_MAPPINGS[$spannerType] ?? ResultSetColumn::SQL_VARCHAR;

            if (sizeof($explodedType) > 1) {
                $explodedArgs = explode(",", $explodedType[1]);
                $length = strtoupper(trim($explodedArgs[0])) === "MAX"
                    ? self::DEFAULT_STRING_LENGTH
                    : intval(trim($explodedArgs[0]));
            } else {
                $length = null;
            }

            $columns[$row['COLUMN_NAME']] = new TableColumn(
                $row['COLUMN_NAME'],
                $tableColumnType,
                $length,
                null,
                $row['COLUMN_DEFAULT'] ?? null,
                in_array($row['COLUMN_NAME'], $pks),
                false,
                $row['IS_NULLABLE'] === 'NO'
            );
        }

        Logger::log($columns);
        return $columns;
    }

    /**
     * @param string $tableName
     * @return TableIndex[]
     */
    public function getTableIndexMetaData(string $tableName): array {
        $sql = "SELECT INDEX_NAME, COLUMN_NAME
                FROM INFORMATION_SCHEMA.INDEX_COLUMNS
                WHERE TABLE_NAME = @tableName AND INDEX_TYPE = 'INDEX'";

        $indexes = [];
        $columns = [];
        $currentIndex = null;

        try {
            $resultSet = $this->query($sql, ["tableName" => $tableName]);

            while ($row = $resultSet->nextRow()) {
                $indexName = $row["INDEX_NAME"];

                if ($indexName != $currentIndex) {
                    if ($currentIndex) {
                        $indexes[] = new TableIndex($currentIndex, $columns);
                    }
                    $currentIndex = $indexName;
                    $columns = [];
                }

                $columns[$indexName] = new TableIndexColumn($row["COLUMN_NAME"]);
            }

            // Last one
            if ($currentIndex) {
                $indexes[] = new TableIndex($currentIndex, $columns);
            }

            return $indexes;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @param string $string
     * @return string
     */
    public function escapeString(string $string): string {
        return str_replace("'", "\\'", $string);
    }

    /**
     * @param string $columnName
     * @return string
     */
    public function escapeColumn(string $columnName): string {
        return implode(".", array_map(function ($part) {
            return "`" . str_replace("`", "``", $part) . "`";
        }, explode(".", $columnName)));
    }

    /**
     * @return int
     */
    public function getLastAutoIncrementId(): int {
    }

    /**
     * @return SpannerBulkDataManager
     */
    public function getBulkDataManager(): BulkDataManager {
        return new SpannerBulkDataManager($this);
    }

    /**
     * @return SpannerDDLManager
     */
    public function getDDLManager(): DDLManager {
        return new SpannerDDLManager();
    }

    /**
     * @return void
     */
    public function close(): void {
        $this->spannerConnection = null;
    }

    public function getSpannerConnection(): Database {
        return $this->spannerConnection;
    }
}