<?php


namespace Kinikit\Persistence\TableMapper\Mapper;


use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Mapper\TableMapper;
use Kinikit\Persistence\TableMapper\Relationship\TableRelationship;


/**
 * Query engine for a given table configured optionally with relationships and a database connection
 *
 * Class TableQueryEngine
 * @package Kinikit\Persistence\TableMapper\Query
 */
class TableQueryEngine {

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var TableRelationship[]
     */
    private $relationships;

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;

    /**
     * @var string[]
     */
    private $relationshipAliasPrefixes;

    /**
     * Table query class - provides support for
     * queries and relational queries on relationships for the passed table
     * and relationships structure
     *
     * @param TableMapper $tableMapper
     *
     * TableQuery constructor.
     */
    public function __construct($tableMapper) {
        if (is_string($tableMapper)) {
            $tableMapper = new TableMapper($tableMapper);
        }
        $this->tableName = $tableMapper->getTableName();
        $this->relationships = $tableMapper->getRelationships();
        $this->databaseConnection = $tableMapper->getDatabaseConnection();
    }


    /**
     * Execute a query for the defined table,
     *
     * @param $query
     * @param $placeholderValues
     * @return array
     * @throws \Kinikit\Persistence\Database\Exception\SQLException
     */
    public function query($query, $placeholderValues = []) {

        // Process the query parts for relationships.
        list($additionalSelectColumns, $joinClauses, $fullPathAliases) = $this->processQueryPartsForRelationships("", "_X");

        $allColumns = $this->getAllColumnNames();

        $doubleQueryRequired = strpos(strtoupper($query), "OFFSET") !== false || strpos(strtoupper($query), "LIMIT") !== false;

        // Substitute params for both select and WHERE clause for optimisation purposes below
        $query = preg_replace_callback("/[0-9a-z_\.]+/", function ($matches) use ($fullPathAliases, $allColumns, &$doubleQueryRequired) {
            if (isset($matches[0])) {
                if (in_array($matches[0], $allColumns)) {
                    return "_X." . $matches[0];
                } else {
                    $splitParam = explode(".", $matches[0]);
                    $column = array_pop($splitParam);
                    $prefix = join(".", $splitParam);
                    if (isset($fullPathAliases[$prefix])) {
                        $fullPathAlias = $fullPathAliases[$prefix];
                        if ($fullPathAlias[1]->isMultiple()) $doubleQueryRequired = true;
                        return $fullPathAlias[0] . $column;
                    }
                }
            }
            return $matches[0];
        }, $query);


        if (strpos($query, "SELECT") !== 0) {
            $query = "SELECT * FROM {$this->tableName} " . $query;
        }

        // If we have a select * query add all required columns
        $requiresMapping = false;
        if (strpos($query, "SELECT *") === 0) {

            // If we need to perform a double query, do this now and return
            if ($doubleQueryRequired) {

                // Select just distinct primary keys as a first query
                $pks = $this->query("SELECT DISTINCT " . join(", ", $this->getPrimaryKeyColumnNames()) . substr($query, 8), $placeholderValues);

                // Create clauses
                $clauses = [];
                $pkPlaceholders = [];
                foreach ($pks as $pkRow) {
                    $pkClauses = [];
                    foreach ($pkRow as $column => $value) {
                        $pkClauses[] = "$column = ?";
                        $pkPlaceholders[] = $value;
                    }
                    $clauses[] = "(" . join(" AND ", $pkClauses) . ")";
                }

                // No do a second query for just the pks
                $pkQuery = "WHERE " . join(" OR ", $clauses);

                return $this->query($pkQuery, $pkPlaceholders);

            }

            // Now add all select columns to the query
            $select = "SELECT ";

            $myColumns = [];
            foreach ($allColumns as $column) {
                $myColumns[] = "_X.$column _X__$column";
            }
            $select .= join(", ", $myColumns);

            if (sizeof($additionalSelectColumns) > 0) {
                $select .= ", " . join(", ", $additionalSelectColumns);
            }

            $query = $select . substr($query, 8);

            $requiresMapping = true;

        }


        // Finally, add join clauses to the from clause.
        $replacementClause = "FROM {$this->tableName} _X ";
        if (sizeof($joinClauses) > 0) {
            $replacementClause .= "\n" . join("\n", $joinClauses);
        }

        $query = str_replace("FROM {$this->tableName}", $replacementClause, $query);

        $results = $this->databaseConnection->query($query, $placeholderValues)->fetchAll();

        if ($requiresMapping) {

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
        } else {
            return $results;
        }


    }


