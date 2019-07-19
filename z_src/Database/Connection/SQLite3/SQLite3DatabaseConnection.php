<?php

namespace Kinikit\Persistence\Database\Connection\SQLite3;

use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Connection\BlobWrapper;
use Kinikit\Persistence\Database\Connection\PreparedStatement;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Connection\TableMetaData;
use Kinikit\Persistence\Database\Exception\SQLException;

/**
 * Database connection implementation for SQLite 3
 * NB:  This needs the optional sqlite3 libraries installed to work correctly
 *
 */
class SQLite3DatabaseConnection extends DatabaseConnection {


    private $logFile;

    /**
     * Construct the connection object with the absolute filename to the database file.
     *
     * @param filename
     *
     * @return SQLite3DatabaseConnection
     */
    public function __construct($filename, $logFile = null) {
        $this->connection = new \PDO ("sqlite:" . $filename);
        $this->connection->sqliteCreateFunction("SQRT", "sqrt", 1);
        $this->logFile = $logFile;
    }

    /**
     * @see BaseConnection::close()
     *
     */
    public function close() {

    }

    /**
     * @param string $string
     * @see BaseConnection::escapeString()
     *
     */
    public function escapeString($string) {
        return str_replace("'", "''", $string);
    }

    /**
     * @see BaseConnection::getLastAutoIncrementId()
     *
     */
    public function getLastAutoIncrementId() {
        return $this->connection->lastInsertId();
    }

    /**
     * @see BaseConnection::getLastErrorMessage()
     *
     */
    public function getLastErrorMessage() {
        $errorInfo = $this->connection->errorInfo();
        return join(", ", $errorInfo);
    }

    /**
     * @param unknown_type $tableName
     * @see BaseConnection::getTableMetaData()
     *
     */
    public function getTableMetaData($tableName) {

        $results = $this->queryWithResults("PRAGMA table_info('" . $tableName . "')",);

        if ($results) {

            // Now iterate through and make table columns
            $tableColumns = array();
            while ($row = $results->nextRow()) {

                $name = $row ["name"];
                $types = explode(" ", $row ["type"]);
                $type = array_shift($types);

                $columnType = $this->getSQLTypeForSQLLiteType(strpos($type, "(") ? substr($type, 0, strpos($type, "(")) : $type);
                $columnLength = strpos($type, "(") ? substr($type, strpos($type, "(") + 1, strpos($type, ")") - strpos($type, "(") - 1) : "";

                $tableColumns [$name] = new TableColumn ($name, $columnType, $columnLength ? $columnLength : 0);
            }

            $results->close();

            if (sizeof($tableColumns) == 0) {
                throw new SQLException ("Unable to retrieve table meta data for Table " . $tableName . " - doesn't exist");
            }

            return new TableMetaData ($tableName, $tableColumns);
        } else {
            throw new SQLException ("Unable to retrieve table meta data for Table " . $tableName . " - " . sqlite3_error($this->connection));
        }
    }

    /**
     * @param string $sql
     * @param array $placeholders
     * @return boolean
     * @see BaseConnection::query()
     */
    public function query($sql, ...$placeholders) {


        $results = $this->connection->exec($sql);

        if ($results === false) {
            throw new SQLException(join(',', $this->connection->errorInfo()));
        } else {

            if ($this->logFile) {
                file_put_contents($this->logFile, "\n\n" . $sql, FILE_APPEND);
            }

            return null;
        }

    }

    /**
     * @param string $sql
     * @param array $placeholders
     * @return SQLite3ResultSet
     * @see BaseConnection::queryWithResults()
     */
    public function queryWithResults($sql, ...$placeholders) {


        $statement = $this->connection->prepare($sql);

        if ($statement) {
            $success = $statement->execute();
            if ($success) {

                if ($this->logFile) {
                    file_put_contents($this->logFile, "\n\n" . $sql, FILE_APPEND);
                }

                return new SQLite3ResultSet ($statement);
            } else {
                throw new SQLException(join(',', $this->connection->errorInfo()));
            }
        } else {
            throw new SQLException(join(',', $this->connection->errorInfo()));
        }
    }

    /**
     * Execute a prepared statement for sqlite 3 database
     *
     * @param PreparedStatement $preparedStatement
     */
    public function executePreparedStatement($preparedStatement) {


        $sqlite3Stmt = $this->connection->prepare($preparedStatement->getSQL());

        if (!$sqlite3Stmt)
            return false;

        $params = $preparedStatement->getBindParameters();

        $blobHandle = null;

        // Bind the params accordingly, accounting for blob fields as well.
        for ($i = 0; $i < sizeof($params); $i++) {

            $paramValue = $params [$i]->getValue();

            if ($params [$i]->getSqlType() == TableColumn::SQL_BLOB) {
                if (!($paramValue instanceof BlobWrapper)) {
                    $paramValue = new BlobWrapper ($paramValue);
                }

                if ($paramValue->getContentFileName()) {
                    $blobHandle = fopen($paramValue->getContentFileName(), "r");
                    $sqlite3Stmt->bindValue(($i + 1), $blobHandle, \PDO::PARAM_LOB);
                } else {
                    $sqlite3Stmt->bindValue(($i + 1), $paramValue->getContentText(), \PDO::PARAM_LOB);
                }

            } else {
                $sqlite3Stmt->bindValue(($i + 1), $paramValue);
            }
        }

        $success = $sqlite3Stmt->execute();

        // Close the statement
        $sqlite3Stmt->closeCursor();

        // Close up any blob handles.
        if ($blobHandle)
            fclose($blobHandle);

        if ($this->logFile) {
            file_put_contents($this->logFile, "\n\n" . $preparedStatement->getSQL(), FILE_APPEND);
        }

        return $success;

    }

    public function insertBlankRow($tableName) {
        $this->query("INSERT INTO $tableName DEFAULT VALUES",);
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
        // Grab the meta data
        $metaData = $this->getTableMetaData($tableName);

        // Create the value placeholders string
        $valuePlaceholders = substr(str_repeat(",?", sizeof($insertColumnNames)), 1);

        // Now process each row in turn.
        foreach ($bulkData as $row) {
            $row = array_values($row);

            $stmt = new PreparedStatement ("REPLACE INTO " . $tableName . " (" . join(",", $insertColumnNames) . ") VALUES (" . $valuePlaceholders . ")");
            for ($i = 0; $i < sizeof($insertColumnNames); $i++) {
                $column = $metaData->getColumn($insertColumnNames [$i]);
                $stmt->addBindParameter($column->getType(), $row [$i]);
            }
            $this->executePreparedStatement($stmt);
        }

        return true;
    }


    // Get the standard SQL Type in ODBC Style for a sql lite type
    private function getSQLTypeForSQLLiteType($sqlLiteType) {

        // Enumerate the types
        switch (strtolower($sqlLiteType)) {
            case "varchar" :
            case "text" :
            case "nvarchar" :
                return TableColumn::SQL_VARCHAR;
                break;
            case "integer" :
                return TableColumn::SQL_INT;
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
}

?>
