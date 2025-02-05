<?php

namespace Kinikit\Persistence\Database\Vendors\MySQL;

use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\PreparedStatement\PDOPreparedStatement;

/**
 * Simple extension of the PDO Prepared statement to automate retry logic in the case of
 * expected errors.
 */
class MySQLPreparedStatement extends PDOPreparedStatement {

    // Exception retries
    private $exceptionRetries;

    public function __construct($sql, $pdo, $exceptionRetries = 5) {
        parent::__construct($sql, $pdo);
        $this->exceptionRetries = $exceptionRetries;
    }


    /**
     * Override Execute method to
     *
     * @param $parameterValues
     * @return void
     * @throws \Kinikit\Core\Exception\DebugException
     * @throws \Kinikit\Persistence\Database\Exception\SQLException
     * @throws \Kinikit\Persistence\Database\PreparedStatement\WrongNumberOfPreparedStatementParametersException
     */
    public function execute($parameterValues) {
        $retries = 1;
        while ($retries > 0) {
            try {
                return parent::execute($parameterValues);
            } catch (SQLException $e) {
                if (!MySQLDatabaseConnection::isRetryException($e) || $retries > $this->exceptionRetries) {
                    throw $e;
                }
                Logger::log("MySQL Statement Retry $retries: " . $e->getMessage(), 6);
                $retries++;
            }
        }
    }


}