<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\SpannerClient;
use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\Exception\SpannerSQLException;
use Kinikit\Persistence\Database\MetaData\TableColumn;

class SpannerDatabaseConnection extends BaseDatabaseConnection {

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
    public function connect($configParams = []) {

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
    public function execute($sql, ...$placeholders) {
        return $this->doQuery($sql, $placeholders, false);
    }


    /**
     * Execute a query and return a result set
     *
     * @param $sql
     * @param $placeholderValues
     * @return mixed
     */
    public function doQuery($sql, $placeholderValues, $returnResults = true, $timeoutMs = 10000) {

        try {
            $index = 1;
            $spannerSql = preg_replace_callback('/\?/', function () use (&$index) {
                return '@p' . $index++;
            }, $sql);

            $options = [];
            if (!empty($placeholderValues)) {
                $spannerParams = [];

                // Check if the array is associative (named parameters) or indexed (positional)
                // If it's indexed, we need to convert '?' in SQL to '@p1, @p2...'
                $isNamed = count(array_filter(array_keys($placeholderValues), 'is_string')) > 0;

                if (!$isNamed) {
                    $index = 1;
                    $spannerSql = preg_replace_callback('/\?/', function () use (&$index) {
                        return '@p' . $index++;
                    }, $sql);
                }

                $index = 1;
                foreach ($placeholderValues as $key => $value) {
                    if (is_numeric($value)) {
                        if (floor($value) == $value) {
                            $value = (int)$value;
                        } else {
                            $value = (float)$value;
                        }
                    }

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
    public function doCreatePreparedStatement($sql) {
        return new SpannerPreparedStatement($sql, $this->spannerConnection);
    }

    /**
     * @param string $tableName
     * @return TableColumn[]
     */
    public function getTableColumnMetaData($tableName) {

        $sql = "SELECT COLUMN_NAME, SPANNER_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = @tableName";

        $resultSet = $this->query($sql, ['tableName' => $tableName]);
        Logger::log($resultSet);
        $results = $resultSet->fetchAll();

        $pkSQL = "SELECT COLUMN_NAME 
                  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                  WHERE TABLE_NAME = @tableName 
                    AND CONSTRAINT_NAME LIKE 'PK_%'";

        $pkResults = $this->query($pkSQL, ['tableName' => $tableName]);
        Logger::log($pkResults);
        $pks = array_map(fn ($pk) => $pk['COLUMN_NAME'], $pkResults->fetchAll());


        $columns = [];
        foreach ($results as $row) {
            $columns[$row['COLUMN_NAME']] = new TableColumn(
                $row['COLUMN_NAME'],
                $row['SPANNER_TYPE'],
                null,
                null,
                $row['COLUMN_DEFAULT'] ?? null,
                in_array($row['COLUMN_NAME'], $pks),
                false,
                $row['IS_NULLABLE'] === 'NO'
            );
        }

        return $columns;
    }

    /**
     * @param string $tableName
     * @return array
     */
    public function getTableIndexMetaData($tableName) {
        $sql = "SELECT INDEX_NAME 
                FROM INFORMATION_SCHEMA.INDEXES 
                WHERE TABLE_NAME = @tableName AND INDEX_TYPE = 'INDEX'";

        try {
            $resultSet = $this->query($sql, ['tableName' => $tableName]);
            return $resultSet->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @param string $string
     * @return string
     */
    public function escapeString($string) {
        return str_replace("'", "\\'", $string);
    }

    /**
     * @param string $columnName
     * @return string
     */
    public function escapeColumn($columnName) {
        return implode(".", array_map(function ($part) {
            return "`" . str_replace("`", "``", $part) . "`";
        }, explode(".", $columnName)));
    }

    /**
     * @return null
     */
    public function getLastAutoIncrementId() {
        return null;
    }

    /**
     * @return SpannerBulkDataManager
     */
    public function getBulkDataManager() {
        return new SpannerBulkDataManager($this);
    }

    /**
     * @return SpannerDDLManager
     */
    public function getDDLManager() {
        return new SpannerDDLManager();
    }

    /**
     * @return void
     */
    public function close() {
        $this->spannerConnection = null;
    }

    public function getSpannerConnection(): Database {
        return $this->spannerConnection;
    }
}