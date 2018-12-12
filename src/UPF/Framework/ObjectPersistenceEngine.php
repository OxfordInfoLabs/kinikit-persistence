<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\SerialisableObject;

/**
 * Base class for any persistence engine implementation.  This defines the core criteria and logic for a persistence engine,
 * in particular implementing the functionality to decide whether or not this engine is persisting for a given passed in mapper
 * by checking the enabledEngines or disabledEngines properties on the mapper.
 *
 * This class also defines the core basic API which must be implemented by a child engine.
 *
 * @author mark
 *
 */
abstract class ObjectPersistenceEngine extends SerialisableObject {

    private $identifier;
    private $ignoreFailures;

    /**
     * Persistence engine constructed with an optional identifier.
     *
     * @param string $identifier
     */
    public function __construct($identifier = null, $ignoreFailures = false) {
        $this->identifier = $identifier;
        $this->ignoreFailures = $ignoreFailures;
    }

    /**
     * @return the $identifier
     */
    public function getIdentifier() {
        return $this->identifier;
    }

    /**
     * @param $identifier the $identifier to set
     */
    public function setIdentifier($identifier) {
        $this->identifier = $identifier;
    }

    /**
     * @return boolean
     */
    public function getIgnoreFailures() {
        return $this->ignoreFailures;
    }

    /**
     * @param boolean $ignoreFailures
     */
    public function setIgnoreFailures($ignoreFailures) {
        $this->ignoreFailures = $ignoreFailures;
    }


    /**
     * Hook method which is called by the persistence coordinator when a persistence transaction (Object Save, Object Delete)
     * occurs.  This is implemented to perform Engine specific transaction behaviour (i.e. start a DB transaction)
     * which can then be committed or rolled back using the other persistence hooks.
     *
     */
    public abstract function persistenceTransactionStarted();

    /**
     * Hook method, called by the persistence coordinator when a persistence transaction is successful.  This would normally
     * perform an operation such as a DB commit.
     *
     */
    public abstract function persistenceTransactionSucceeded();

    /**
     * Hook method, called by the persistence coordinator when a persistence transaction fails.  This would normally perform a
     * rollback operation on a DB or similar.
     *
     */
    public abstract function persistenceTransactionFailed();

    /**
     * Get some object data by primary key.  This should return an associative array of field values
     * keyed in by field name.
     *
     * @param ObjectMapper $objectMapper
     * @param mixed $objectPrimaryKey
     *
     * @return array
     */
    public abstract function getObjectDataByPrimaryKey($objectMapper, $objectPrimaryKey);

    /**
     * Get an array of objects data by primary key.  This should return an array of associative arrays of
     * field values each representing a returned object where the main array is keyed in by primary key.
     *
     * @param ObjectMapper $objectMapper
     * @param mixed $arrayOfPrimaryKeys
     *
     * @return array
     */
    public abstract function getMultipleObjectsDataByPrimaryKey($objectMapper, $arrayOfPrimaryKeys);

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
    public abstract function getObjectsDataForFieldValues($objectMapper, $fieldValues, $orderingFields);

    /**
     * Perform an engine specific query using an engine relevant query object.  This object could be a string if the engine expects a SQL query
     * or a compound descriptor object for querying other types of engine accordingly.
     *
     * @param ObjectMapper $objectMapper
     * @param mixed $queryObject
     * @param array $additionalQueryArgs
     * @return
     */
    public abstract function query($objectMapper, $queryObject, $additionalQueryArgs = array());


    /**
     * Perform a count of results for the supplied query using the mapper.
     *
     * @param $objectMapper
     * @param $queryObject
     * @param array $additionalQueryArgs
     * @return mixed
     */
    public abstract function count($objectMapper, $queryObject, $additionalQueryArgs = array());


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
    public abstract function saveObjectData($objectMapper, $insertValues, $primaryKeyValues, $persistableFieldValueMap, $fullObject);

    /**
     * Remove object data for the passed primary key values using the object mapper as the
     * reference for any required meta data.
     *
     * @param ObjectMapper $objectMapper
     * @param array $primaryKeyValues
     */
    public abstract function removeObjectData($objectMapper, $primaryKeyValues, $persistableFieldValueMap);



}

?>