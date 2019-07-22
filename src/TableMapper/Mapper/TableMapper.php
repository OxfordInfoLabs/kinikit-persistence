<?php

namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Exception\PrimaryKeyRowNotFoundException;
use Kinikit\Persistence\TableMapper\Exception\WrongPrimaryKeyLengthException;


/**
 * Main
 *
 * Class TableMapper
 */
class TableMapper {

    /**
     * @var string
     */
    private $tableName;


    /**
     * @var string[]
     */
    private $primaryKeyColumns;


    /**
     * @var Relationship[]
     */
    private $relationships;

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;


    /**
     * TableMapper constructor.
     *
     * @param string $tableName
     * @param mixed $primaryKeyColumns
     * @param Relationship[] $relationships
     * @param DatabaseConnection $databaseConnection
     */
    public function __construct($tableName, $primaryKeyColumns, $relationships = [], $databaseConnection = null) {
        $this->tableName = $tableName;
        $this->primaryKeyColumns = is_array($primaryKeyColumns) ? $primaryKeyColumns : [$primaryKeyColumns];
        $this->relationships = $relationships;
        $this->databaseConnection = $databaseConnection ?? Container::instance()->get(DatabaseConnection::class);
    }


    /**
     * Fetch a row by primary key from the configured table
     *
     * @param mixed $primaryKeyValue
     */
    public function fetch($primaryKeyValue) {

        // If primary key is not an array, make it so.
        if (!is_array($primaryKeyValue)) {
            $primaryKeyValue = [$primaryKeyValue];
        }

        if (sizeof($primaryKeyValue) != sizeof($this->primaryKeyColumns)) {
            throw new WrongPrimaryKeyLengthException($this->tableName, $primaryKeyValue, sizeof($this->primaryKeyColumns));
        }

        $primaryKeyClauses = [];
        foreach ($this->primaryKeyColumns as $column) {
            $primaryKeyClauses[] = $column . "=?";
        }

        $primaryKeyClause = join(" AND ", $primaryKeyClauses);

        $results = array_values($this->doQuery("SELECT * FROM {$this->tableName} WHERE {$primaryKeyClause}", $primaryKeyValue));
        if (sizeof($results) > 0) {
            return $results[0];
        } else {
            throw new PrimaryKeyRowNotFoundException($this->tableName, $primaryKeyValue);
        }


    }

    /**
     * Fetch multiple rows by primary key from the configured table.  If ignore missing objects
     * is supplied no exception is raised, otherwise error is raised if row count <> primary keys supplied.
     *
     * @param $primaryKeyValues
     * @param bool $ignoreMissingObjects
     * @return mixed
     */
    public function multiFetch($primaryKeyValues, $ignoreMissingObjects = false) {

        if (!is_array($primaryKeyValues)) {
            $primaryKeyValues = [$primaryKeyValues];
        }

        $primaryKeyClauses = [];
        $placeholderValues = [];
        $serialisedPks = [];
        foreach ($primaryKeyValues as $index => $primaryKey) {

            // If primary key is not an array, make it so.
            if (!is_array($primaryKey)) {
                $primaryKey = [$primaryKey];
            }

            $serialisedPks[] = join("||", $primaryKey);

            $primaryKeyClause = [];
            foreach ($primaryKey as $pkIndex => $value) {
                $placeholderValues[] = $value;
                $primaryKeyClause[] = $this->primaryKeyColumns[$pkIndex] . "=?";
            }
            $primaryKeyClauses[] = "(" . join(" AND ", $primaryKeyClause) . ")";


        }

        $whereClause = join(" OR ", $primaryKeyClauses);

        $results = $this->doQuery("SELECT * FROM {$this->tableName} WHERE $whereClause", $placeholderValues, true);

        $orderedResults = [];
        foreach ($serialisedPks as $serialisedPk) {
            if (isset($results[$serialisedPk]))
                $orderedResults[] = $results[$serialisedPk];
            else if (!$ignoreMissingObjects)
                throw new PrimaryKeyRowNotFoundException($this->tableName, $primaryKeyValues, true);
        }

        return $orderedResults;

    }


    /**
     * Supply a query with ? placeholders and an array of placeholder values to substitute.
     *
     * @param string $query
     * @param mixed[] $placeholderValues
     */
    public function query($query, ...$placeholderValues) {

        // If just a where clause, handle this otherwise assume full query.
        if (substr(strtoupper(trim($query)), 0, 5) == "WHERE") {
            $results = $this->doQuery("SELECT * FROM {$this->tableName} " . $query, $placeholderValues);
        } else {
            $results = $this->doQuery($query, $placeholderValues);
        }

        return array_values($results);

    }


    // Actually do a query with or without results.
    private function doQuery($query, $placeholderValues) {

        $result = $this->databaseConnection->query($query, $placeholderValues);
        $rows = [];
        while ($row = $result->nextRow()) {

            // Index by PK for internal processing
            $pkValue = [];
            foreach ($this->primaryKeyColumns as $primaryKeyColumn) {
                $pkValue[] = $row[$primaryKeyColumn];
            }

            $rows[join("||", $pkValue)] = $row;
        }

        return $rows;

    }

}
