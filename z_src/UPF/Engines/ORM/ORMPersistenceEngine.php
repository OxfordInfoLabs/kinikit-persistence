<?php

namespace Kinikit\Persistence\UPF\Engines\ORM;

use Kinikit\Persistence\Database\Connection\PreparedStatement;
use Kinikit\Persistence\UPF\Engines\ORM\Dialects\ORMSQLDialectManager;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMFullQueryRequiredException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMObjectNotWritableException;
use Kinikit\Persistence\UPF\Engines\ORM\Query\SQLQuery;
use Kinikit\Persistence\UPF\Engines\ORM\Utils\ORMUtils;
use Kinikit\Persistence\UPF\Exception\UnsupportedEngineQueryException;
use Kinikit\Persistence\UPF\Framework\ObjectPersistenceEngine;

/**
 * Implementation of the persistence engine for Object Relational Mapping
 *
 * @author mark
 *
 */
class ORMPersistenceEngine extends ObjectPersistenceEngine {

    private $databaseConnection;
    private $dialect;
    private $cachedTableMetaData = array();
    private $cachedObjectColumnFieldMappings = array();

    /**
     * Construct
     *
     * @param BaseDatabaseConnection $databaseConnection
     * @param string $identifier
     */
    public function __construct($databaseConnection = null, $identifier = null) {

        $this->setDatabaseConnection($databaseConnection ? $databaseConnection : DefaultDB::instance());
        parent::__construct($identifier);
    }

    /**
     * Set the database connection
     *
     * @param DatabaseConnection $databaseConnection
     */
    public function setDatabaseConnection($databaseConnection) {
        $this->databaseConnection = $databaseConnection;
        $this->dialect = ORMSQLDialectManager::instance()->getDialectForConnection($databaseConnection);
    }

    /**
     * @return the $databaseConnection
     */
    public function getDatabaseConnection() {
        return $this->databaseConnection;
    }

    /**
     * Start a DB transaction when called by the persistence framework
     */
    public function persistenceTransactionStarted() {
        $this->databaseConnection->beginTransaction();
    }

    /**
     * Rollback the current transaction if it fails
     */
    public function persistenceTransactionFailed() {
        $this->databaseConnection->rollback();
    }

    /**
     * Commit the transaction if successful.
     */
    public function persistenceTransactionSucceeded() {
        $this->databaseConnection->commit();
    }

    /**
     * Get the object by pk
     *
     * @param ObjectMapper $objectMapper
     * @param mixed $objectPrimaryKey
     */
    public function getObjectDataByPrimaryKey($objectMapper, $objectPrimaryKey) {


        $objectPrimaryKey = is_array($objectPrimaryKey) ? $objectPrimaryKey : explode("||", $objectPrimaryKey);

        $staticTableInfo = ORMUtils::getStaticObjectTableInfo($objectMapper, $this->databaseConnection)["READ"];
        $primaryKeyFields = $objectMapper->getPrimaryKeyFields();
        $fieldColumnMap = $staticTableInfo->getFieldColumnMappings();

        $clauses = array();
        for ($i = 0; $i < sizeof($primaryKeyFields); $i++) {
            $pkColumn = $fieldColumnMap [$primaryKeyFields [$i]->getFieldName()];
            $pkValue = $objectPrimaryKey [$i];
            $clauses [] =
                $this->databaseConnection->escapeColumn($pkColumn->getName()) . "=" . $pkColumn->getSQLValue($pkValue);
        }

        $sql =
            $this->dialect->generateAllColumnSelectClause($staticTableInfo) . " FROM " . $staticTableInfo->getTableMetaData()->getTableName() . " WHERE " . join(" AND ", $clauses);

        $results = $this->databaseConnection->queryWithResults($sql);

        if ($row = $results->nextRow()) {
            return $this->mapRowColumnsToFields($row, $objectMapper);
        } else {
            return null;
        }

    }

