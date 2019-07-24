<?php

namespace Kinikit\Persistence\Database\Vendors\MSSQL;

use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\Connection\ColumnType;
use Kinikit\Persistence\Database\Connection\DatabaseConnectionException;
use Kinikit\Persistence\Database\Connection\PreparedStatement;
use Kinikit\Persistence\Database\ResultSet\ResultSet;
use Kinikit\Persistence\Database\Exception\SQLException;


/**
 * SQL Server Database Connection Implementation
 *
 * @author matthew
 *
 */
class MSSQLDatabaseConnection extends BaseDatabaseConnection {

    private $driver;
    private $connection;

    const DRIVER_MICROSOFT = "MS";
    const DRIVER_SYBASE = "SYBASE";

    /**
     * Construct new MSSQL Connection and determine the driver in use.
     *
     * @param string $serverName
     * @param string $username
     * @param string $password
     *
     * @throws DatabaseConnectionException
     */
    public function __construct($configKey = null) {

        if (function_exists("sqlsrv_connect")) {
            $this->driver = MSSQLDatabaseConnection::DRIVER_MICROSOFT;
        } else {
            $this->driver = MSSQLDatabaseConnection::DRIVER_SYBASE;
        }

        parent::__construct($configKey);
    }


    /**
     * Connect to the database.  This receives an array of normalised stripped config parameters
     * so e.g. "db.name" or "db.test.name" would be mapped to simply "name" for convenience of handling.
     *
     * @param array $configParams
     *
     * @return boolean
     * @throws DatabaseConnectionException
     */
    public function connect($configParams = []) {

        // Handle SYBASE Connections
        if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE) {

            $this->connection = mssql_connect($configParams["serverName"], $configParams["username"], $configParams["password"], true);

            // If no connection, return false from here.
            if (!$this->connection) {
                return false;
            }

            // Select a db if required.
            if (isset($configParams["database"])) {
                mssql_select_db($configParams["database"], $this->connection);
            }

        } // Handle MS Connections
        else {

            $this->connection =
                sqlsrv_connect($configParams["serverName"], array("UID" => $configParams["username"], "PWD" => $configParams["password"],
                    "Database" => $configParams["database"], 'MultipleActiveResultSets' => false));

            if ($this->connection === false) {
                return false;
            }

        }


