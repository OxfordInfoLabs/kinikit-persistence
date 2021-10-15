<?php

namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\Binding\ObjectBinder;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\BulkData\BulkDataManager;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Exception\PrimaryKeyRowNotFoundException;
use Kinikit\Persistence\TableMapper\Exception\WrongPrimaryKeyLengthException;
use Kinikit\Persistence\TableMapper\Relationship\ManyToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\ManyToOneTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\TableRelationship;


/**
 * Main Table mapper
 *
 * @noProxy
 *
 * Class TableMapper
 */
class TableMapper {


    /**
     * @var TableQueryEngine
     */
    private $queryEngine;


    /**
     * @var TablePersistenceEngine
     */
    private $persistenceEngine;

    /**
     * TableMapper constructor - designed for autowiring of dependencies.
     *
     * @param TableQueryEngine $queryEngine
     * @param TablePersistenceEngine $persistenceEngine
     */
    public function __construct($queryEngine, $persistenceEngine) {
        $this->queryEngine = $queryEngine;
        $this->persistenceEngine = $persistenceEngine;
    }


    /**
     * Fetch a row by primary key from the configured table mapper
     *
     * @param string|TableMapping $tableMapping
     * @param mixed $primaryKeyValue
     * @return mixed[]
     */
    public function fetch($tableMapping, $primaryKeyValue) {

        // Ensure we have a table mapping object
        if (is_string($tableMapping)) {
            $tableMapping = new TableMapping($tableMapping);
        }

        // If primary key is not an array, make it so.
        if (!is_array($primaryKeyValue)) {
            $primaryKeyValue = [$primaryKeyValue];
        }

        $tableName = $tableMapping->getTableName();
        $pkColumnNames = $tableMapping->getPrimaryKeyColumnNames();

        if (sizeof($primaryKeyValue) != sizeof($pkColumnNames)) {
            throw new WrongPrimaryKeyLengthException($tableName, $primaryKeyValue, sizeof($pkColumnNames));
        }

        $primaryKeyClauses = [];
        foreach ($pkColumnNames as $column) {
            $primaryKeyClauses[] = $column . "=?";
        }

        $primaryKeyClause = join(" AND ", $primaryKeyClauses);


        $results = $this->queryEngine->query($tableMapping, "SELECT * FROM {$tableName} WHERE {$primaryKeyClause}", $primaryKeyValue);
        $results = is_array($results) ? array_values($results) : [];
        if (sizeof($results) > 0) {
            return $results[0];
        } else {
            throw new PrimaryKeyRowNotFoundException($tableName, $primaryKeyValue);
        }


    }

    /**
     * Fetch multiple rows by primary key from the configured table.  If ignore missing objects
     * is supplied no exception is raised, otherwise error is raised if row count <> primary keys supplied.
     *
     * @param string|TableMapping $tableMapping
     * @param mixed[] $primaryKeyValues
     * @param bool $ignoreMissingObjects
     * @return mixed[]
     */
    public function multiFetch($tableMapping, $primaryKeyValues, $ignoreMissingObjects = false) {

        // Ensure we have a table mapping object
        if (is_string($tableMapping)) {
            $tableMapping = new TableMapping($tableMapping);
        }

        if (!is_array($primaryKeyValues)) {
            $primaryKeyValues = [$primaryKeyValues];
        }

        $primaryKeyColumns = $tableMapping->getPrimaryKeyColumnNames();
        $tableName = $tableMapping->getTableName();


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


        $results = $this->queryEngine->query($tableMapping, "SELECT * FROM {$tableName} WHERE $whereClause", $placeholderValues);

        $orderedResults = [];
        foreach ($serialisedPks as $serialisedPk) {
            if (isset($results[$serialisedPk]))
                $orderedResults[$serialisedPk] = $results[$serialisedPk];
            else if (!$ignoreMissingObjects)
                throw new PrimaryKeyRowNotFoundException($tableName, $primaryKeyValues, true);
        }

        return $orderedResults;

    }


    /**
     * Get a filtered list of items matching the supplied WHERE / ORDER BY clause which should include the
     * directive WHERE, ORDER BY, LIMIT, OFFSET etc.
     *
     * @param string|TableMapping $tableMapping
     * @param string $whereClause
     * @param mixed[] $placeholderValues
     */
    public function filter($tableMapping, $whereClause = "", ...$placeholderValues) {

        // Ensure we have a table mapping object
        if (is_string($tableMapping)) {
            $tableMapping = new TableMapping($tableMapping);
        }

        if (sizeof($placeholderValues) == 1 && is_array($placeholderValues[0]))
            $placeholderValues = $placeholderValues[0];

        $tableName = $tableMapping->getTableName();


        // If just a where clause, handle this otherwise assume full query.
        $results = $this->queryEngine->query($tableMapping, "SELECT * FROM {$tableName} " . $whereClause, $placeholderValues);
        return array_values($results);

    }


