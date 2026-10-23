<?php

namespace Kinikit\Persistence\Database\Connection;

use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableIndexColumn;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

class TestDatabaseConnection extends BaseDatabaseConnection {

    public $configParams;
    public $lastSQL;
    public $lastParameters;
    public $lastPreparedStatement;

    public $executedStatements = [];


    /**
     * Connect to the database.  This receives an array of normalised stripped config parameters
     * so e.g. "db.name" or "db.test.name" would be mapped to simply "name" for convenience of handling.
     *
     * @return boolean
     */
    public function connect($configParams = []): bool {
        $this->configParams = $configParams;
        return true;
    }

    /**
     * Escape a string value ready for use in a query.
     *
     * @param $string
     * @return string
     */
    public function escapeString($string): string {
        // TODO: Implement escapeString() method.
    }

    /**
     * Escape a column name ready for use in queries.
     *
     * @param $columnName
     * @return mixed
     */
    public function escapeColumn($columnName): string {
        // TODO: Implement escapeColumn() method.
    }

    /**
     * Actually do the query execution
     *
     * @param $sql
     * @param BindParameter[] $parameters
     * @return ResultSet
     */
    public function doQuery(string $sql, array $parameters): ResultSet {
        $this->lastSQL = $sql;
        $this->lastParameters = $parameters;
        return MockObjectProvider::mock(ResultSet::class);
    }


    /**
     * Create a prepared statement (usually an update operation) and return a boolean according to
     * whether or not it was successful
     *
     * @param $sql
     * @return PreparedStatement
     */
    public function doCreatePreparedStatement($sql): PreparedStatement {
        $this->executedStatements[] = $sql;
        $this->lastPreparedStatement = new TestPreparedStatement();
        return $this->lastPreparedStatement;
    }

    /**
     * Get the last auto increment id if an insert into auto increment occurred
     *
     */
    public function getLastAutoIncrementId(): int {
        // TODO: Implement getLastAutoIncrementId() method.
    }

    /**
     * Get the last error message if a query fails.
     *
     */
    public function getLastErrorMessage(): string {
        // TODO: Implement getLastErrorMessage() method.
    }

    /**
     * Close function if required to close the database connection.
     *
     */
    public function close(): void {
        // TODO: Implement close() method.
    }


    /**
     * Get table column meta data for a given table as an associative array keyed in by column name.
     *
     * @param $tableName
     * @return TableColumn[]
     */
    public function getTableColumnMetaData($tableName): array {
        return [new TableColumn("bingo", "int")];
    }

    /**
     * Return the index data for a table
     *
     * @param $tableName
     * @return TableIndex[]
     */
    public function getTableIndexMetaData($tableName): array {
        return [new TableIndex("testindex", [new TableIndexColumn("bingo")])];
    }

    public function getDDLManager(): \Kinikit\Persistence\Database\DDL\DDLManager {
        // TODO: Implement getDDLManager() method.
    }
}
