<?php

namespace Kinikit\Persistence\Database\Connection\ODBC;
use Kinikit\Persistence\Database\Connection\BlobWrapper;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Connection\TableMetaData;

/**
 * ODBC Database connection implementation
 *
 */
class ODBCDatabaseConnection extends DatabaseConnection {

    private $dsn;
    private $username;
    private $password;

    /**
     * Construct with all relevant parameters for connecting to ODBC
     * DSN is the Data source name referring to the equivalent one in the ODBC configuration on the machine
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @return ODBCDatabaseConnection
     */
    public function __construct($dsn = null, $username = "", $password = "") {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
    }


    /**
     * @return unknown
     */
    public function getDsn() {
        return $this->dsn;
    }

    /**
     * @return unknown
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * @return unknown
     */
    public function getUsername() {
        return $this->username;
    }

    /**
     * @param null|string $dsn
     */
    public function setDsn($dsn) {
        $this->dsn = $dsn;
    }

    /**
     * @param string $username
     */
    public function setUsername($username) {
        $this->username = $username;
    }

    /**
     * @param string $password
     */
    public function setPassword($password) {
        $this->password = $password;
    }

    /**
     * Overload get underlying connection to lazy load.
     */
    public function getUnderlyingConnection() {
        if (!$this->connection) {
            $this->connection = odbc_connect($this->dsn, $this->username, $this->password) or die (odbc_errormsg());
        }

        return $this->connection;
    }


    /**
     * @see DatabaseConnection::close()
     *
     */
    public function close() {
        odbc_commit($this->getUnderlyingConnection());
        odbc_close($this->getUnderlyingConnection());
    }

    /**
     * @see DatabaseConnection::escapeString()
     *
     * @param string $string
     */
    public function escapeString($string) {
        return str_replace("'", "''", $string);
    }

    /**
     * @see DatabaseConnection::getLastAutoIncrementId()
     *
     */
    public function getLastAutoIncrementId() {
        $result = odbc_exec($this->getUnderlyingConnection(), "select @@identity");
        $id = odbc_result($result, 1);
        odbc_free_result($result);
        return $id;
    }

    /**
     * @see DatabaseConnection::getLastErrorMessage()
     *
     */
    public function getLastErrorMessage() {
        return odbc_errormsg($this->getUnderlyingConnection());
    }

    /**
     * @param string $sql
     * @param array $placeholders
     * @return boolean
     * @see DatabaseConnection::query()
     *
     */
    public function query($sql, ...$placeholders) {
        return odbc_exec($this->getUnderlyingConnection(), $sql);

    }

    /**
     * @param unknown_type $sql
     * @param array $placeholders
     * @return ODBCResultSet|null
     * @see DatabaseConnection::queryWithResults()
     *
     */
    public function queryWithResults($sql, ...$placeholders) {
        $results = $this->query($sql,);
        if ($results) {
            return new ODBCResultSet ($results);
        } else {
            return null;
        }
    }

    /**
     * Bind and execute a standard prepared statement
     *
     * @param PreparedStatement $preparedStatement
     */
    public function executePreparedStatement($preparedStatement) {

        // Prepare an ODBC statement
        $odbcStatement = odbc_prepare($this->getUnderlyingConnection(), $preparedStatement->getSQL());

        if (!$odbcStatement) {
            return false;
        }

        // Form an array of the parameter values
        $paramValues = array();

        // Add each value to the param values array
        foreach ($preparedStatement->getBindParameters() as $param) {

            $value = $param->getValue();

            // If a blob, handle it seperately
            if ($param->getSqlType() == TableColumn::SQL_BLOB) {

                if (!($value instanceof BlobWrapper)) {
                    $value = new BlobWrapper ($value);
                }

                // Get the insert value
                $insertValue = $value->getContentText() ? $value->getContentText() : file_get_contents($value->getContentFileName());

                $paramValues [] = $insertValue;

            } else {
                $paramValues [] = $value;
            }
        }

        // Execute the call
        return odbc_execute($odbcStatement, $paramValues);

    }

    /**
     * Implement the get table meta data functionality for odbc connections
     *
     * @param string $tableName
     */
    public function getTableMetaData($tableName) {

        // Get the columns for the passed table
        $results = odbc_columns($this->getUnderlyingConnection(), null, null, $tableName, "%");

        $columns = array();
        while (odbc_fetch_into($results, $resultArray)) {

            $columnName = $resultArray [3];
            $dataType = $resultArray [4];
            $length = $resultArray [6];

            $columns [$columnName] = new TableColumn ($columnName, $dataType, $length);

        }


        return new TableMetaData ($tableName, $columns);

    }

}

?>
