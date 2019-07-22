<?php

namespace Kinikit\Persistence\Database\Connection\MySQL;

use Kinikit\Persistence\Database\Connection\BlobWrapper;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Connection\PreparedStatement;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Connection\TableMetaData;
use Kinikit\Persistence\Database\Exception\DatabaseConnectionException;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\Exception\WrongNumberOfPreparedStatementParametersException;

/**
 * Standard MYSQL implementation of the database connection class
 *
 */
class MySQLDatabaseConnection extends DatabaseConnection {

    private $host;
    private $username;
    private $password;
    private $database;
    private $characterSet;
    private $port;
    private $socket;
    private $lastError;
    private $queryLogFile;

    /**
     * Constructor for MySQL
     *
     *
     * @param string $host
     * @param string $database
     * @param string $username
     * @param string $password
     * @return DatabaseConnection
     */
    public function __construct($host = null, $database = null, $username = null, $password = null, $port = null, $socket = null, $queryLogFile = null, $characterSet = null) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port ? $port : "3306";
        $this->socket = $socket;
        $this->queryLogFile = $queryLogFile;
        $this->characterSet = $characterSet;
    }

    /**
     * Get the host to connect to.
     *
     * @return unknown
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * Get the database connection in use.
     *
     * @return unknown
     */
    public function getDatabase() {
        return $this->database;
    }

    /**
     * Get the username in use for the db
     */
    public function getUsername() {
        return $this->username;
    }

    /**
     * Get the db password in use.
     *
     * @return unknown
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * @return the $port
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * @return the $socket
     */
    public function getSocket() {
        return $this->socket;
    }

    public function setHost($host) {
        $this->host = $host;
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
     * @param $database the $database to set
     */
    public function setDatabase($database) {
        $this->database = $database;
    }

    /**
     * @param $port the $port to set
     */
    public function setPort($port) {
        $this->port = $port;
    }

    /**
     * @param $socket the $socket to set
     */
    public function setSocket($socket) {
        $this->socket = $socket;
    }

    /**
     * @param mixed $characterSet
     */
    public function setCharacterSet($characterSet) {

        $this->characterSet = $characterSet;

        // If we have initialised the connection, set this now.
        if ($this->connection) {
            mysqli_set_charset($this->connection, $this->characterSet);
            mysqli_query($this->connection, "SET NAMES " . $this->characterSet);
        }
    }

    /**
     * @return mixed
     */
    public function getCharacterSet() {
        return $this->characterSet;
    }


    public function getUnderlyingConnection() {
        if (!$this->connection) {

            $this->connection = mysqli_init();
            $success =
                @mysqli_real_connect($this->connection, $this->host, $this->username, $this->password, $this->database, $this->port, $this->socket);

            if (!$success) {
                $this->lastError = $this->connection->error;
                throw new DatabaseConnectionException ("MySQL");
            }

            $this->connection->query("SET sql_mode = ''");

            // Initialise the character set if this was passed as configuration.
            if ($this->characterSet) {
                $this->setCharacterSet($this->characterSet);
            }
        }

        return $this->connection;
    }

    /**
     * Begin a transaction
     * Also switch off auto commit when the main transaction starts.
     */
    public function beginTransaction() {
        if ($this->transactionDepth == 0) {
            $this->getUnderlyingConnection()->autocommit(false);
        }
        parent::beginTransaction();
    }

    /**
     * Commit the current transaction
     * Also switch back on auto commit once the outer transaction is complete.
     */
    public function commit() {
        parent::commit();
        if ($this->transactionDepth == 0) $this->getUnderlyingConnection()->autocommit(true);
    }

    /**
     * Rollback the current transaction
     * Also switch back on auto commit once the outer transaction is complete.
     */
    public function rollback() {
        parent::rollback();

        if ($this->transactionDepth == 0) $this->getUnderlyingConnection()->autocommit(true);
    }

    /**
     * Implement the get table meta data functionality for mysql connections
     *
     * @param string $tableName
     * @return TableMetaData
     */
    public function getTableMetaData($tableName) {

        try {
            $results = $this->queryWithResults("SHOW COLUMNS FROM " . $tableName,);

            // Add each field as a table column to the array
            $tableColumns = array();
            while ($row = $results->nextRow()) {
                $columnSpec = $row ["Type"];
                $columnType =
                    $this->getSQLTypeForMySQLType(strpos($columnSpec, "(") ? substr($columnSpec, 0, strpos($columnSpec, "(")) : $columnSpec);
                $columnLength =
                    strpos($columnSpec, "(") ? substr($columnSpec, strpos($columnSpec, "(") + 1, strpos($columnSpec, ")") - strpos($columnSpec, "(") - 1) : "";

                $tableColumns [$row ["Field"]] = new MySQLTableColumn ($this, $row ["Field"], $columnType, $columnLength);
            }


            // Return the meta data
            return new TableMetaData ($tableName, $tableColumns);

        } catch (SQLException $e) {
            throw new SQLException ("Could not get meta data for table: " . $this->getLastErrorMessage());
        }
    }

    /**
     * Straightforward query method, used for inserts/updates/deletes, expects no returned results.
     *
     * @param string $sql
     * @param array $placeholders
     * @return boolean - Success or failure
     */
    public function query($sql, ...$placeholders) {

        $startTime = microtime(true);
        $result = $this->getUnderlyingConnection()->query($sql);
        $endTime = microtime(true);

        $logString = "\n\n" . $sql;

        if ($this->connection->error) {

            if ($this->connection->errno == 2006) {
                $this->connection = null;
                return $this->query($sql, $placeholders);
            }


            $this->lastError = $this->connection->error;

            $logString .= "\nERROR: " . $this->connection->error;

            if ($this->getQueryLogFile()) {
                file_put_contents($this->getQueryLogFile(), $logString, FILE_APPEND);
            }

            throw new SQLException ($this->connection->error);
        } else {

            if ($this->getQueryLogFile()) {
                file_put_contents($this->getQueryLogFile(), $logString . "\nQuery Completed in " . ($endTime - $startTime) . " seconds", FILE_APPEND);
            }
        }


        return $result;
    }

    /**
     * Query method which expects result rows.  This will return a MySQLResultSet if successful
     *
     * @param $sql
     * @param array $placeholders
     * @return MySQLResultSet|null
     */
    public function queryWithResults($sql, ...$placeholders) {

        $results = $this->query($sql,);
        if ($results) {
            return new MySQLResultSet ($results);
        } else {
            return null;
        }
    }

    /**
     * Execute a prepared statement.  This currently does not support returned results.
     *
     * @param PreparedStatement $sql
     * @return bool
     */
    public function createPreparedStatement($sql) {


        // Do MySQL preparation
        $mySQLStmt = $this->getUnderlyingConnection()->prepare($sql->getSQL());

        if ($mySQLStmt) {

            // Loop through each prepared statement, binding as appropriate
            $typeString = "";
            $values = array();
            $refValues = array();
            $bindParameters = $sql->getBindParameters();

            // Throw if mismatch of sizes.
            if (sizeof($bindParameters) != $mySQLStmt->param_count) {
                throw new WrongNumberOfPreparedStatementParametersException ($mySQLStmt->param_count, sizeof($bindParameters));
            }

            $blobWrappers = array();
            for ($i = 0; $i < sizeof($bindParameters); $i++) {

                $parameter = $bindParameters [$i];
                $parameterValue = $parameter->getValue();

                // Holder for a string type
                $isStringType = false;

                // Set type string according to types
                switch ($parameter->getSqlType()) {
                    case TableColumn::SQL_INT :
                    case TableColumn::SQL_SMALLINT :
                    case TableColumn::SQL_TINYINT :
                    case TableColumn::SQL_BIGINT :
                        $typeString .= "i";
                        break;
                    case TableColumn::SQL_DECIMAL :
                    case TableColumn::SQL_FLOAT :
                    case TableColumn::SQL_DOUBLE :
                        $typeString .= "d";
                        break;
                    case TableColumn::SQL_BLOB :
                        $typeString .= "b";

                        // Ensure we have a blob wrapper
                        if (!($parameterValue instanceof BlobWrapper)) {
                            $parameterValue = new BlobWrapper ($parameterValue);
                        }

                        // Keep track of the blob wrapper
                        $blobWrappers [$i] = $parameterValue;


                        break;
                    case TableColumn::SQL_DATE :
                        $typeString .= "s";
                        break;
                    default :
                        $typeString .= "s";
                        $isStringType = true;
                }

                $null = null;

                // If not Blobbing, add the value otherwise add null
                if ($parameter->getSqlType() == TableColumn::SQL_BLOB) {
                    $refValues [] = &$null;
                } else if ($isStringType || strlen($parameter->getValue()) > 0) {
                    $values [] = $parameter->getValue();
                    $refValues [] = &$values [sizeof($values) - 1];
                } else {
                    $refValues [] = &$null;
                }

            }

            // Construct and bind the params
            array_unshift($refValues, $typeString);

            // Call the bind param using reflection
            $reflection = new \ReflectionClass("mysqli_stmt");
            $method = $reflection->getMethod("bind_param");
            $method->invokeArgs($mySQLStmt, $refValues);

            // Send any blob data before execution.
            foreach ($blobWrappers as $index => $blobWrapper) {
                // Send the data in chunks to ensure mysql is happy
                while ($chunk = $blobWrapper->nextChunk()) {
                    $mySQLStmt->send_long_data($index, $chunk);
                }
            }


            if ($this->getQueryLogFile()) {
                $query = $sql->getExplicitSQL();
                file_put_contents($this->getQueryLogFile(), "\n" . $query, FILE_APPEND);
            }

            // Execute the prepared statement
            $success = $mySQLStmt->execute();

            $mySQLStmt->close();

            if (!$success) {
                $this->lastError = $mySQLStmt->error;
                throw new SQLException ($this->lastError);
            }

            return true;

        } else {
            $this->lastError = $this->connection->error;
            throw new SQLException ($this->lastError);
        }
    }

    /**
     * Overridden more efficient bulk insert logic for MySQL
     *
     * @param string $tableName
     * @param array $insertColumnNames
     * @param array $bulkData
     */
    public function bulkInsert($tableName, $insertColumnNames, $bulkData) {
        return $this->doBulkInsert($tableName, $insertColumnNames, $bulkData);
    }


    /**
     * Overridden more efficient bulk replace logic for MySQL
     *
     * @param string $tableName
     * @param array $insertColumnNames
     * @param array $primaryKeyColumnIndexes
     * @param array $bulkData
     */
    public function bulkReplace($tableName, $insertColumnNames, $primaryKeyColumnIndexes, $bulkData) {
        return $this->doBulkInsert($tableName, $insertColumnNames, $bulkData, "REPLACE");
    }


    /**
     * Escape a string ready for use in a query
     *
     * @param string $string
     */
    public function escapeString($string) {
        return $this->getUnderlyingConnection()->real_escape_string($string);
    }

    public function escapeColumn($columnName) {
        return "`" . $columnName . "`";
    }


    /**
     * Get the last auto increment id if an insert into auto increment occurred
     *
     */
    public function getLastAutoIncrementId() {
        return $this->getUnderlyingConnection()->insert_id;
    }

    /**
     * Get the last error message.
     *
     */
    public function getLastErrorMessage() {
        return $this->lastError;
    }

    /**
     * Close this database connection.
     *
     */
    public function close() {
        if ($this->connection) {
            try {
                $this->connection->close();
            } catch (\Exception $e) {
                // Ignore
            }
        }

    }

    // Get the standard SQL Type in ODBC Style for a mysql type
    private function getSQLTypeForMySQLType($mysqlType) {

        // Enumerate the types
        switch ($mysqlType) {
            case "varchar" :
            case "text" :
            case "tinytext" :
                return TableColumn::SQL_VARCHAR;
                break;
            case "tinyint" :
                return TableColumn::SQL_TINYINT;
                break;
            case "smallint" :
                return TableColumn::SQL_SMALLINT;
                break;
            case "mediumint" :
            case "int" :
                return TableColumn::SQL_INT;
                break;
            case "bigint" :
                return TableColumn::SQL_BIGINT;
                break;
            case "float" :
                return TableColumn::SQL_FLOAT;
                break;
            case "double" :
                return TableColumn::SQL_DOUBLE;
                break;
            case "decimal" :
                return TableColumn::SQL_DECIMAL;
                break;
            case "date" :
                return TableColumn::SQL_DATE;
                break;
            case "time" :
                return TableColumn::SQL_TIME;
                break;
            case "timestamp" :
            case "datetime" :
                return TableColumn::SQL_TIMESTAMP;
                break;
            case "blob" :
            case "longblob" :
            case "clob" :
            case "longclob" :
            case "mediumtext" :
            case "longtext" :
                return TableColumn::SQL_BLOB;
                break;
            default :
                return TableColumn::SQL_UNKNOWN;

        }

    }

    public function setQueryLogFile($queryLogFile) {
        $this->queryLogFile = $queryLogFile;
    }

    public function getQueryLogFile() {
        return $this->queryLogFile;
    }


    /**
     * Do a bulk insert / replace operation
     *
     * @param $tableName
     * @param $insertColumnNames
     * @param $bulkData
     * @return bool
     * @throws SQLException
     * @throws WrongNumberOfPreparedStatementParametersException
     */
    private function doBulkInsert($tableName, $insertColumnNames, $bulkData, $mode = "INSERT IGNORE") {
        $valuesClause = substr(str_repeat(",?", sizeof($insertColumnNames)), 1);
        $bulkClause = substr(str_repeat(",(" . $valuesClause . ")", sizeof($bulkData)), 1);

        $sql = $mode . " INTO " . $tableName . " (" . join(",", $insertColumnNames) . ") VALUES " . $bulkClause;
        $stmt = new PreparedStatement ($sql);

        $tableMetaData = $this->getTableMetaData($tableName);

        // loop through and bind each row
        foreach ($bulkData as $row) {
            $row = array_values($row);

            for ($i = 0; $i < sizeof($insertColumnNames); $i++) {
                $stmt->addBindParameter($tableMetaData->getColumn($insertColumnNames[$i])->getType(), $row [$i]);
            }
        }

        // Execute the prepared statement.
        return $this->createPreparedStatement($stmt);
    }


    /**
     * Override generate table SQL method to fix AUTO_INCREMENT pks.
     *
     * @param TableMetaData $tableMetaData
     */
    protected function generateCreateTableSQL($tableMetaData) {
        $parentSQL = parent::generateCreateTableSQL($tableMetaData);

        // Fix auto increment.
        $parentSQL = str_replace("AUTOINCREMENT", "AUTO_INCREMENT", $parentSQL);

        return $parentSQL;
    }


}

?>
