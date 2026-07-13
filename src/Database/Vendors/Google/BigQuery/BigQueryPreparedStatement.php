<?php

namespace Kinikit\Persistence\Database\Vendors\Google\BigQuery;

use Google\Cloud\BigQuery\BigQueryClient;
use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\PreparedStatement\BasePreparedStatement;
use Kinikit\Persistence\Database\PreparedStatement\WrongNumberOfPreparedStatementParametersException;
use GSE\Exception\BigQuerySQLException;

class BigQueryPreparedStatement extends BasePreparedStatement {

    private BigQueryClient $connection;

    /**
     * @var integer $boundValues
     */
    private $boundValues;

    /**
     * @param string $sql
     * @param BigQueryClient $connection
     */
    public function __construct(?string $sql, $connection) {
        parent::__construct($sql);

        $this->connection = $connection;
        $this->boundValues = substr_count($sql, "?");
    }

    /**
     * @param array $parameterValues
     * @return void
     * @throws BigQuerySQLException
     * @throws WrongNumberOfPreparedStatementParametersException
     */
    public function execute($parameterValues) {

        // Map parameters - BQ is very fussy about types
        $parameterValues = array_map(function($value) {
            if (is_numeric($value)) {
                if (floor($value) == $value) {
                    $value = (int)$value;
                } else {
                    $value = (float)$value;
                }
            }
            return $value;
        }, $parameterValues);

        // If mismatch of parameter values, throw.
        if (sizeof($parameterValues) != $this->boundValues) {
            throw new WrongNumberOfPreparedStatementParametersException($this->boundValues, sizeof($parameterValues));
        }

        Logger::log($parameterValues);
        Logger::log($this->getStatementSQL());

        try {
            $queryJobConfig = $this->connection->query($this->getStatementSQL());
            $queryJobConfig->parameters($parameterValues);
            $this->connection->runQuery($queryJobConfig);
        } catch (\Exception $e) {
            throw new BigQuerySQLException($e->getMessage(), $e->getCode());
        }
    }

    public function close() {
        $this->statement = null;
    }
}