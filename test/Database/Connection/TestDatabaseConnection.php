<?php

namespace Kinikit\Persistence\Database\Connection;

class TestDatabaseConnection extends BaseDatabaseConnection {

    public $configParams;
    public $lastSQL;


    /**
     * Connect to the database.  This receives an array of normalised stripped config parameters
     * so e.g. "db.name" or "db.test.name" would be mapped to simply "name" for convenience of handling.
     *
     * @return boolean
     */
    public function connect($configParams = []) {
        $this->configParams = $configParams;
        return true;
    }

    /**
     * Escape a string value ready for use in a query.
     *
     * @param $string
     * @return string
     */
    public function escapeString($string) {
        // TODO: Implement escapeString() method.
    }

    /**
     * Escape a column name ready for use in queries.
     *
     * @param $columnName
     * @return mixed
     */
    public function escapeColumn($columnName) {
        // TODO: Implement escapeColumn() method.
    }

    /**
     * Issue a SQL query without results, returns success / failure.
     *
     * @param $sql
     * @return boolean
     */
    public function query($sql) {
        $this->lastSQL = $sql;
    }

    /**
     * Issue a SQL query with results.  Returns a ResultSet if successful or
     * may throw SQLException if issues
     *
     * @param $sql
     * @return ResultSet
     * @throws SQLException
     */
    public function queryWithResults($sql) {
        // TODO: Implement queryWithResults() method.
    }

    /**
     * Execute a prepared statement (usually an update operation) and return a boolean according to
     * whether or not it was successful
     *
     * @param $preparedStatement
     * @return boolean
     */
    public function executePreparedStatement($preparedStatement) {
        // TODO: Implement executePreparedStatement() method.
    }

    /**
     * Get the last auto increment id if an insert into auto increment occurred
     *
     */
    public function getLastAutoIncrementId() {
        // TODO: Implement getLastAutoIncrementId() method.
    }

    /**
     * Get the last error message if a query fails.
     *
     */
    public function getLastErrorMessage() {
        // TODO: Implement getLastErrorMessage() method.
    }

    /**
     * Close function if required to close the database connection.
     *
     */
    public function close() {
        // TODO: Implement close() method.
    }
}
