<?php

namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Exception\PrimaryKeyRowNotFoundException;
use Kinikit\Persistence\TableMapper\Exception\WrongPrimaryKeyLengthException;
use Kinikit\Persistence\TableMapper\Query\TableQueryEngine;
use Kinikit\Persistence\TableMapper\Relationship\TableRelationship;


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
     * @var TableRelationship[]
     */
    private $relationships = [];

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;


    /**
     * @var TableQueryEngine
     */
    private $queryEngine;

    /**
     * TableMapper constructor.
     *
     * @param string $tableName
     * @param TableRelationship[] $relationships
     * @param DatabaseConnection $databaseConnection
     */
    public function __construct($tableName, $relationships = [], $databaseConnection = null) {
        $this->tableName = $tableName;
        $this->relationships = $relationships ?? [];
        $this->databaseConnection = $databaseConnection ?? Container::instance()->get(DatabaseConnection::class);
        $this->queryEngine = new TableQueryEngine($tableName, $relationships, $databaseConnection);
    }


    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * Get the table query engine
     *
     * @return TableQueryEngine
     */
    public function getQueryEngine() {
        return $this->queryEngine;
    }


    /**
     * Lazy load the primary key columns using the db connection
     *
     * @return string[]
     */
    public function getPrimaryKeyColumnNames() {
        return array_keys($this->databaseConnection->getTableMetaData($this->tableName)->getPrimaryKeyColumns());
    }

    /**
     * Lazy load the column names using the db connection
     */
    protected function getAllColumnNames() {
        return array_keys($this->databaseConnection->getTableMetaData($this->tableName)->getColumns());
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

        $pkColumnNames = $this->getPrimaryKeyColumnNames();

        if (sizeof($primaryKeyValue) != sizeof($pkColumnNames)) {
            throw new WrongPrimaryKeyLengthException($this->tableName, $primaryKeyValue, sizeof($pkColumnNames));
        }

        $primaryKeyClauses = [];
        foreach ($pkColumnNames as $column) {
            $primaryKeyClauses[] = $column . "=?";
        }

        $primaryKeyClause = join(" AND ", $primaryKeyClauses);

        $results = array_values($this->queryEngine->query("SELECT * FROM {$this->tableName} WHERE {$primaryKeyClause}", $primaryKeyValue));
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

        $primaryKeyColumns = $this->getPrimaryKeyColumnNames();

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
                $primaryKeyClause[] = $primaryKeyColumns[$pkIndex] . "=?";
            }
            $primaryKeyClauses[] = "(" . join(" AND ", $primaryKeyClause) . ")";


        }

        $whereClause = join(" OR ", $primaryKeyClauses);

        $results = $this->queryEngine->query("SELECT * FROM {$this->tableName} WHERE $whereClause", $placeholderValues, true);

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
     * Get a filtered list of items matching the supplied WHERE / ORDER BY clause which should include the
     * directive WHERE, ORDER BY, LIMIT, OFFSET etc.
     *
     *
     * @param string $whereClause
     * @param mixed[] $placeholderValues
     */
    public function filter($whereClause = "", ...$placeholderValues) {

        // If just a where clause, handle this otherwise assume full query.
        $results = $this->queryEngine->query("SELECT * FROM {$this->tableName} " . $whereClause, $placeholderValues);

        return array_values($results);

    }


    /**
     * Get a values array for one or more expressions (either column names or SQL expressions e.g. count, distinct etc) using
     * items from this table or related entities thereof.
     *
     * @param $expressions
     * @param $whereClause
     * @param mixed ...$placeholderValues
     */
    public function values($expressions, $whereClause = "", ...$placeholderValues) {

        $valuesOnly = !is_array($expressions);
        if ($valuesOnly) {
            $expressions = [$expressions];
        }

        $results = $this->queryEngine->query("SELECT " . join(", ", $expressions) . " FROM {$this->tableName} " . $whereClause, $placeholderValues);
        $results = array_values($results);

        // if values only, reduce to values array
        if ($valuesOnly) {
            foreach ($results as $index => $result) {
                $resultValue = array_values($result);
                $results[$index] = array_shift($resultValue);
            }
        }

        return $results;

    }


}
