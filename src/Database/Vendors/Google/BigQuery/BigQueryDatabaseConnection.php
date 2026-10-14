<?php

namespace Kinikit\Persistence\Database\Vendors\Google\BigQuery;

use Google\Cloud\BigQuery\BigQueryClient;
use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\BulkData\BulkDataManager;
use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\Exception\BigQuerySQLException;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

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
    public function connect(array $configParams = []): bool {
        $this->bigQueryClient = new BigQueryClient();
        return true;
    }

    /**
     * @param string $sql
     * @param array ...$placeholders
     * @return mixed
     * @throws BigQuerySQLException
     */
    public function execute($sql, ...$placeholders) {
        $parameters = BindParameter::createAll($placeholders);
        return $this->doQuery($sql, $parameters, false);
    }


    /**
     * Execute a query and return a result set
     *
     * @param string $sql
     * @param BindParameter[] $parameters
     * @return ResultSet
     */
    public function doQuery(string $sql, array $parameters, $returnResults = true, $timeoutMs = 10000): ResultSet {

        // Do query with placeholder params
        $queryJobConfig = $this->bigQueryClient->query($sql, [
            "configuration" => [
                "query" => [
                    "allowLargeResults" => true
                ],
                "jobTimeoutMs" => $timeoutMs
            ]
        ]);

        $parameterValues = array_map(fn ($parameter) => $parameter->getValue(), $parameters);
        $queryJobConfig->parameters($parameterValues);

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
            return new BigQueryResultSet($queryJob->queryResults());
        } else {
            return true;
        }
    }


    /**
     * @param string $sql
     * @return BigQueryPreparedStatement
     */
    public function doCreatePreparedStatement(string $sql): PreparedStatement {
        return new BigQueryPreparedStatement($sql, $this->bigQueryClient);
    }

    /**
     * @param string $tableName
     * @return TableColumn[]
     */
    public function getTableColumnMetaData(string $tableName): array {
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
    public function getTableIndexMetaData(string $tableName): array {
        return [];
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
        // Handle multipart names like project.dataset.table
        return implode(".", array_map(function ($part) {
            return "`" . str_replace("`", "``", $part) . "`";
        }, explode(".", $columnName)));
    }

    /**
     * @return null
     */
    public function getLastAutoIncrementId(): int {
    }

    /**
     * @return BigQueryBulkDataManager
     */
    public function getBulkDataManager(): BulkDataManager {
        return new BigQueryBulkDataManager($this);
    }

    /**
     * @return BigQueryDDLManager
     */
    public function getDDLManager(): DDLManager {
        return new BigQueryDDLManager();
    }

    /**
     * @return void
     */
    public function close(): void {
        $this->bigQueryClient = null;
    }
}