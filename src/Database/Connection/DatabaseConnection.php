<?php

namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

/**
 * Database connection interface
 *
 * @implementationConfigParam db.provider
 * @implementation sqlite3 \Kinikit\Persistence\Database\Vendor\SQLite3\SQLite3DatabaseConnection
 * @implementation mysql \Kinikit\Persistence\Database\Vendor\MySQL\MySQLDatabaseConnection
 *
 * Interface DatabaseConnection
 */
interface DatabaseConnection {


    /**
     * Connect to the database.  This receives an array of normalised stripped config parameters
     * so e.g. "db.name" or "db.test.name" would be mapped to simply "name" for convenience of handling.
     *
     * @return boolean
     */
    public function connect($configParams = []);


    /**
     * Escape a string value ready for use in a query.
     *
     * @param $string
     * @return string
     */
    public function escapeString($string);


    /**
     * Escape a column name ready for use in queries.
     *
     * @param $columnName
     * @return mixed
     */
    public function escapeColumn($columnName);


    /**
     * Issue a SQL query without results, returns database specific result.
     *
     * @param $sql
     * @param array $placeholders
     *
     * @return mixed
     * @throws SQLException
     */
    public function query($sql, ...$placeholders);


    /**
     * Issue a SQL query with results.  Returns a ResultSet if successful or
     * may throw SQLException if issues
     *
     * Placeholders may be applied which will be evaluated.
     *
     * @param $sql
     * @param array $placeholders
     * @return ResultSet
     * @throws SQLException
     */
    public function queryWithResults($sql, ...$placeholders);


    /**
     * Execute a prepared statement (usually an update operation) and return a boolean according to
     * whether or not it was successful
     *
     * @param $preparedStatement
     * @return boolean
     * @throws SQLException
     */
    public function executePreparedStatement($preparedStatement);


    /**
     * Get the last auto increment id if an insert into auto increment occurred
     *
     * @return int
     * @throws SQLException
     */
    public function getLastAutoIncrementId();


    /**
     * Get the last error message if a query fails.
     *
     */
    public function getLastErrorMessage();


    /**
     * Begin a transaction for this connection or start a new savepoint within
     * the current transaction.
     *
     * @throws SQLException
     */
    public function beginTransaction();


    /**
     * Commit the current transaction
     *
     * @throws SQLException
     */
    public function commit();


    /**
     * Rollback the transaction either to last savepoint or whole transaction (default)
     *
     * @param boolean $wholeTransaction
     * @throws SQLException
     */
    public function rollback($wholeTransaction = true);


    /**
     * Close function if required to close the database connection.
     *
     * @throws SQLException
     */
    public function close();


}
