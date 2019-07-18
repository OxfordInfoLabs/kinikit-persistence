<?php

namespace Kinikit\Persistence\UPF\Framework;


class TestBrokenPersistenceEngine extends ObjectPersistenceEngine {


    /**
     * Hook method which is called by the persistence coordinator when a persistence transaction (Object Save, Object Delete)
     * occurs.  This is implemented to perform Engine specific transaction behaviour (i.e. start a DB transaction)
     * which can then be committed or rolled back using the other persistence hooks.
     *
     */
    public function persistenceTransactionStarted() {
        // TODO: Implement persistenceTransactionStarted() method.
    }

    /**
     * Hook method, called by the persistence coordinator when a persistence transaction is successful.  This would normally
     * perform an operation such as a DB commit.
     *
     */
    public function persistenceTransactionSucceeded() {
        // TODO: Implement persistenceTransactionSucceeded() method.
    }

    /**
     * Hook method, called by the persistence coordinator when a persistence transaction fails.  This would normally perform a
     * rollback operation on a DB or similar.
     *
     */
    public function persistenceTransactionFailed() {
        // TODO: Implement persistenceTransactionFailed() method.
    }

    /**
     * Get some object data by primary key.  This should return an associative array of field values
     * keyed in by field name.
     *
     * @param ObjectMapper $objectMapper
     * @param mixed $objectPrimaryKey
     *
     * @return array
     */
    public function getObjectDataByPrimaryKey($objectMapper, $objectPrimaryKey) {
        // TODO: Implement getObjectDataByPrimaryKey() method.
    }

    /**
     * Get an array of objects data by primary key.  This should return an array of associative arrays of
     * field values each representing a returned object where the main array is keyed in by primary key.
     *
     * @param ObjectMapper $objectMapper
     * @param mixed $arrayOfPrimaryKeys
     *
     * @return array
     */
    public function getMultipleObjectsDataByPrimaryKey($objectMapper, $arrayOfPrimaryKeys) {
        // TODO: Implement getMultipleObjectsDataByPrimaryKey() method.
    }

    /**
     * Get an array of objects matching a set of field values.  The field values should be an associative
     * array of values keyed in by field names which are treated like additive filters to a query.
     * The ordering fields are of tupe ObjectOrderingField encapsulating a field and direction which
     * should be applied as a sort after the filtering.
     *
     * @param ObjectMapper $objectMapper
     * @param array $fieldValue
     * @param array $orderingFields
     */
    public function getObjectsDataForFieldValues($objectMapper, $fieldValues, $orderingFields) {
        // TODO: Implement getObjectsDataForFieldValues() method.
    }

    /**
     * Perform an engine specific query using an engine relevant query object.  This object could be a string if the engine expects a SQL query
     * or a compound descriptor object for querying other types of engine accordingly.
     *
     * @param ObjectMapper $objectMapper
     * @param mixed $queryObject
     * @param array $additionalQueryArgs
     * @throws Exception
     */
    public function query($objectMapper, $queryObject, $additionalQueryArgs = array()) {
        throw new Exception("Cannot query");
    }

    /**
     * Save object data.  The first argument is the mapper from which any required meta data can be looked up
     * for persistence.  The second argument is the rational array of insert values keyed in by member name with any object / array
     * values converted into primary key values / array of pk values.  Object values can be ignored if they are being
     * related later on in the relateChildObjects method.
     *
     * @param  ObjectMapper $objectMapper
     * @param array $insertValues
     * @param string $primaryKeyValues
     * @param array $persistableFieldValueMap
     * @param object $fullObject
     *
     * @return mixed - The primary key for the object, as either a string or an array.
     */
    public function saveObjectData($objectMapper, $insertValues, $primaryKeyValues, $persistableFieldValueMap, $fullObject) {
        throw new Exception("Cannot save");
    }

    /**
     * Remove object data for the passed primary key values using the object mapper as the
     * reference for any required meta data.
     *
     * @param ObjectMapper $objectMapper
     * @param array $primaryKeyValues
     */
    public function removeObjectData($objectMapper, $primaryKeyValues, $persistableFieldValueMap) {
        throw new Exception("Cannot remove");
    }

    /**
     * Perform a count of results for the supplied query using the mapper.
     *
     * @param $objectMapper
     * @param $queryObject
     * @param array $additionalQueryArgs
     * @return mixed
     */
    public function count($objectMapper, $queryObject, $additionalQueryArgs = array()) {
        // TODO: Implement count() method.
    }
}