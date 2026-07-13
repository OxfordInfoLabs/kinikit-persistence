<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Google\Cloud\Spanner\Database;
use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\Exception\SpannerSQLException;
use Kinikit\Persistence\Database\PreparedStatement\BasePreparedStatement;
use Kinikit\Persistence\Database\PreparedStatement\WrongNumberOfPreparedStatementParametersException;

class SpannerPreparedStatement extends BasePreparedStatement {

    private Database $connection;

    /**
     * @var integer $boundValues
     */
    private $boundValues;

    /**
     * @param string $sql
     * @param Database $connection
     */
    public function __construct(?string $sql, $connection) {
        $this->boundValues = substr_count($sql, "?");

        $index = 1;
        $spannerSql = preg_replace_callback('/\?/', function () use (&$index) {
            return '@p' . $index++;
        }, $sql);

        parent::__construct($spannerSql);

        $this->connection = $connection;
    }

    /**
     * @param array $parameterValues
     * @return SpannerResultSet|bool
     * @throws SpannerSQLException
     * @throws WrongNumberOfPreparedStatementParametersException
     */
    public function execute($parameterValues) {

        if (count($parameterValues) !== $this->boundValues) {
            throw new WrongNumberOfPreparedStatementParametersException($this->boundValues, count($parameterValues));
        }

        $spannerParams = [];
        $index = 1;
        foreach ($parameterValues as $value) {
            if (is_numeric($value)) {
                if (floor($value) == $value) {
                    $value = (int)$value;
                } else {
                    $value = (float)$value;
                }
            }
            $spannerParams['p' . $index++] = $value;
        }

        Logger::log($parameterValues);
        Logger::log($this->getStatementSQL());

        try {
            $options = ['parameters' => $spannerParams];

            // Determine if the SQL statement is a standard SELECT or a mutation (DML)
            // to execute it with the correct transactional paradigm if needed.
            if (stripos(trim($this->getStatementSQL()), 'SELECT') === 0) {
                $results = $this->connection->execute($this->getStatementSQL(), $options);
                return new SpannerResultSet($results);
            } else {
                $this->connection->runTransaction(function ($transaction) use ($options) {
                    $transaction->executeUpdate($this->getStatementSQL(), $options);
                    $transaction->commit();
                });
                return true;
            }
        } catch (\Exception $e) {
            throw new SpannerSQLException($e->getMessage(), $e->getCode());
        }
    }

    public function close() {
        $this->statement = null;
    }
}