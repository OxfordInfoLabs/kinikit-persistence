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
     * @var BulkDataManager
     */
    private $bulkDataManager;


    // Save operations
    const SAVE_OPERATION_INSERT = "INSERT";
    const SAVE_OPERATION_UPDATE = "UPDATE";
    const SAVE_OPERATION_REPLACE = "REPLACE";
    const SAVE_OPERATION_SAVE = "SAVE";


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
        $this->queryEngine = new TableQueryEngine($this);
        $this->bulkDataManager = $this->databaseConnection->getBulkDataManager();

        // Ensure we synchronise parent mappers.
        foreach ($this->relationships as $relationship) {
            $relationship->setParentMapper($this);
        }

    }


    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * @return TableRelationship[]
     */
    public function getRelationships(): array {
        return $this->relationships;
    }

    /**
     * @return DatabaseConnection
     */
    public function getDatabaseConnection() {
        return $this->databaseConnection;
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
     * Return a boolean indicating whether or not this has auto increment PK.
     *
     * @return bool
     */
    protected function getAutoIncrementPk() {
        $pkColumns = $this->databaseConnection->getTableMetaData($this->tableName)->getPrimaryKeyColumns();
        foreach ($pkColumns as $pkColumn) {
            if ($pkColumn->isAutoIncrement())
                return $pkColumn->getName();
        }
        return null;
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


    /**
     * Insert data into this table and any other relational tables.  Please note, the
     * data array is mutable for use when relational data is being updated.
     *
     * @param $data
     */
    public function insert($data) {

        // Process save data and act accordingly
        $data = $this->processSaveData($data);

        // Check for auto increment pk
        $autoIncrementPk = $this->getAutoIncrementPk();

        // if we have relationship data, we need to process this here.
        if (isset($data["relationshipData"])) {

            // Run pre-save operations for certain relationship types
            foreach ($data["relationshipData"] as $relationshipIndex => $relationshipDatum) {
                $this->relationships[$relationshipIndex]->preParentSaveOperation(self::SAVE_OPERATION_INSERT, $relationshipDatum);
            }

            // If an auto increment pk, need to insert / update each value
            if ($autoIncrementPk) {
                foreach ($data["saveRows"] as $index => $item) {
                    $this->bulkDataManager->insert($this->tableName, $item);
                    $data["saveRows"][$index][$autoIncrementPk] = $this->databaseConnection->getLastAutoIncrementId();
                }
            } else {
                $this->bulkDataManager->insert($this->tableName, $data["saveRows"]);
            }


            // Run post-save operations for certain relationship types
            foreach ($data["relationshipData"] as $relationshipIndex => $relationshipDatum) {
                $this->relationships[$relationshipIndex]->postParentSaveOperation(self::SAVE_OPERATION_INSERT, $relationshipDatum);
                $relationshipDatum->updateParentMember();
            }


            $objectBinder = Container::instance()->get(ObjectBinder::class);
            echo json_encode($objectBinder->bindToArray($data["saveRows"]), JSON_PRETTY_PRINT);


        } else {
            $this->bulkDataManager->insert($this->tableName, $data["saveRows"]);
        }
    }



    // Process incoming data for a save operation
    // Essentially return a structured array ready for relational processing
    private function processSaveData($data) {

        if (!isset($data[0])) {
            $data = [$data];
        }


        // if we have relationships, process otherwise simply return data for insert.
        if (sizeof($this->relationships) > 0) {

            // Sift through the relationships first and decide which ones need to pre-process and which ones can wait.
            $structuredData = ["saveRows" => [], "relationshipData" => []];
            foreach ($data as $index => $datum) {

                // Process relationship data
                foreach ($this->relationships as $relIndex => $relationship) {

                    if (isset($data[$index][$relationship->getMappedMember()])) {

                        // Now get the data for the relationship.
                        $relationshipData = $data[$index][$relationship->getMappedMember()];
                        if (!isset($relationshipData[0])) $relationshipData = [$relationshipData];

                        if (!isset($structuredData["relationshipData"][$relIndex]))
                            $structuredData["relationshipData"][$relIndex] = new TableRelationshipSaveData($relationship->getMappedMember(), $relationship->isMultiple());

                        $structuredData["relationshipData"][$relIndex]->addChildRows($datum, $relationshipData);

                        // Remove the mapped member from the parent insert array.
                        unset($datum[$relationship->getMappedMember()]);
                    }
                }

                $structuredData["saveRows"][] = &$datum;

            }


            return $structuredData;

        } else {
            return ["saveRows" => $data];
        }

    }

}
