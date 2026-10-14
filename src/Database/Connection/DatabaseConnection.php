<?php

namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Persistence\Database\BulkData\BulkDataManager;
use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

/**
 * Database connection interface
 *
 * @implementationConfigParam db.provider
 * @implementation sqlite3 \Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3DatabaseConnection
 * @implementation mysql \Kinikit\Persistence\Database\Vendors\MySQL\MySQLDatabaseConnection
 * @implementation postgresql \Kinikit\Persistence\Database\Vendors\PostgreSQL\PostgreSQLDatabaseConnection
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
    public function connect(array $configParams = []): bool;


    /**
     * Escape a string value ready for use in a query.
     *
     * @param string $string
     * @return string
     */
    public function escapeString(string $string): string;


    /**
     * Escape a column name ready for use in queries.
     *
     * @param string $columnName
     * @return string
     */
    public function escapeColumn(string $columnName): string;


    /**
     * Parse SQL prior to execution - used to make vendor specific alterations from the default
     * SQL dialect (SQLite)
     *
     * @param string $sql
     * @param BindParameter[] &$parameterValues
     * @return string
     */
    public function parseSQL(string $sql, &$parameterValues = []): string;


    /**
     * Issue a SQL query with results.  Returns a ResultSet if successful or
     * may throw SQLException if issues
     *
     * Placeholders may be applied which will be evaluated.  These may be supplied
     * either as raw values (SQL type inferred) or as BindParameter objects with an explicit SQL type.
     *
     * @param string $sql
     * @param mixed|BindParameter ...$placeholders
     * @return ResultSet
     * @throws SQLException
     */
    public function query(string $sql, ...$placeholders): ResultSet;


    /**
     * Execute a statement which doesn't return any results.
     * This will usually be implemented via a call to createPreparedStatement
     * as a convenience macro for a single statement.  Returns a boolean
     * indicating success of statement.  Placeholders may be supplied either as raw
     * values or as BindParameter objects.
     *
     * @param string $sql
     * @param mixed|BindParameter ...$placeholders
     * @return boolean
     * @throws SQLException
     */
    public function execute(string $sql, ...$placeholders);


    /**
     * Execute a prepared statement (usually an update operation) and return a boolean according to
     * whether or not it was successful
     *
     * @param string $sql
     * @return PreparedStatement
     * @throws SQLException
     */
    public function createPreparedStatement(string $sql): PreparedStatement;


    /**
     * Execute a script containing multiple statements terminated by ;
     * Split each statement and execute in turn.
     *
     * @param string $scriptContents
     */
    public function executeScript(string $scriptContents);


    /**
     * Get the last auto increment id if an insert into auto increment occurred
     *
     * @return int
     * @throws SQLException
     */
    public function getLastAutoIncrementId(): int;


    /**
     * Get the last error message if a query fails.
     *
     * @return string
     */
    public function getLastErrorMessage(): string;


    /**
     * Begin a transaction for this connection or start a new savepoint within
     * the current transaction.
     *
     * @throws SQLException
     */
    public function beginTransaction(): void;


    /**
     * Commit the current transaction
     *
     * @throws SQLException
     */
    public function commit(): void;


    /**
     * Rollback the transaction either to last savepoint or whole transaction (default)
     *
     * @param boolean $wholeTransaction
     * @throws SQLException
     */
    public function rollback(bool $wholeTransaction = true): void;


    /**
     * Close function if required to close the database connection.
     *
     * @throws SQLException
     */
    public function close(): void;


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
    public function getBulkDataManager(): BulkDataManager;

    /**
     * Get the DDL mnage for this database connection
     *
     * @return DDLManager
     */
    public function getDDLManager(): DDLManager;


}