    /**
     * Get multiple objects by primary key
     *
     * @param ObjectMapper $objectMapper
     * @param array $arrayOfPrimaryKeys
     */
    public function getMultipleObjectsDataByPrimaryKey($objectMapper, $arrayOfPrimaryKeys) {

        // Quit if no pks supplied
        if (sizeof($arrayOfPrimaryKeys) == 0) return null;

        $staticTableInfo = ORMUtils::getStaticObjectTableInfo($objectMapper, $this->databaseConnection)["READ"];
        $primaryKeyFields = $objectMapper->getPrimaryKeyFields();
        $fieldColumnMap = $staticTableInfo->getFieldColumnMappings();

        if (sizeof($primaryKeyFields) == 1) {

            $pkColumn = $fieldColumnMap [$primaryKeyFields [0]->getFieldName()];

            $insertValues = array();
            foreach ($arrayOfPrimaryKeys as $primaryKey) {
                $primaryKey = is_array($primaryKey) ? $primaryKey [0] : $primaryKey;
                $insertValues [] = $pkColumn->getSQLValue($primaryKey);
            }

            $sql =
                $this->dialect->generateAllColumnSelectClause($staticTableInfo) . "  FROM " . $staticTableInfo->getTableMetaData()->getTableName() . " WHERE " . $this->getDatabaseConnection()->escapeColumn($pkColumn->getName()) . " IN (" . join(",", $insertValues) . ")";
        } else {

            $whereClauses = array();
            foreach ($arrayOfPrimaryKeys as $primaryKey) {
                $primaryKey = is_array($primaryKey) ? array_values($primaryKey) : explode("||", $primaryKey);
                $pkClauses = array();
                for ($i = 0; $i < sizeof($primaryKeyFields); $i++) {
                    $pkColumn = $fieldColumnMap [$primaryKeyFields [$i]->getFieldName()];
                    $pkClauses [] =
                        $this->getDatabaseConnection()->escapeColumn($pkColumn->getName()) . "=" . $pkColumn->getSQLValue($primaryKey [$i]);
                }

                $whereClauses [] = "(" . join(" AND ", $pkClauses) . ")";
            }

            $sql =
                $this->dialect->generateAllColumnSelectClause($staticTableInfo) . "  FROM " . $staticTableInfo->getTableMetaData()->getTableName() . " WHERE " . join(" OR ", $whereClauses);

        }

        $results = $this->databaseConnection->queryWithResults($sql);

        $mappedRows = array();
        while ($row = $results->nextRow()) {
            $mappedRow = $this->mapRowColumnsToFields($row, $objectMapper);
            $pk = $objectMapper->getPrimaryKeyValueForArrayOfValues($mappedRow);

            $mappedRows [join("||", $pk)] = $mappedRow;
        }


        return $mappedRows;

    }

    /**
     * Get all objects for the set of field values.  Essentially execute a where clause
     * for each field passed.
     *
     *
     * (non-PHPdoc)
     * @see ObjectPersistenceEngine::getObjectsForFieldValues()
     */
    public function getObjectsDataForFieldValues($objectMapper, $fieldValues, $orderingFields) {

        $staticTableInfo = ORMUtils::getStaticObjectTableInfo($objectMapper, $this->databaseConnection)["READ"];
        $fieldColumnMappings = $staticTableInfo->getFieldColumnNameMappings();
        $tableMetaData = $staticTableInfo->getTableMetaData();


        // Create the where clause
        $clauses = array();
        foreach ($fieldValues as $fieldName => $fieldValue) {

            $escapedValue = $this->getDatabaseConnection()->escapeString($fieldValue);
            $columnName = isset($fieldColumnMappings [$fieldName]) ? $fieldColumnMappings[$fieldName] : null;
            $column = $tableMetaData->getColumn($columnName);

            if ($column) {
                $escapedValue =
                    (($column->isNumeric() && $escapedValue != null) ? $escapedValue : "'" . $escapedValue . "'");
                $clauses [] = $columnName . " = " . $escapedValue;
            }
        }


        $orderByClause = "";
        if ($orderingFields) {
            $orderByClauses = array();

            foreach ($orderingFields as $field) {

                if (isset($fieldColumnMappings[$field->getField()])) {
                    $orderByClauses [] = $fieldColumnMappings[$field->getField()] . " " . $field->getDirection();
                } else {
                    throw new OrderFieldDoesNotExistException($objectMapper->getClassName(), $field->getField());
                }

            }

            $orderByClause = " ORDER BY " . join(",", $orderByClauses);
        }


        return $this->query($objectMapper, (sizeof($clauses) > 0 ? "WHERE " . join(" AND ", $clauses) : "") . $orderByClause);
    }


