<?php

namespace Kinikit\Persistence\Database\Connection;

use Kinikit\Core\Object\SerialisableObject;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

/**
 * Abstract class acting as base connection class for all database connections
 *
 */
abstract class DatabaseConnection extends SerialisableObject {

    // Handle on actual connection object for use directly in code
    // where bespoke queries etc are required.
    protected $connection = null;
    protected $transactionDepth = 0;

    /**
     * Return the underlying connection object in use by this connection instance
     *
     * @return mysqli
     */
    public function getUnderlyingConnection() {
        return $this->connection;
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
            $this->query("BEGIN");
        } else {
            $this->query("SAVEPOINT SP" . $this->transactionDepth);
        }

    }

    /**
     * Commit the current transaction.  This function has no effect if not currently in a transaction.
     *
     */
    public function commit() {

        $this->query("COMMIT");

        // Reset the transaction depth
        $this->transactionDepth = 0;
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
                $this->query("ROLLBACK");
            } else {
                $this->query("ROLLBACK TO SAVEPOINT SP" . $this->transactionDepth);
            }

            // Decrement the transaction depth
            $this->transactionDepth = max(0, $this->transactionDepth - 1);

        } catch (SQLException $e) {
            // Ignore for now
        }
    }

    /**
     * Get table meta data object for a given table.
     *
     * @return TableMetaData
     */
    public abstract function getTableMetaData($tableName);

    /**
     * Escape a string ready for use in a query
     *
     * @param string $string
     */
    public abstract function escapeString($string);

    /**
     * Escape a column for database specific purposes e.g. [columnName] in SQL Server `columnName` in MySQL etc. (Default implementation returns the name intact)
     *
     * @param String $columnName
     */
    public function escapeColumn($columnName) {
        return $columnName;
    }

    /**
     * Straightforward query method, used for inserts/updates/deletes, expects no returned results.
     *
     * @param string $sql
     * @return boolean - Success or failure
     */
    public abstract function query($sql);

    /**
     * Query method which expects result rows.  This will call the passed function on each row,
     * return a result set for querying.
     *
     * @return ResultSet
     */
    public abstract function queryWithResults($sql);

    /**
     * Execute a query which returns a single value.
     *
     * @param string $sql
     */
    public function queryForSingleValue($sql) {
        $results = $this->queryWithResults($sql);
        if (!$results)
            return null;
        $row = $results->nextRow();
        if ($row) {
            $rowValues = array_values($row);
            return $rowValues [0];
        } else {
            return null;
        }
    }

    /**
     * Execute a prepared statement in standard prepared statement format.
     * At the moment this need not worry about statements which return result
     * sets as the primary use for this is to facilitate updating queries with large objects
     * where bound variables are more prudent.
     *
     * @param PreparedStatement $preparedStatement
     */
    public abstract function executePreparedStatement($preparedStatement);


    /**
     * Create a table, using table meta data object as assistant.
     *
     * @param TableMetaData $tableMetaData
     */
    public function createTable($tableMetaData) {

        // Create the table using the create table SQL
        $this->query($this->generateCreateTableSQL($tableMetaData));
    }


    /**
     * Generate create table SQL
     *
     * @param $tableMetaData
     */
    protected function generateCreateTableSQL($tableMetaData) {

        $sql = "CREATE TABLE {$tableMetaData->getTableName()} (\n";

        $columnLines = array();
        $pks = array();
        foreach ($tableMetaData->getColumns() as $column) {

            $line = $column->getName() . " " . $column->getSQLType();
            if ($column->getLength()) $line .= "(" . $column->getLength() . ")";
            if ($column->getNotNull()) $line .= " NOT NULL";
            if ($column->getPrimaryKey()) {
                if ($column->getAutoIncrement())
                    $line .= ' PRIMARY KEY';
                else
                    $pks[] = $column->getName();
            }
            if ($column->getAutoIncrement()) $line .= ' AUTOINCREMENT';

            $columnLines[] = $line;
        }


        $sql .= join(",\n", $columnLines);

        if (sizeof($pks) > 0) {
            $sql .= ",\nPRIMARY KEY (" . join(",", $pks) . ")";
        }

        $sql .= "\n)";

        return $sql;

    }


    /**
     * Insert a blank row into a table
     *
     * @param $tableName
     */
    public function insertBlankRow($tableName) {
        $this->query("INSERT INTO " . $tableName . " VALUES ()");
    }


    /**
     * Bulk insert a series of rows into a table using the passed column names and the bulk data
     * The bulk data should be structured as a 2 dimensional array with each row containing a value for each column in the order
     * specified in the column names array.  The out of the box implementation does this in the bog standard way of an insert per row
     * but where possible (e.g. mysql) this should be made much more efficient.
     *
     * @param string $tableName
     * @param array $insertColumnNames
     * @param array $bulkData
     */
    public function bulkInsert($tableName, $insertColumnNames, $bulkData) {

        // Grab the meta data
        $metaData = $this->getTableMetaData($tableName);

        // Create the value placeholders string
        $valuePlaceholders = substr(str_repeat(",?", sizeof($insertColumnNames)), 1);

        // Now process each row in turn.
        foreach ($bulkData as $row) {
            $row = array_values($row);

            $stmt = new PreparedStatement ("INSERT INTO " . $tableName . " (" . join(",", $insertColumnNames) . ") VALUES (" . $valuePlaceholders . ")");
            for ($i = 0; $i < sizeof($insertColumnNames); $i++) {
                $column = $metaData->getColumn($insertColumnNames [$i]);
                $stmt->addBindParameter($column->getType(), $row [$i]);
            }
            $this->executePreparedStatement($stmt);
        }

        return true;

    }


    /**
     * Bulk replace a series of rows into a table using the passed column names as insert columns
     *
     * The primary key should be specified as an array of indexes into the insertColumnNames array (e.g array(0) for the first column index)
     *
     * The bulk data should be structured as a 2 dimensional array with each row containing a value for each column in the order
     * specified in the column names array.
     *
     * The out of the box implementation does this fairly inefficiently by simply first calling the bulkDelete function followed by the bulk insert function,
     * but this may be overloaded in implementations e.g. MySQL where multi replace is supported.
     *
     * @param string $tableName
     * @param array $insertColumnNames
     * @param array $primaryKeyColumnIndexes
     * @param array $bulkData
     */
    public function bulkReplace($tableName, $insertColumnNames, $primaryKeyColumnIndexes, $bulkData) {

        $deleteColumnNames = array();
        foreach ($primaryKeyColumnIndexes as $columnIndex) {
            $deleteColumnNames[] = $insertColumnNames[$columnIndex];
        }

        // Generate key values
        $keyValues = array();
        foreach ($bulkData as $dataItem) {
            $keyValue = array();
            foreach ($primaryKeyColumnIndexes as $columnIndex) {
                $keyValue[] = $dataItem[$columnIndex];
            }
            $keyValues[] = $keyValue;
        }


        // Bulk delete existing
        $this->bulkDelete($tableName, $deleteColumnNames, $keyValues);

        // Bulk insert new
        $this->bulkInsert($tableName, $insertColumnNames, $bulkData);

        return true;

    }


    /**
     * Bulk delete as series of rows from a table using the passed key column names and key values.
     * The bulk data should be a single or two dimensional array, depending whether the delete key is
     * single or compound.  The out of the box implementation creates an in clause in the case of a
     * single key or a series of and clauses logically orred together in a compound case.
     *
     * @param string $tableName
     * @param array $deleteKeyColumnNames
     * @param array $keyValues
     */
    public function bulkDelete($tableName, $deleteKeyColumnNames, $keyValues) {

        // Grab the meta data
        $metaData = $this->getTableMetaData($tableName);

        if (!is_array($deleteKeyColumnNames))
            $deleteKeyColumnNames = array($deleteKeyColumnNames);

        $preparedStatement = new PreparedStatement ();

        // If a single delete key, deal using an IN Clause
        if (sizeof($deleteKeyColumnNames) == 1) {
            $column = $metaData->getColumn($deleteKeyColumnNames [0]);
            $whereClause = $column->getName() . " IN (?" . str_repeat(",?", sizeof($keyValues) - 1) . ")";
        } else {
            $deleteClauses = array();
            foreach ($deleteKeyColumnNames as $columnName) {
                $deleteClauses [] = $columnName . "=?";
            }
            $orClause = "(" . join(" AND ", $deleteClauses) . ")";

            $whereClause = $orClause . str_repeat(" OR " . $orClause, sizeof($keyValues) - 1);
        }

        // Loop through each key value
        foreach ($keyValues as $keyValue) {
            if (!is_array($keyValue)) {
                $keyValue = array($keyValue);
            }
            for ($i = 0; $i < sizeof($keyValue); $i++) {
                $column = $metaData->getColumn($deleteKeyColumnNames [$i]);
                $preparedStatement->addBindParameter($column->getType(), $keyValue [$i]);
            }
        }

        $preparedStatement->setSQL("DELETE FROM " . $tableName . " WHERE " . $whereClause);
        $this->executePreparedStatement($preparedStatement);

        return true;
    }


    /**
     * Execute a script containing multiple statements terminated by ;
     * Split each statement and execute in turn.
     *
     * @param $scriptContents
     */
    public function executeScript($scriptContents) {


        $numberProcessed = 1;

        while ($numberProcessed > 0)
            $scriptContents = preg_replace("/'(.*?);(.*?)'/", "'$1||^^$2'", $scriptContents, -1, $numberProcessed);


        $splitStatements = explode(";", $scriptContents);


        foreach ($splitStatements as $statement) {

            if (trim($statement)) {

                $numberProcessed = 1;

                while ($numberProcessed > 0)
                    $statement = preg_replace("/'(.*?)\|\|\^\^(.*?)'/", "'$1;$2'", $statement, -1, $numberProcessed);


                $this->query($statement);

            }
        }

    }


    /**
     * Get the last error message if a query fails.
     *
     */
    public abstract function getLastErrorMessage();

    /**
     * Get the last auto increment id if an insert into auto increment occurred
     *
     */
    public abstract function getLastAutoIncrementId();

    /**
     * Abstract close function which must be implemented in a connection specific way by subclass.
     *
     */
    public abstract function close();

}

?>