    /**
     * Get a values array for one or more expressions (either column names or SQL expressions e.g. count, distinct etc) using
     * items from this table or related entities thereof.
     *
     * @param string|TableMapping $tableMapping
     * @param $expressions
     * @param $whereClause
     * @param mixed ...$placeholderValues
     */
    public function values($tableMapping, $expressions, $whereClause = "", ...$placeholderValues) {

        // Ensure we have a table mapping object
        if (is_string($tableMapping)) {
            $tableMapping = new TableMapping($tableMapping);
        }

        if (sizeof($placeholderValues) == 1 && is_array($placeholderValues[0]))
            $placeholderValues = $placeholderValues[0];


        $valuesOnly = !is_array($expressions);
        if ($valuesOnly) {
            $expressions = [$expressions];
        }

        $results = $this->queryEngine->query($tableMapping, "SELECT " . join(", ", $expressions) . " FROM {$tableMapping->getTableName()} " . $whereClause, $placeholderValues);
        $results = is_array($results) ? array_values($results) : [];

        // if values only, reduce to values array
        if ($valuesOnly) {
            foreach ($results as $index => $result) {
                $resultValue = array_values($result);
                $results[$index] = array_shift($resultValue);
            }
        }

        return $results;

    }


    /**
     * Insert data using the supplied table mapper.  This will perform a strict insert (i.e. no updates to existing data)
     *
     * @param string|TableMapping $tableMapping
     * @param $data
     */
    public function insert($tableMapping, $data) {

        // Ensure we have a table mapping object
        if (is_string($tableMapping)) {
            $tableMapping = new TableMapping($tableMapping);
        }

        // Save the rows using insert operation.
        $this->persistenceEngine->saveRows($tableMapping, $data, TablePersistenceEngine::SAVE_OPERATION_INSERT);
    }


    /**
     * Update data using the supplied table mapper.  This will perform a strict update (i.e. no new inserts).  Any new
     * data will be ignored.
     *
     * @param string|TableMapping $tableMapping
     * @param $data
     */
    public function update($tableMapping, $data) {

        // Ensure we have a table mapping object
        if (is_string($tableMapping)) {
            $tableMapping = new TableMapping($tableMapping);
        }

        // Save the rows using insert operation.
        $this->persistenceEngine->saveRows($tableMapping, $data, TablePersistenceEngine::SAVE_OPERATION_UPDATE);
    }


    /**
     * Replace data using the supplied table mapper.  This will essentially remove and insert data so will handle new and
     * existing data.  Please note any subordinate relational entities attached will be replaced down the chain but unlike
     * the save operation it will not clean up any additional relational entities.
     *
     * @param string|TableMapping $tableMapping
     * @param $data
     */
    public function replace($tableMapping, $data) {

        // Ensure we have a table mapping object
        if (is_string($tableMapping)) {
            $tableMapping = new TableMapping($tableMapping);
        }

        // Save the rows using insert operation.
        $this->persistenceEngine->saveRows($tableMapping, $data, TablePersistenceEngine::SAVE_OPERATION_REPLACE);
    }


    /**
     * Save data using the supplied table mapper.  This is the most useful and natural method for saving trees of data
     * it will create / update new items as required and also remove any non-supplied items in e.g. many-to-many
     * or one-to-many relationships to provide a consistently saved object tree.  Used by the ORM framework.
     *
     * @param string|TableMapping $tableMapping
     * @param $data
     */
    public function save($tableMapping, $data) {

        // Ensure we have a table mapping object
        if (is_string($tableMapping)) {
            $tableMapping = new TableMapping($tableMapping);
        }

        // Save the rows using insert operation.  Return the result
        return $this->persistenceEngine->saveRows($tableMapping, $data, TablePersistenceEngine::SAVE_OPERATION_SAVE);

    }


    /**
     * Delete rows using the supplied table mapper.
     *
     * @param $tableMapping
     * @param $data
     */
    public function delete($tableMapping, $data) {

        // Ensure we have a table mapping object
        if (is_string($tableMapping)) {
            $tableMapping = new TableMapping($tableMapping);
        }

        // Delete rows
        $this->persistenceEngine->deleteRows($tableMapping, $data);
    }


}
