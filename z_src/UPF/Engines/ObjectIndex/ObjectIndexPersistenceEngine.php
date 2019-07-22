<?php

namespace Kinikit\Persistence\UPF\Engines\ObjectIndex;

use Kinikit\Core\Exception\BadParameterException;
use Kinikit\Core\Exception\MethodNotImplementedException;
use Kinikit\Core\Object\SerialisableObject;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\Database\Connection\PreparedStatement;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Util\BatchingBulkRowInserter;
use Kinikit\Persistence\Database\Util\SequenceGenerator;
use Kinikit\Persistence\UPF\Framework\ObjectArrayFacade;
use Kinikit\Persistence\UPF\Framework\ObjectFacade;
use Kinikit\Persistence\UPF\Framework\ObjectMapper;
use Kinikit\Persistence\UPF\Framework\ObjectPersistenceEngine;

/**
 * Persistence engine implementation for object indexing.
 *
 * @author mark
 *
 */
class ObjectIndexPersistenceEngine extends ObjectPersistenceEngine {

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;

    /**
     * @var ObjectIndexSessionReferenceProvider
     */
    private $sessionReferenceProvider;

    private $indexRowInserter;
    private $historyRowInserter;
    private $sequenceGenerator;

    private $versioning = true;

    /**
     * Create the persistence engine
     *
     * @param DatabaseConnection $databaseConnection
     */
    public function __construct($databaseConnection = null, $identifier = null, $versioning = true, $sessionReferenceProvider = null) {

        $this->setDatabaseConnection($databaseConnection ? $databaseConnection : DefaultDB::instance());
        $this->versioning = $versioning;
        $this->sessionReferenceProvider = $sessionReferenceProvider;

        parent::__construct($identifier);

    }

    /**
     * Set the database connection
     *
     * @param DatabaseConnection $databaseConnection
     */
    public function setDatabaseConnection($databaseConnection) {
        $this->databaseConnection = $databaseConnection;
        $this->indexRowInserter = new BatchingBulkRowInserter ($this->databaseConnection, "kinikit_object_index", array("object_class", "object_pk", "field_name", "field_value", "value_class", "last_modified"), 500);
        $this->historyRowInserter = new BatchingBulkRowInserter ($this->databaseConnection, "kinikit_object_index_history", array("version_timestamp", "session_ref", "object_class", "object_pk", "field_name", "field_value", "value_class"), 500);
        $this->sequenceGenerator = new SequenceGenerator ($this->databaseConnection);
    }


    /**
     * Set the session reference provider for providing session references for historical logging.
     *
     * @param $sessionReferenceProvider
     */
    public function setSessionReferenceProvider($sessionReferenceProvider) {
        $this->sessionReferenceProvider = $sessionReferenceProvider;
    }

    /**
     * @return the $versioning
     */
    public function getVersioning() {
        return $this->versioning;
    }

    /**
     * @param $versioning the $versioning to set
     */
    public function setVersioning($versioning) {
        $this->versioning = $versioning;
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

        // Commit index batches.
        $this->indexRowInserter->commitBatch();
        $this->historyRowInserter->commitBatch();

        $this->databaseConnection->commit();
    }

    /**
     * @param unknown_type $objectMapper
     * @param unknown_type $objectPrimaryKey
     */
    public function getObjectDataByPrimaryKey($objectMapper, $objectPrimaryKey) {
        $matches = array_values($this->getMultipleObjectsDataByPrimaryKey($objectMapper, array($objectPrimaryKey)));

        if (sizeof($matches) > 0) {
            return $matches [0];
        } else {
            return null;
        }
    }

    /**
     * @param unknown_type $objectMapper
     * @param unknown_type $arrayOfPrimaryKeys
     */
    public function getMultipleObjectsDataByPrimaryKey($objectMapper, $arrayOfPrimaryKeys) {

        if (is_string($objectMapper)) {
            $objectClass = $objectMapper;
        } else {
            $objectClass = $objectMapper->getClassName();
        }

        $pkArray = array();
        foreach ($arrayOfPrimaryKeys as $pk) {
            $pkArray [] = $this->databaseConnection->escapeString($pk);
        }

        // Grab all relevant rows of interest
        $multiObjectResultSet = $this->databaseConnection->queryWithResults("SELECT * FROM kinikit_object_index WHERE object_class = '" . $this->databaseConnection->escapeString($objectClass) . "' AND object_pk IN ('" . join("','", $pkArray) . "')",);
        return $this->mapResultDataToRows($multiObjectResultSet);

    }

