<?php

namespace Kinikit\Persistence\Database\Vendors\Google\BigQuery;

use Google\Auth\Cache\MemoryCacheItemPool;
use Google\Cloud\BigQuery\BigQueryClient;
use GSE\Exception\BigQuerySQLException;
use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableColumn;

class BigQueryDatabaseConnection extends BaseDatabaseConnection {

    /**
     * @var BigQueryClient
     */
    private $bigQueryClient;

    /**
     * Connect with config parameters
     *
     * @param array $configParams
     * @return bool
     */
    public function connect($configParams = []) {

        $bqHost = Configuration::readParameter("bq.host");

        if (!$bqHost || $bqHost == "prod") {
            $clientConfig = [
                "suppressKeyFileNotice" => true
            ];
            $this->bigQueryClient = new BigQueryClient($clientConfig);
            return true;
        }

        $projectId = Configuration::readParameter("dev.project.id");
        $clientConfig = [
            "projectId" => "$projectId",
            "apiEndpoint" => "http://{$bqHost}:9050/",
            "allowPreemptiveTokens" => true,
            "authCache" => new MemoryCacheItemPool(),
            "restOptions" => ["debug" => false]
        ];

        $this->bigQueryClient = new BigQueryClient($clientConfig);
        return true;
    }

    /**
     * @param string $sql
     * @param array ...$placeholders
     * @return mixed
     * @throws BigQuerySQLException
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

        // Do query with placeholder params
        $queryJobConfig = $this->bigQueryClient->query($sql, [
            "configuration" => [
                "query" => [
                    "allowLargeResults" => true
                ],
                "jobTimeoutMs" => $timeoutMs
            ]
        ]);

        $queryJobConfig->parameters($placeholderValues);

        try {
            $queryJob = $this->bigQueryClient->startQuery($queryJobConfig);
        } catch (\Exception $e) {
            Logger::log($e->getTraceAsString());
            throw new BigQuerySQLException($e->getMessage(), $e->getCode());
        }

        while (!$queryJob->isComplete()) {
            usleep(500000); // Sleep 0.5s
            $queryJob->reload();
        }

        Logger::log($queryJob->info()["statistics"]);

        if ($returnResults) {
            return new SpannerResultSet($queryJob->queryResults());
        } else {
            return true;
        }
    }


    /**
     * @param string $sql
     * @return SpannerPreparedStatement
     */
    public function doCreatePreparedStatement($sql) {
        return new SpannerPreparedStatement($sql, $this->bigQueryClient);
    }

    /**
     * @param string $tableName
     * @return TableColumn[]
     */
    public function getTableColumnMetaData($tableName) {
        $parts = explode(".", $tableName);
        $dataset = $parts[0];
        $table = $parts[1];

        $sql = "SELECT column_name, data_type, is_nullable, column_default FROM `$dataset`.INFORMATION_SCHEMA.COLUMNS WHERE table_name = ?";

        $resultSet = $this->query($sql, $table);

        $results = $resultSet->fetchAll();

        $columns = [];
        foreach ($results as $row) {
            // Clean mapping without the heavy string parsing
            $columns[$row['column_name']] = new TableColumn(
                $row['column_name'],
                $row['data_type'],
                null,
                null,
                $row['column_default'],
                false,
                false,
                $row['is_nullable'] === 'NO'
            );
        }

        return $columns;
    }

    /**
     * Indexes aren't supported by BQ
     *
     * @param string $tableName
     * @return array
     */
    public function getTableIndexMetaData($tableName) {
        return [];
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
        // Handle multipart names like project.dataset.table
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
        $this->bigQueryClient = null;
    }
}