<?php

namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Persistence\Database\BulkData\BulkDataManager;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
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
    public function query($sql, ...$placeholders);


    /**
     * Execute a statement which doesn't return any results.
     * This will usually be implemented via a call to createPreparedStatement
     * as a convenience macro for a single statement.  Returns a boolean
     * indicating success of statement.
     *
     * @param $sql
     * @param mixed ...$placeholders
     * @return boolean
     * @throws SQLException
     */
    public function execute($sql, ...$placeholders);


    /**
     * Execute a prepared statement (usually an update operation) and return a boolean according to
     * whether or not it was successful
     *
     * @param string $sql
     * @return PreparedStatement
     * @throws SQLException
     */
    public function createPreparedStatement($sql);


    /**
     * Execute a script containing multiple statements terminated by ;
     * Split each statement and execute in turn.
     *
     * @param $scriptContents
     */
    public function executeScript($scriptContents);


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


    /**
     * Get Table meta data for a table name
     *
     * @param $tableName
     * @return \Kinikit\Persistence\Database\MetaData\TableMetaData
     */
    public function getTableMetaData($tableName);


    /**
     * Get the bulk data manager for this database connection.
     * Used for Inserting, Updating and Replacing table data in bulk.
     * This is useful as different RDBMS engines do and don't support
     * use of certain operations.
     *
     * @return BulkDataManager
     */
    public function getBulkDataManager();


}