    /**
     * Perform a flexible query for objects.  Currently, this expects an SQL string which may be either a full select string or simply
     * a where clause.  If a where clause is passed only, a full string is constructed using the object mapper meta data.
     *
     * @param ObjectMapper $objectMapper
     * @param mixed $queryObject
     * @param array $additionalQueryArgs
     * @return array
     * @throws Exception\ORMColumnDoesNotExistException
     * @throws Exception\ORMNotEnoughQueryValuesException
     * @throws ORMFullQueryRequiredException
     * @throws UnsupportedEngineQueryException
     */
    public function query($objectMapper, $queryObject, $additionalQueryArgs = array()) {

        if (is_string($queryObject)) {
            $queryObject = new SQLQuery($queryObject, $additionalQueryArgs);
        }

        // If a SQL Query, expand out to a string
        if ($queryObject instanceof SQLQuery) {

            $staticTableInfo = ORMUtils::getStaticObjectTableInfo($objectMapper, $this->databaseConnection)["READ"];

            $queryObject = $queryObject->getExpandedQueryString($this->databaseConnection, $staticTableInfo);
            $queryObject = trim($queryObject);


            if (!is_numeric(strpos(strtoupper($queryObject), "SELECT")) || (strpos(strtoupper($queryObject), "SELECT") > 0)) {


                // Throw if no backing object to resolve against.
                if ($objectMapper->getOrmNoBackingObject()) throw new ORMFullQueryRequiredException($objectMapper->getClassName());

                $queryObject =
                    $this->dialect->generateAllColumnSelectClause($staticTableInfo) . " FROM " . $staticTableInfo->getTableMetaData()->getTableName() . " " . $queryObject;
            } else {
                $queryObject = str_replace("#VIEW", $staticTableInfo->getTableMetaData()->getTableName(), $queryObject);
            }


            // Execute the query
            $results = $this->databaseConnection->queryWithResults($queryObject);

            // Return the values in the appropriate format.
            $returnedValues = array();
            while ($row = $results->nextRow()) {

                $rowData = $this->mapRowColumnsToFields($row, $objectMapper);
                $returnedValues [] = $rowData;
            }


            return $returnedValues;

        } else {
            throw new UnsupportedEngineQueryException ($queryObject, $this->getIdentifier());
        }

    }


    /**
     * Perform a count of results for the supplied query using the mapper.
     *
     * @param ObjectMapper $objectMapper
     * @param $queryObject
     * @param array $additionalQueryArgs
     * @return mixed
     * @throws Exception\ORMColumnDoesNotExistException
     * @throws Exception\ORMNotEnoughQueryValuesException
     * @throws ORMFullQueryRequiredException
     */
    public function count($objectMapper, $queryObject, $additionalQueryArgs = array()) {

        if (is_string($queryObject)) {
            $queryObject = new SQLQuery($queryObject, $additionalQueryArgs);
        }


        // If a SQL Query, expand out to a string
        if ($queryObject instanceof SQLQuery) {

            $staticTableInfo = ORMUtils::getStaticObjectTableInfo($objectMapper, $this->databaseConnection)["READ"];

            $queryObject = $queryObject->getExpandedQueryString($this->databaseConnection, $staticTableInfo);

            $queryObject = trim($queryObject);

            $pkFields = $objectMapper->getPrimaryKeyFields();
            $fieldMappings = $staticTableInfo->getFieldColumnNameMappings();
            $pkColumns = array();
            foreach ($pkFields as $pkField) {
                $pkColumns[] = $fieldMappings[$pkField->getFieldName()];
            }

            // Generate a distinct clause in a dialect specific way.
            if ($pkColumns)
                $distinctClause = $this->dialect->generateDistinctClause($pkColumns);
            else
                $distinctClause = "*";

            if (!is_numeric(strpos(strtoupper($queryObject), "SELECT")) || (strpos(strtoupper($queryObject), "SELECT") > 0)) {

                // Throw if no backing object to resolve against.
                if ($objectMapper->getOrmNoBackingObject()) throw new ORMFullQueryRequiredException($objectMapper->getClassName());

                $queryObject =
                    "SELECT COUNT($distinctClause) FROM " . $staticTableInfo->getTableMetaData()->getTableName() . " " . $queryObject;
            } else {
                $queryObject = "SELECT COUNT($distinctClause) FROM (" . str_replace("#VIEW", $staticTableInfo->getTableMetaData()->getTableName(), $queryObject) . ") XXXX";
            }

        }

        return $this->databaseConnection->queryForSingleValue($queryObject);

    }


