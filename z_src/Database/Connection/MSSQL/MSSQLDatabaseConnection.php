<?php

namespace Kinikit\Persistence\Database\Connection\MSSQL;

use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Exception\DatabaseConnectionException;
use Kinikit\Persistence\Database\Exception\SQLException;


/**
 * SQL Server Database Connection Implementation
 *
 * @author matthew
 *
 */
class MSSQLDatabaseConnection extends DatabaseConnection {

    private $serverName;
    private $database;
    private $username;
    private $password;
    private $lastError;
    private $queryLogFile;
    private $tableMetaDatas = array();
    private $driver;
    private $queryParser;

    const DRIVER_MICROSOFT = "MS";
    const DRIVER_SYBASE = "SYBASE";

    /**
     * Construct new MSSQL Connection with bits and bobs we need to connect.
     *
     * @param string $serverName
     * @param string $username
     * @param string $password
     */
    public function __construct($serverName = null, $username = null, $password = null, $database = null) {
        $this->serverName = $serverName;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;

        if (function_exists("sqlsrv_connect")) {
            $this->driver = MSSQLDatabaseConnection::DRIVER_MICROSOFT;
        } else {
            $this->driver = MSSQLDatabaseConnection::DRIVER_SYBASE;
        }

        $this->queryParser = new MSSQLQueryParser();

    }

    /**
     * @return the $serverName
     */
    public function getServerName() {
        return $this->serverName;
    }

    /**
     * @return the $username
     */
    public function getUsername() {
        return $this->username;
    }

    /**
     * @return the $password
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * @param $serverName the $serverName to set
     */
    public function setServerName($serverName) {
        $this->serverName = $serverName;
    }

    /**
     * @param $username the $username to set
     */
    public function setUsername($username) {
        $this->username = $username;
    }

    /**
     * @param $password the $password to set
     */
    public function setPassword($password) {
        $this->password = $password;
    }

    /**
     * @return the $database
     */
    public function getDatabase() {
        return $this->database;
    }

    /**
     * @param $database the $database to set
     */
    public function setDatabase($database) {
        $this->database = $database;
    }

    /**
     * @return the $queryLogFile
     */
    public function getQueryLogFile() {
        return $this->queryLogFile;
    }

    /**
     * @param field_type $queryLogFile
     */
    public function setQueryLogFile($queryLogFile) {
        $this->queryLogFile = $queryLogFile;
    }

