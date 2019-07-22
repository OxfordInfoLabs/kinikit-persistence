<?php

namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Exception\PrimaryKeyRowNotFoundException;
use Kinikit\Persistence\TableMapper\Exception\WrongPrimaryKeyLengthException;
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
     * @var string[]
     */
    private $primaryKeyColumns = [];


    /**
     * @var string[]
     */
    private $allColumns = [];


    /**
     * @var TableRelationship[]
     */
    private $relationships = [];

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;


    /**
     * Transient array of relationship aliases used when mapping data back
     *
     * @var array
     */
    private $relationshipMappingData;


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
        $this->lookupColumns();
    }


    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * Get the primary key columns
     *
     * @return string[]
     */
    public function getPrimaryKeyColumns() {
        return $this->primaryKeyColumns;
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
     * Get a filtered list of items matching the supplied WHERE / ORDER BY clause which should include the
     * directive WHERE, ORDER BY, LIMIT, OFFSET etc.
     *
     *
     * @param string $whereClause
     * @param mixed[] $placeholderValues
     */
    public function filter($whereClause, ...$placeholderValues) {

        // If just a where clause, handle this otherwise assume full query.
        $results = $this->doQuery("SELECT * FROM {$this->tableName} " . $whereClause, $placeholderValues);

        return array_values($results);

    }


    // Actually do a query with or without results.
    private function doQuery($query, $placeholderValues) {

        // Process the query parts for relationships.
        list($additionalSelectColumns, $joinClauses, $fullPathAliases) = $this->processQueryPartsForRelationships("", "_X");


        // Substitute where clause params first.
        $query = preg_replace_callback("/[0-9a-z_\.]+/", function ($matches) use ($fullPathAliases) {
            if (isset($matches[0])) {
                if (isset($this->allColumns[$matches[0]])) {
                    return "_X." . $matches[0];
                } else {
                    $splitParam = explode(".", $matches[0]);
                    $column = array_pop($splitParam);
                    $prefix = join(".", $splitParam);
                    if (isset($fullPathAliases[$prefix])) {
                        return $fullPathAliases[$prefix] . $column;
                    }
                }
            }
            return $matches[0];
        }, $query);


        // Now add additional columns to the select clause if required.
        if (strpos($query, "SELECT *") === 0) {
            $select = "SELECT ";

            $myColumns = [];
            foreach ($this->allColumns as $column) {
                $myColumns[] = "_X.$column _X__$column";
            }
            $select .= join(", ", $myColumns);

            if (sizeof($additionalSelectColumns) > 0) {
                $select .= ", " . join(", ", $additionalSelectColumns);
            }

            $query = $select . substr($query, 8);

        }

        // Finally, add join clauses to the from clause.
        $replacementClause = "FROM {$this->tableName} _X ";
        if (sizeof($joinClauses) > 0) {
            $replacementClause .= "\n" . join("\n", $joinClauses);
        }

        $query = str_replace("FROM {$this->tableName}", $replacementClause, $query);


        $results = $this->databaseConnection->query($query, $placeholderValues)->fetchAll();

        /**
         * Loop through all results and process indexed by pk.
         */
        $rows = [];
        foreach ($results as $result) {
            $this->processQueryResult($result, $rows, "_X__");
        }


        // Clean relational data before returning
        $this->cleanRelationshipData($rows);

        return $rows;


    }


    /**
     * Process a single query result including mapping relationship data.
     *
     * @param $results
     * @return array
     */
    protected function processQueryResult($result, &$rows, $myTableAlias) {

        // Index by PK for internal processing
        $pkValue = [];
        foreach ($this->primaryKeyColumns as $primaryKeyColumn) {
            $pkValue[] = $result["_X__" . $primaryKeyColumn];
        }

        $pkString = join("||", $pkValue);

        // if we haven't seen a row for this one yet, create it.
        if (!isset($rows[$pkString])) {

            $newRow = [];

            // Loop though processing data for my table alias.
            $allNull = true;
            foreach ($result as $key => $value) {

                if (substr($key, 0, strlen($myTableAlias)) == $myTableAlias) {
                    $newRow[substr($key, strlen($myTableAlias))] = $value;

                    if ($value != null) {
                        $allNull = false;
                    }

                }

            }

            // Provided at least one value in the row add it in.
            if (!$allNull)
                $rows[$pkString] = $newRow;


        }

        // Map any relationship data
        foreach ($this->relationships as $index => $relationship) {
            $relationshipAlias = $this->relationshipMappingData[$index]["alias"];

            if (isset($rows[$pkString][$relationship->getMappedMember()])) {
                $relationship->getRelatedTableMapper()->processQueryResult($result, $rows[$pkString][$relationship->getMappedMember()], $relationshipAlias);
            } else {
                $newRow = [];
                $relationship->getRelatedTableMapper()->processQueryResult($result, $newRow, $relationshipAlias);
                if (sizeof($newRow) > 0) {
                    $rows[$pkString][$relationship->getMappedMember()] = $newRow;
                }
            }

        }

    }


    /**
     * Clean relationship data after the fact to ensure that singles and arrays are returned
     * according to rules.
     */
    protected function cleanRelationshipData(&$rows) {
        foreach ($rows as $key => $row) {

            foreach ($this->relationships as $relationship) {

                // If we have a relational entry in the data, proceed
                if (isset($row[$relationship->getMappedMember()])) {

                    // Clean sub entries first
                    $relationship->getRelatedTableMapper()->cleanRelationshipData($row[$relationship->getMappedMember()]);

                    $values = array_values($row[$relationship->getMappedMember()]);
                    if ($relationship->isMultiple()) {
                        $rows[$key][$relationship->getMappedMember()] = $values;
                    } else {
                        $rows[$key][$relationship->getMappedMember()] = $values[0];
                    }
                }

            }
        }
    }


    /**
     * Process query parts for relationships.
     *
     * @param $query
     */
    protected function processQueryPartsForRelationships($parentPath, $parentAlias) {
        $selectJoinClauses = [];
        $additionalSelectColumns = [];

        $this->relationshipMappingData = [];
        $fullPathAliases = [];
        foreach ($this->relationships as $index => $relationship) {

            // Generate an alias for this relationship in the query
            $alias = $parentAlias . chr(65 + $index);

            // Get the select join clause.
            $selectJoinClauses[] = $relationship->getSelectJoinClause($parentAlias, $alias);

            // Now create additional select columns
            $relatedTableMapper = $relationship->getRelatedTableMapper();

            $columns = $relatedTableMapper->allColumns;
            foreach ($columns as $column) {
                $additionalSelectColumns[] = $alias . "." . $column . " " . $alias . "__" . $column;
            }

            $this->relationshipMappingData[] = ["alias" => $alias . "__",
                "fullMember" => $parentPath . $relationship->getMappedMember()];


            $fullPathAliases[$parentPath . $relationship->getMappedMember()] = $alias . ".";

            // Call the related entity recursively to add other join data.
            list ($relatedSelectColumns, $relatedJoinClauses, $relatedFullPathAliases) = $relationship->getRelatedTableMapper()->processQueryPartsForRelationships($parentPath . $relationship->getMappedMember() . ".", $alias);
            $additionalSelectColumns = array_merge($additionalSelectColumns, $relatedSelectColumns);
            $selectJoinClauses = array_merge($selectJoinClauses, $relatedJoinClauses);
            $fullPathAliases = array_merge($fullPathAliases, $relatedFullPathAliases);


        }

        return array($additionalSelectColumns, $selectJoinClauses, $fullPathAliases);
    }


// Lookup columns both PK and regular using table meta data.
    private function lookupColumns() {
        $tableColumns = $this->databaseConnection->getTableColumnMetaData($this->tableName);
        foreach ($tableColumns as $tableColumn) {
            if ($tableColumn->isPrimaryKey()) {
                $this->primaryKeyColumns[] = $tableColumn->getName();
            }

            $this->allColumns[$tableColumn->getName()] = $tableColumn->getName();

        }

    }

}