    /**
     * Save an object row
     *
     * @param ObjectMapper $objectMapper
     * @param array $insertValues
     * @param array $primaryKeyValues
     * @param array $persistableFieldValueMap
     */
    public function saveObjectData($objectMapper, $insertValues, $primaryKeyValues, $persistableFieldValueMap, $fullObject) {



        // Cache meta data for performance.
        if (!isset($this->cachedTableMetaData[$objectMapper->getClassName()])) {

            // Find the meta data for a mapper
            $tableMetaData = ORMUtils::getTableMetaDataForMapper($objectMapper, $this->databaseConnection);
            $this->cachedTableMetaData[$objectMapper->getClassName()] = $tableMetaData;
        }


        $tableMetaData = $this->cachedTableMetaData[$objectMapper->getClassName()];


        // Veto any save operation up front if we are trying to save to a mapper configured with ORMViewSQL
        if (!isset($tableMetaData["WRITE"])) {

            if ($objectMapper->getOrmAllowRelationshipPersistence()) {
                return $primaryKeyValues;
            }

            throw new ORMObjectNotWritableException ($objectMapper->getClassName());
        }


        $tableName = $tableMetaData["WRITE"]->getTableName();


        // Now convert the insert values into a column map
        $columnMap =
            ORMUtils::getFieldColumnValueMapForMapperTableAndValues($objectMapper, $tableMetaData, $insertValues, false);


        // Now loop through all insert values, inserting them into the table
        $insertColumns = array();
        $updateClauses = array();
        $pkColumns = array();
        $pkClauses = array();
        $updatePreparedStatement = new PreparedStatement ();
        $insertPreparedStatement = new PreparedStatement ();
        foreach ($columnMap as $fieldName => $columnMapEntry) {
            list ($field, $columnMetaData, $insertValue) = $columnMapEntry;
            $columnName = $columnMetaData->getName();

            // Add the insert value and column provided it isn't auto increment.
            if ((!$persistableFieldValueMap [$fieldName] [0]->getAutoIncrement()) && (!is_object($insertValue))) {
                $updatePreparedStatement->addBindParameter($columnMetaData->getType(), $insertValue);
                $updateClauses [] = $this->databaseConnection->escapeColumn($columnName) . " = ?";

                // If an insert value not null, add the column to the clause for insert.
                if ($insertValue !== null) {
                    $insertColumns [] = $this->databaseConnection->escapeColumn($columnName);
                    $insertPreparedStatement->addBindParameter($columnMetaData->getType(), $insertValue);
                }

            }

            if ($persistableFieldValueMap [$fieldName] [0]->getPrimaryKey()) {
                $escapedValue = $this->databaseConnection->escapeString($insertValue);
                $pkColumns [] = $this->databaseConnection->escapeColumn($columnName);
                $pkClauses [] =
                    $this->databaseConnection->escapeColumn($columnName) . "=" . $columnMetaData->getSQLValue($escapedValue);
            }

        }

        // Check now for existence of primary key.
        if ($primaryKeyValues) {
            $sql = "SELECT COUNT(*) FROM " . $tableName . " WHERE " . join(" AND ", $pkClauses);
            $newInsert = $this->databaseConnection->queryForSingleValue($sql) == 0;
        } else {
            $newInsert = true;
        }


        $preparedStatement = null;
        if ($newInsert) {
            if (sizeof($insertColumns) > 0) {
                $sql =
                    "INSERT INTO " . $tableName . "(" . join(",", $insertColumns) . ") VALUES (?" . str_repeat(",?", sizeof($insertColumns) - 1) . ")";
                $preparedStatement = $insertPreparedStatement;
            } else
                $this->databaseConnection->insertBlankRow($tableName);
        } else if (sizeof($updateClauses) > 0) {
            $sql = "UPDATE " . $tableName . " SET " . join(",", $updateClauses) . " WHERE " . join(" AND ", $pkClauses);
            $preparedStatement = $updatePreparedStatement;
        }

        // Execute the statement
        if ($preparedStatement) {
            $preparedStatement->setSQL($sql);
            $this->databaseConnection->executePreparedStatement($preparedStatement);
        }

        $primaryKeyValues =
            $primaryKeyValues ? $primaryKeyValues : $this->databaseConnection->getLastAutoIncrementId($tableName);

        // Return the primary key
        return $primaryKeyValues;

    }