    /**
     * Get objects matching a set of field values.
     *
     * (non-PHPdoc)
     * @see ObjectPersistenceEngine::getObjectsForFieldValues()
     */
    public function getObjectsDataForFieldValues($objectMapper, $fieldValues, $orderingFields) {

        $whereClauses = array();
        foreach ($fieldValues as $key => $value) {
            $whereClauses [] = "(field_name = '" . $key . "' AND field_value = '" . $value . "')";
        }
        $relevantRows = $this->databaseConnection->queryWithResults("SELECT * FROM kinikit_object_index WHERE object_class = '" . $this->databaseConnection->escapeString($objectMapper->getClassName()) . "' AND " . join(" OR ", $whereClauses) . " ORDER BY object_pk",);

        $objectsArray = array();
        while ($row = $relevantRows->nextRow()) {
            $pk = $row ["object_pk"];

            if (!isset ($objectsArray [$pk])) {
                $objectsArray [$pk] = 1;
            } else {
                $objectsArray [$pk]++;
            }
        }

        // Now loop through each found object and check that we got a full match
        $pks = array();
        foreach ($objectsArray as $pk => $count) {
            if ($count == sizeof($fieldValues)) {
                $pks [] = $pk;
            }
        }

        // Return all applicable matches.
        return $this->getMultipleObjectsDataByPrimaryKey($objectMapper, $pks);

    }

    /**
     * Perform a simple object query against the index engine.
     *
     * @param ObjectMapper $objectMapper
     * @param mixed $queryObject
     * @param array $additionalQueryArgs
     * @return array
     * @throws BadParameterException
     */
    public function query($objectMapper, $queryObject, $additionalQueryArgs = array()) {


        if (is_string($objectMapper)) {
            $objectClass = $objectMapper;
        } else {
            $objectClass = $objectMapper->getClassName();
        }

        // If the query object is a string, construct an ObjectIndexQuery object from it
        if (is_string($queryObject)) {
            $queryObject = new ObjectIndexQuery($queryObject);
        }


        if (!$queryObject instanceof ObjectIndexQuery) {
            throw new BadParameterException("ObjectIndexPersistenceEngine::query", "queryObject", "badValue");
        }


        $logicClause = $queryObject->getLogicClause($objectClass);
        $results = $this->databaseConnection->queryWithResults("SELECT DISTINCT(object_pk) FROM kinikit_object_index WHERE object_class='" . $this->databaseConnection->escapeString($objectClass) . "' " . $logicClause,);

        $pks = array();
        while ($row = $results->nextRow()) {
            $pks[] = $row["object_pk"];
        }

        $results->close();

        return $this->getMultipleObjectsDataByPrimaryKey($objectMapper, $pks);


    }


    /**
     * Perform a count of results for the supplied query using the mapper.
     *
     * @param $objectMapper
     * @param $queryObject
     * @param array $additionalQueryArgs
     * @return mixed
     * @throws MethodNotImplementedException
     */
    public function count($objectMapper, $queryObject, $additionalQueryArgs = array()) {
        throw new MethodNotImplementedException("ObjectIndexPersistenceEngine", "count");
    }


    /**
     * Save a row to the index
     *
     * @param ObjectMapper $objectMapper
     * @param array $insertValues
     * @param array $primaryKeyValues
     * @param array $persistableFieldValueMap
     * @param SerialisableObject $fullObject
     */
    public function saveObjectData($objectMapper, $insertValues, $primaryKeyValues, $persistableFieldValueMap, $fullObject) {

        $objectClass = $objectMapper->getClassName();

        // If no primary key values, create an auto one.  Otherwise, remove existing data.
        if (!$primaryKeyValues) {
            $primaryKeyValues = $this->sequenceGenerator->incrementSequence("ObjectIndex:" . $objectClass);
        }


        // Get array of previous values for reference
        $previousResults = $this->databaseConnection->queryWithResults("SELECT * FROM kinikit_object_index WHERE object_class='" . $this->databaseConnection->escapeString($objectMapper->getClassName()) . "' AND object_pk='" . $primaryKeyValues . "'",);
        $previousValues = array();
        while ($row = $previousResults->nextRow()) {
            $previousValues[$row["field_name"]] = $row["field_value"];
        }


        $preparedStatement = new PreparedStatement ("DELETE FROM kinikit_object_index WHERE object_class=? AND object_pk=? AND field_name IN (?" . str_repeat(",?", sizeof($insertValues) - 1) . ")");
        $preparedStatement->addBindParameter(TableColumn::SQL_VARCHAR, $objectMapper->getClassName());
        $preparedStatement->addBindParameter(TableColumn::SQL_VARCHAR, $primaryKeyValues);
        foreach (array_keys($insertValues) as $fieldKey) {
            $preparedStatement->addBindParameter(TableColumn::SQL_VARCHAR, $fieldKey);
        }

        $this->databaseConnection->createPreparedStatement($preparedStatement);


        // Grab the session reference if one is provided.
        $sessionRef = null;
        if ($this->sessionReferenceProvider) {
            $sessionRef = $this->sessionReferenceProvider->getSessionRef();
        }

        $versionTimestamp = date('Y-m-d H:i:s');


        // Loop through the values in the persistable field value map, and write rows for each.
        foreach ($insertValues as $fieldName => $insertValue) {
            $fieldEntries = $persistableFieldValueMap [$fieldName];
            $field = $fieldEntries [0];

            // If auto increment primary key not set, synchronise with generated value.
            if ($insertValue == null && $field->getPrimaryKey() && $field->getAutoIncrement()) {
                $insertValue = $primaryKeyValues;
            }


            if (is_object($insertValue) || is_array($insertValue)) {
                continue;
            } else {
                $valueClass = "PRIMITIVE";
            }


            // Insert the index row.
            $this->indexRowInserter->addRow(array($objectClass, $primaryKeyValues, $fieldName, $insertValue, $valueClass, $versionTimestamp));

            $previousValue = isset($previousValues[$fieldName]) ? $previousValues[$fieldName] : null;
            if ($this->getVersioning() && !($objectMapper->getVersioning() === false) &&
                (!isset($previousValues[$fieldName]) || ($previousValue != $insertValue ? $insertValue : null))
            ) {
                $this->historyRowInserter->addRow(array($versionTimestamp, $sessionRef, $objectClass, $primaryKeyValues, $fieldName, $insertValue, $valueClass));
            }


        }

        return $primaryKeyValues;

    }

