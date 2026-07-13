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
            $options = [];
            if (!empty($placeholderValues)) {
                $options['parameters'] = $placeholderValues;
            }

            if ($returnResults) {
                $results = $this->spannerConnection->execute($sql, $options);
                return new SpannerResultSet($results);
            } else {
                $this->spannerConnection->runTransaction(function ($transaction) use ($sql, $options) {
                    $transaction->executeUpdate($sql, $options);
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
        $results = $resultSet->fetchAll();

        $columns = [];
        foreach ($results as $row) {
            $columns[$row['COLUMN_NAME']] = new TableColumn(
                $row['COLUMN_NAME'],
                $row['SPANNER_TYPE'],
                null,
                null,
                $row['COLUMN_DEFAULT'] ?? null,
                false,
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