    /**
     *
     * Return the native MSSQL Connection
     *
     * @return link
     */
    public function getUnderlyingConnection() {
        if (!$this->connection) {

            // Handle SYBASE Connections
            if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE) {

                $this->connection = mssql_connect($this->serverName, $this->username, $this->password, true);


                if (!$this->connection) {
                    throw new DatabaseConnectionException ("SQL Server");
                }

                // Select a db if required.
                if ($this->database) {
                    mssql_select_db($this->database, $this->connection);
                }

            } // Handle MS Connections
            else {

                $this->connection =
                    sqlsrv_connect($this->serverName, array("UID" => $this->username, "PWD" => $this->password,
                        "Database" => $this->database, 'MultipleActiveResultSets' => false));

                if ($this->connection === false) {
                    throw new DatabaseConnectionException ("SQL Server");
                }

            }


            // Set ANSI NULLS On.
            $this->query("SET ANSI_NULLS ON;",);
            $this->query("SET ANSI_WARNINGS ON;",);

        }
        return $this->connection;
    }

    /**
     * @param unknown_type $tableName
     *
     *
     *
     * CHECK FOR ALL DATATYPES!!!
     *
     */
    public function getTableMetaData($tableName, $findPrimaryKeys = false) {

        if (!isset ($this->tableMetaDatas [$tableName])) {

            try {

                $schemaName = null;
                $splitTableName = explode(".", $tableName);
                if (sizeof($splitTableName) > 1) {
                    $schemaName = $splitTableName [0];
                    $schemaTableName = $splitTableName [1];
                } else {
                    $schemaTableName = $tableName;
                }

                $tableSQL = "SELECT * FROM INFORMATION_SCHEMA.Columns WHERE TABLE_NAME = '" . $schemaTableName . "'";
                if ($schemaName != null) {
                    $tableSQL .= " AND TABLE_SCHEMA = '" . $schemaName . "'";
                }


                $results = new MSSQLResultSet ($this->query($tableSQL,), $this->driver);

                // Add each field as a table column to the array
                $tableColumns = array();
                while ($row = $results->nextRow()) {


                    $columnType = "SQL_" . strtoupper($row ["DATA_TYPE"]);

                    switch ($row ["DATA_TYPE"]) {
                        case "char" :
                        case "varchar" :
                            $columnType = TableColumn::SQL_VARCHAR;
                            break;
                        case "smallint" :
                        case "tinyint" :
                            $columnType = TableColumn::SQL_SMALLINT;
                            break;
                        case "int" :
                        case "bit" :
                            $columnType = TableColumn::SQL_INT;
                            break;
                        case "bigint" :
                            $columnType = TableColumn::SQL_BIGINT;
                            break;
                        case "float" :
                            $columnType = TableColumn::SQL_FLOAT;
                            break;
                        case "double" :
                            $columnType = TableColumn::SQL_DOUBLE;
                            break;
                        case "real" :
                        case "money" :
                        case "smallmoney" :
                            $columnType = TableColumn::SQL_REAL;
                        case "decimal" :
                            $columnType = TableColumn::SQL_DECIMAL;
                            break;
                        case "date" :
                            $columnType = TableColumn::SQL_DATE;
                            break;
                        case "time" :
                        case "smalldatetime" :
                            $columnType = TableColumn::SQL_TIME;
                            break;
                        case "timestamp" :
                            $columnType = TableColumn::SQL_TIMESTAMP;
                            break;
                        case "blob" :
                        case "image" :
                            $columnType = TableColumn::SQL_BLOB;
                            break;
                        default :
                            $columnType = TableColumn::SQL_UNKNOWN;
                    }
                    if ($row ["CHARACTER_MAXIMUM_LENGTH"]) $columnLength = $row ["CHARACTER_MAXIMUM_LENGTH"];
                    if ($row ["NUMERIC_PRECISION"]) $columnLength = $row ["NUMERIC_PRECISION"];
                    if ($row ["DATETIME_PRECISION"]) $columnLength = $row ["DATETIME_PRECISION"];

                    $primaryKey = false;
                    if ($findPrimaryKeys == true) {

                        $result =
                            new MSSQLResultSet ($this->query("SELECT [INFORMATION_SCHEMA].[CONSTRAINT_COLUMN_USAGE].TABLE_NAME, COLUMN_NAME FROM [INFORMATION_SCHEMA].[CONSTRAINT_COLUMN_USAGE] JOIN [INFORMATION_SCHEMA].[TABLE_CONSTRAINTS] ON [INFORMATION_SCHEMA].[CONSTRAINT_COLUMN_USAGE].CONSTRAINT_NAME = [INFORMATION_SCHEMA].[TABLE_CONSTRAINTS].CONSTRAINT_NAME WHERE [INFORMATION_SCHEMA].[TABLE_CONSTRAINTS].CONSTRAINT_TYPE = 'PRIMARY KEY'AND [INFORMATION_SCHEMA].[CONSTRAINT_COLUMN_USAGE].TABLE_NAME = '" . $tableName . "' AND COLUMN_NAME = '" . $row ["COLUMN_NAME"] . "'",), $this->driver);

                        if ($result->nextRow()) {
                            $primaryKey = true;
                        }
                    }

                    $tableColumns [$row ["COLUMN_NAME"]] =
                        new MSSQLTableColumn ($row ["COLUMN_NAME"], $columnType, $columnLength, $primaryKey);
                }

            } catch (SQLException $e) {
                throw new SQLException ("Could not get meta data for table: " . $this->getLastErrorMessage());
            }

            // Return the meta data
            $this->tableMetaDatas [$tableName] = new TableMetaData ($tableName, $tableColumns);

        }

        return $this->tableMetaDatas [$tableName];

    }

    /**
     * @param unknown_type $string
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

    public function escapeColumn($columnName) {
        return "[" . $columnName . "]";
    }

    /**
     * @param unknown_type $sql
     * @param array $placeholders
     * @return bool|mixed|resource
     */
    public function query($sql, ...$placeholders) {

        // Parse the query
        $sql = $this->queryParser->parse($sql);

        $this->getUnderlyingConnection();

        $startTime = microtime(true);

        if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE)
            $result = mssql_query($sql, $this->connection);
        else
            $result = sqlsrv_query($this->connection, $sql);

        $endTime = microtime(true);

        $logString = "\n\n" . $sql;

        if (!$result) {

            if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE)
                $this->lastError = mssql_get_last_message(); else {
                $errors = sqlsrv_errors();
                $this->lastError = "";
                foreach ($errors as $error) {
                    $this->lastError .= "\n" . $error["message"];
                }
            }

            if ($this->getQueryLogFile()) {
                file_put_contents($this->getQueryLogFile(), $logString, FILE_APPEND);
            }

            throw new SQLException ($this->lastError);
        } else {

            if ($this->getQueryLogFile()) {
                file_put_contents($this->getQueryLogFile(), $logString . "\nQuery Completed in " . ($endTime - $startTime) . " seconds", FILE_APPEND);
            }
        }


        return $result;

    }

    /**
     * @param unknown_type $sql
     * @param array $placeholders
     * @return MSSQLResultSet|null
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
     * @param unknown_type $sql
     * @param bool $test
     * @return bool|string
     */
    public function createPreparedStatement($sql, $test = false) {

        $sql = $sql->getSQL();
        $bindParameters = $sql->getBindParameters();

        $sqlArray = explode("?", $sql);

        $numberOfPerams = sizeof($sqlArray) - 1;

        $statement = $sqlArray [0];
        for ($i = 0; $i < $numberOfPerams; $i++) {

            /*			if (TableColumn::SQL_VARCHAR == $bindParameters[$i]-> getSqlType()){
                $statement = $statement."'".$value = $bindParameters[$i]->getValue()."'".$sqlArray[$i+1];
                }
                if( TableColumn::SQL_INT == $bindParameters[$i]-> getSqlType()){
                $statement = $statement.$value = $bindParameters[$i]->getValue().$sqlArray[$i+1];
                }
                */


            $currentBindValue = $bindParameters [$i]->getValue();

            switch ($bindParameters [$i]->getSqlType()) {
                case TableColumn::SQL_INT :
                case TableColumn::SQL_SMALLINT :
                case TableColumn::SQL_TINYINT :
                case TableColumn::SQL_BIGINT :
                case TableColumn::SQL_DECIMAL :
                case TableColumn::SQL_FLOAT :
                case TableColumn::SQL_DOUBLE :
                case TableColumn::SQL_REAL :
                    $escapedValue = $this->escapeString($currentBindValue);
                    $statement =
                        $statement . ($currentBindValue === null ? "Null" : ($escapedValue === '' ? "Null" : $escapedValue)) . $sqlArray [$i + 1];
                    break;
                /*			case TableColumn::SQL_DATE :
                         case TableCplumn::SQL_TIME :
                         case TableColumn::SQL_TIMESTAMP :
                         $typeString .= "s";
                         break;

                         */

                case TableColumn::SQL_BLOB :
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

        $success = $this->query($statement,);

        if (!$success) {
            $this->lastError = mssql_get_last_message();
            throw new SQLException ($this->lastError);
        }

        if ($test === true) return $statement;

        return true;

    }


    // Insert a blank row (overloaded)
    public function insertBlankRow($tableName) {
        $this->query("INSERT INTO " . $tableName . " DEFAULT VALUES",);
    }

    /**
     *
     */
    public function getLastErrorMessage() {
        return $this->lastError;
    }

    /**
     *
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
            if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE) mssql_close($this->connection); else
                sqlsrv_close($this->connection);
        }
    }

    /**
     * Begin a transaction, or try to start a save point if already in a transaction
     *
     *
     * @param integer $beginBehaviour
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
     * @param string $toSavepoint
     */
    public function rollback() {

        try {
            if ($this->transactionDepth <= 1) {
                $this->query("ROLLBACK TRANSACTION",);
            } else {
                $this->query("ROLLBACK TRANSACTION SAVEPOINT" . $this->transactionDepth,);
            }
        } catch (SQLException $e) {
            // Ignore exceptions here as little we can do.
        }

        // Decrement the transaction depth
        $this->transactionDepth = max(0, $this->transactionDepth - 1);
    }

}

?>