    /**
     * Remove all data for a particular object identified by type and primary key
     *
     * @param ObjectMapper $objectMapper
     * @param array $primaryKeyValues
     */
    public function removeObjectData($objectMapper, $primaryKeyValues, $persistableFieldValueMap) {
        $removeStatement = new PreparedStatement ("DELETE FROM kinikit_object_index WHERE object_class = ? AND object_pk = ?");
        $removeStatement->addBindParameter(TableColumn::SQL_VARCHAR, $objectMapper->getClassName());
        $removeStatement->addBindParameter(TableColumn::SQL_VARCHAR, $primaryKeyValues);
        $this->databaseConnection->createPreparedStatement($removeStatement);
    }

    /**
     * Get all indexed object classes
     */
    public function getAllIndexedObjectClasses() {
        $distinctClassesQuery = "SELECT DISTINCT(object_class) FROM kinikit_object_index ORDER BY object_class";
        $results = $this->databaseConnection->queryWithResults($distinctClassesQuery,);
        $indexedObjectClasses = array();
        while ($row = $results->nextRow()) {
            $indexedObjectClasses[] = $row["object_class"];
        }
        $results->close();

        return $indexedObjectClasses;
    }


    /**
     * Get all fields for an indexed object class
     *
     * @param $string
     */
    public function getAllFieldsForIndexedObjectClass($objectClass) {
        $distinctFieldNameQuery = "SELECT DISTINCT(field_name) FROM kinikit_object_index WHERE object_class = '" . $this->databaseConnection->escapeString($objectClass) . "'";
        $results = $this->databaseConnection->queryWithResults($distinctFieldNameQuery,);
        $indexedObjectClasses = array();
        while ($row = $results->nextRow()) {
            $indexedObjectClasses[] = $row["field_name"];
        }
        $results->close();

        return $indexedObjectClasses;
    }


    /**
     * @param $resultSet
     * @return array
     */
    private function mapResultDataToRows($resultSet) {
        $returnedArray = array();
        while ($row = $resultSet->nextRow()) {
            $pk = $row ["object_pk"];
            $fieldName = $row ["field_name"];
            $fieldValue = $row ["field_value"];
            $valueClass = $row ["value_class"];

            if (!isset ($returnedArray [$pk])) {
                $returnedArray [$pk] = array("objectIndexLastModified" => $row["last_modified"]);

            }

            if ($valueClass [0] == "[") {
                $valueClass = substr($valueClass, 1, strlen($valueClass) - 2);
                if (!isset ($returnedArray [$pk] [$fieldName])) {
                    if ($valueClass == "PRIMITIVE") {
                        $returnedArray [$pk] [$fieldName] = array();
                    } else {
                        $returnedArray [$pk] [$fieldName] = new ObjectArrayFacade ();
                    }
                }

                if ($valueClass == "PRIMITIVE")
                    array_push($returnedArray [$pk] [$fieldName], $fieldValue);
                else
                    $returnedArray [$pk] [$fieldName]->addObjectFacade(new ObjectFacade ($valueClass, $fieldValue));

            } else if ($valueClass != "PRIMITIVE") {
                $returnedArray [$pk] [$fieldName] = new ObjectFacade ($valueClass, $fieldValue);
            } else {
                $returnedArray [$pk] [$fieldName] = $fieldValue;
            }
        }

        return $returnedArray;
    }


}

?>