    /**
     * Remove an object row.
     *
     * @param ObjectMapper $objectMapper
     * @param array $primaryKeyValues
     */
    public function removeObjectData($objectMapper, $primaryKeyValues, $persistableFieldValueMap) {

        if (!is_array($primaryKeyValues)) {
            $primaryKeyValues = explode("||", $primaryKeyValues);
        }


        $tableMetaData = ORMUtils::getStaticObjectTableInfo($objectMapper, $this->databaseConnection);

        // Veto any save operation up front if we are trying to save to a mapper configured with ORMViewSQL
        if (!isset($tableMetaData["WRITE"])) {

            if ($objectMapper->getOrmAllowRelationshipPersistence()) {
                return;
            } else {
                throw new ORMObjectNotWritableException ($objectMapper->getClassName());
            }

        }


        $staticTableMetaData = $tableMetaData["WRITE"];
        $fieldColumnMappings = $staticTableMetaData->getFieldColumnMappings();

        $preparedStatement = new PreparedStatement ();
        $clauses = array();
        $primaryKeyFields = $objectMapper->getPrimaryKeyFields();
        for ($i = 0; $i < sizeof($primaryKeyFields); $i++) {
            $field = $primaryKeyFields [$i];
            if (!$field->getReadOnly()) {
                $matchingColumn = $fieldColumnMappings [$field->getFieldName()];
                $preparedStatement->addBindParameter($matchingColumn->getType(), $primaryKeyValues [$i]);
                $clauses [] = $matchingColumn->getName() . "=?";
            }
        }

        $preparedStatement->setSQL("DELETE FROM " . $staticTableMetaData->getTableMetaData()->getTableName() . " WHERE " . join(" AND ", $clauses));

        $this->databaseConnection->executePreparedStatement($preparedStatement);

    }


    // Map a set of values keyed in by column name into another map, keyed in by field name.
    private function mapRowColumnsToFields($rowData, $objectMapper) {

        $className = $objectMapper->getClassName();
        if (!isset($this->cachedObjectColumnFieldMappings[$className])) {

            $columnFieldMap = array();

            $fieldsByName = $objectMapper->getFieldsByName();
            foreach ($fieldsByName as $fieldName => $field) {
                $ormColumn = $field->getOrmColumn();
                if ($ormColumn) {
                    $columnFieldMap[$ormColumn] = $fieldName;
                }
            }

            // Now catch any remaining fields
            foreach ($rowData as $column => $value) {

                if (!isset($columnFieldMap[$column])) {
                    $fieldName = preg_replace_callback("/_([a-z])/", function ($matches) {
                        return strtoupper($matches[1]);
                    }, $column);
                    $columnFieldMap [$column] = $fieldName;
                }
            }

            $this->cachedObjectColumnFieldMappings[$className] = $columnFieldMap;

        }


        $fieldMap = $this->cachedObjectColumnFieldMappings[$className];

        $returnedMap = array();
        foreach ($fieldMap as $column => $field) {
            $returnedMap[$field] = $rowData[$column];
        }


        return $returnedMap;

    }


}

?>