    /**
     * Process a single query result including mapping relationship data.
     *
     * @param $results
     * @return array
     */
    protected function processQueryResult($result, &$rows, $myTableAlias) {


        $primaryKeyColumns = $this->getPrimaryKeyColumnNames();

        // Index by PK for internal processing
        $pkValue = [];
        foreach ($primaryKeyColumns as $primaryKeyColumn) {
            $pkValue[] = $result[$myTableAlias . $primaryKeyColumn];
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
            $relationshipAlias = $this->relationshipAliasPrefixes[$index];

            if (isset($rows[$pkString][$relationship->getMappedMember()])) {
                $relationship->getRelatedTableMapper()->getQueryEngine()->processQueryResult($result, $rows[$pkString][$relationship->getMappedMember()], $relationshipAlias);
            } else {
                $newRow = [];
                $relationship->getRelatedTableMapper()->getQueryEngine()->processQueryResult($result, $newRow, $relationshipAlias);
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
                    $relationship->getRelatedTableMapper()->getQueryEngine()->cleanRelationshipData($row[$relationship->getMappedMember()]);

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

        $primaryKeyColumns = $this->getPrimaryKeyColumnNames();

        $this->relationshipAliasPrefixes = [];
        $fullPathAliases = [];
        foreach ($this->relationships as $index => $relationship) {

            // Generate an alias for this relationship in the query
            $alias = $parentAlias . chr(65 + $index);

            // Relationship alias prefix is with an additional __
            $relationshipAliasPrefix = $alias . "__";

            // Get the select join clause.
            $selectJoinClauses[] = $relationship->getSelectJoinClause($parentAlias, $alias);

            // Now create additional select columns
            $relatedTableMapper = $relationship->getRelatedTableMapper();

            $columns = $relatedTableMapper->getQueryEngine()->getAllColumnNames();
            foreach ($columns as $column) {
                $additionalSelectColumns[] = $alias . "." . $column . " " . $relationshipAliasPrefix . $column;
            }


            $this->relationshipAliasPrefixes[] = $relationshipAliasPrefix;


            $fullPathAliases[$parentPath . $relationship->getMappedMember()] = [$alias . ".", $relationship];

            // Call the related entity recursively to add other join data.
            list ($relatedSelectColumns, $relatedJoinClauses, $relatedFullPathAliases) = $relationship->getRelatedTableMapper()->getQueryEngine()->processQueryPartsForRelationships($parentPath . $relationship->getMappedMember() . ".", $alias);
            $additionalSelectColumns = array_merge($additionalSelectColumns, $relatedSelectColumns);
            $selectJoinClauses = array_merge($selectJoinClauses, $relatedJoinClauses);
            $fullPathAliases = array_merge($fullPathAliases, $relatedFullPathAliases);


        }

        return array($additionalSelectColumns, $selectJoinClauses, $fullPathAliases);
    }


    /**
     * Get the primary key columns
     *
     * @return string[]
     */
    protected function getPrimaryKeyColumnNames() {
        return array_keys($this->databaseConnection->getTableMetaData($this->tableName)->getPrimaryKeyColumns());
    }


    /**
     * Get all column names
     */
    protected function getAllColumnNames() {
        return array_keys($this->databaseConnection->getTableMetaData($this->tableName)->getColumns());
    }

}