        // Set ANSI NULLS On.
        $this->query("SET ANSI_NULLS ON;",);
        $this->query("SET ANSI_WARNINGS ON;",);
    }


    /**
     * Escape a string ready for query
     *
     * @param string $string
     */
    public function escapeString($data) {

        if (is_numeric($data)) return $data;

        if (!isset ($data) or empty ($data)) return '';

        $non_displayables = array('/%0[0-8bcef]/', // url encoded 00-08, 11, 12, 14, 15
            '/%1[0-9a-f]/', // url encoded 16-31
            '/[\x00-\x08]/', // 00-08
            '/\x0b/', // 11
            '/\x0c/', // 12
            '/[\x0e-\x1f]/'); // 14-31


        foreach ($non_displayables as $regex) $data = preg_replace($regex, '', $data);
        $data = str_replace("'", "''", $data);
        return $data;

    }


    /**
     * Escape a column ready for use in a query.
     *
     * @param $columnName
     * @return mixed|string
     */
    public function escapeColumn($columnName) {
        return "[" . $columnName . "]";
    }

    /**
     * Query function
     *
     * @param string $sql
     * @param array $placeholders
     * @return mixed
     * @throws SQLException
     */
    public function query($sql, ...$placeholders) {

        /**
         * Time the SQL query.
         */
        $result = $this->executeCallableWithLogging(function () use ($sql) {

            if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE)
                $result = mssql_query($sql, $this->connection);
            else
                $result = sqlsrv_query($this->connection, $sql);


            return $result;
        }, $sql);


        if (!$result) {

            if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE)
                $errorString = mssql_get_last_message();

            else {
                $errors = sqlsrv_errors();
                $errorString = "";
                foreach ($errors as $error) {
                    $errorString .= "\n" . $error["message"];
                }
            }

            $this->setLastErrorMessage($errorString);
            throw new SQLException ($errorString);
        } else {
            return $result;
        }

    }

    /**
     * Query with results, return an MS SQL Result Set.
     *
     * @param string $sql
     * @param array $placeholders
     * @return ResultSet
     * @throws SQLException
     */
    public function queryWithResults($sql, ...$placeholders) {

        $results = $this->query($sql,);
        if ($results) {
            return new MSSQLResultSet ($results, $this->driver);
        } else {
            return null;
        }

    }

    /**
     * Execute a prepared statement in MSSQL
     *
     * @param PreparedStatement $sql
     * @return bool
     * @throws SQLException
     */
    public function createPreparedStatement($sql) {

        $sql = $sql->getSQL();

        $errorString = null;

        $success = $this->executeCallableWithLogging(function () use ($sql, $sql, $errorString) {

            $bindParameters = $sql->getBindParameters();

            $sqlArray = explode("?", $sql);

            $numberOfPerams = sizeof($sqlArray) - 1;

            $statement = $sqlArray [0];
            for ($i = 0; $i < $numberOfPerams; $i++) {

                $currentBindValue = $bindParameters [$i]->getValue();

                switch ($bindParameters [$i]->getSqlType()) {
                    case ColumnType::SQL_INT :
                    case ColumnType::SQL_SMALLINT :
                    case ColumnType::SQL_TINYINT :
                    case ColumnType::SQL_BIGINT :
                    case ColumnType::SQL_DECIMAL :
                    case ColumnType::SQL_FLOAT :
                    case ColumnType::SQL_DOUBLE :
                    case ColumnType::SQL_REAL :
                        $escapedValue = $this->escapeString($currentBindValue);
                        $statement =
                            $statement . ($currentBindValue === null ? "Null" : ($escapedValue === '' ? "Null" : $escapedValue)) . $sqlArray [$i + 1];
                        break;

                    case ColumnType::SQL_BLOB :
                        if ($currentBindValue) {
                            $arrData = unpack("H*hex", $currentBindValue);
                            $hexdata = "0x" . $arrData ['hex'];
                        } else {
                            $hexdata = "Null";
                        }
                        $statement = $statement . $hexdata . $sqlArray [$i + 1];

                        break;
                    default :
                        $statement =
                            $statement . ($currentBindValue == null ? "Null" : ("'" . utf8_decode($this->escapeString($currentBindValue)) . "'")) . $sqlArray [$i + 1];

                }

            }

            return $this->query($statement,);

        }, $sql);

        if (!$success) {

            if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE) {
                $this->setLastErrorMessage(mssql_get_last_message());
            } else {
                $errors = sqlsrv_errors();
                $errorString = "";
                foreach ($errors as $error) {
                    $errorString .= "\n" . $error["message"];
                }
                $this->setLastErrorMessage($errorString);
            }


            throw new SQLException ($this->getLastErrorMessage());
        }

        return true;

    }


    /**
     * Get the last Auto Increment Id in a SQL server specific manner.
     *
     * @throws SQLException
     */
    public function getLastAutoIncrementId() {

        $result = $this->query("select SCOPE_IDENTITY()",);

        if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE) {
            $id = mssql_result($result, 0, 0);
            mssql_free_result($result);
        } else {
            $id = array_shift(sqlsrv_fetch_array($result, SQLSRV_FETCH_NUMERIC));
            sqlsrv_free_stmt($result);
        }

        return $id;

    }

    /**
     *
     */
    public function close() {

        if ($this->connection) {
            if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE)
                mssql_close($this->connection);
            else
                sqlsrv_close($this->connection);
        }
    }

    /**
     * Begin a transaction, or try to start a save point if already in a transaction
     *
     *
     * @param integer $beginBehaviour
     * @throws SQLException
     */
    public function beginTransaction() {

        // Increase the transaction depth
        $this->transactionDepth++;

        // If not in transaction, start a transaction
        if ($this->transactionDepth == 1) {
            $this->query("BEGIN TRANSACTION",);
        } else {
            $this->query("SAVE TRANSACTION SAVEPOINT" . $this->transactionDepth,);
        }

    }

    /**
     * Rollback the current transaction either to the savepoint identified by the first parameter
     * (which should match a value returned from beginTransaction) or if null supplied (default) the
     * whole current transaction will be rolled back.  This function has no effect if not currently in a transaction
     *
     * @param $wholeTransaction
     * @throws  SQLException
     */
    public function rollback($wholeTransaction = true) {


        if ($this->transactionDepth <= 1 || $wholeTransaction) {
            $this->query("ROLLBACK TRANSACTION",);
        } else {
            $this->query("ROLLBACK TRANSACTION SAVEPOINT" . $this->transactionDepth,);
        }


        // Decrement the transaction depth
        $this->transactionDepth = $wholeTransaction ? 0 : max(0, $this->transactionDepth - 1);
    }


}

?>
