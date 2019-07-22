<?php

namespace Kinikit\Persistence\Database\Connection;

use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

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
     * Actually do the query execution
     *
     * @param $sql
     * @param $placeholderValues
     * @return void
     */
    public function doQuery($sql, $placeholderValues) {
        $this->lastSQL = $sql;
    }


    /**
     * Create a prepared statement (usually an update operation) and return a boolean according to
     * whether or not it was successful
     *
     * @param $sql
     * @return void
     */
    public function createPreparedStatement($sql) {
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
