<?php

namespace Kinikit\Persistence\UPF\Framework;

class TestPersistenceEngine extends ObjectPersistenceEngine {

    public static $returnMap = array();
    public static $relatedMap = array();
    public static $relatedPksMap = array();

    public static $storedValues = array();
    public static $removedValues = array();
    public static $relatedObjectMap = array();

    public static $dataForFieldValues = array();

    public static $incrementId = 0;
    public static $writes = 0;

    public $transactionStarted = 0;
    public $transactionFailed = 0;
    public $transactionSucceeded = 0;
    private $forceFailure = false;
    private $createTransactionFailures = false;

    public $lastQueryObject;
    public $queryResults;

    public function __construct($identifier = null, $forceFailure = false, $ignoreFailures = false, $createTransactionFailures = false) {
        parent::__construct($identifier, $ignoreFailures);
        $this->forceFailure = $forceFailure;
        $this->createTransactionFailures = $createTransactionFailures;
    }

    /**
     *
     */
    public function persistenceTransactionFailed() {

        $this->transactionFailed++;

        if ($this->createTransactionFailures) {
            throw new Exception ("Forcing a failure");
        }


    }

    /**
     *
     */
    public function persistenceTransactionStarted() {
        $this->transactionStarted++;

        if ($this->createTransactionFailures) {
            throw new Exception ("Forcing a failure");
        }
    }

    /**
     *
     */
    public function persistenceTransactionSucceeded() {
        $this->transactionSucceeded++;

        if ($this->createTransactionFailures) {
            throw new Exception ("Forcing a failure");
        }
    }

    /**
     * @param unknown_type $objectClass
     * @param unknown_type $objectPrimaryKey
     */
    public function getObjectDataByPrimaryKey($objectMapper, $objectPrimaryKey) {

        return isset (TestPersistenceEngine::$returnMap [$objectPrimaryKey]) ? TestPersistenceEngine::$returnMap [$objectPrimaryKey] : null;
    }

    /**
     * @param unknown_type $objectClass
     * @param unknown_type $arrayOfPrimaryKeys
     */
    public function getMultipleObjectsDataByPrimaryKey($objectMapper, $arrayOfPrimaryKeys) {
        $results = array();
        $objectClass = $objectMapper->getObjectClass();
        foreach ($arrayOfPrimaryKeys as $key) {

            $value = $this->getObjectDataByPrimaryKey($objectClass, $key);
            if ($value) $results [$key] = $value;
        }
        return $results;
    }

    // Get objects for field values.
    public function getObjectsDataForFieldValues($objectMapper, $fieldValues, $orderingFields) {
        return isset(TestPersistenceEngine::$dataForFieldValues [$objectMapper->getClassName()] [join("||", $fieldValues)]) ? TestPersistenceEngine::$dataForFieldValues [$objectMapper->getClassName()] [join("||", $fieldValues)] : null;
    }

    /**
     * @param unknown_type $relatedObjectMapper
     * @param unknown_type $parentFieldRelationship
     * @param unknown_type $parentForeignKey
     * @param unknown_type $returnPKsOnly
     */
    public function getRelatedObjectsDataByParentForeignKey($parentObjectMapper, $relatedObjectMapper, $parentFieldRelationship, $parentForeignKey, $returnPKsOnly) {
        if ($returnPKsOnly) {
            return isset (TestPersistenceEngine::$relatedPksMap [$parentObjectMapper->getClassName()] [$relatedObjectMapper->getClassName()] [$parentForeignKey]) ? TestPersistenceEngine::$relatedPksMap [$parentObjectMapper->getClassName()] [$relatedObjectMapper->getClassName()] [$parentForeignKey] : null;
        } else {
            return isset (TestPersistenceEngine::$relatedMap [$parentObjectMapper->getClassName()] [$relatedObjectMapper->getClassName()] [$parentForeignKey]) ? TestPersistenceEngine::$relatedMap [$parentObjectMapper->getClassName()] [$relatedObjectMapper->getClassName()] [$parentForeignKey] : null;

        }

    }

    /**
     * @param ObjectMapper $objectMapper
     * @param mixed $queryObject
     * @param array $additionalQueryArgs
     * @return
     */
    public function query($objectMapper, $queryObject, $additionalQueryArgs = array()) {
        $this->lastQueryObject = array($objectMapper->getClassName(), $queryObject);

        return $this->queryResults;
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

    }


    /**
     * @param unknown_type $objectMapper
     * @param unknown_type $insertValues
     * @param unknown_type $primaryKeyValues
     * @param unknown_type $persistableFieldValueMap
     */

    public function saveObjectData($objectMapper, $insertValues, $primaryKeyValues, $persistableFieldValueMap, $fullObject) {

        if ($this->forceFailure) {
            throw new \Exception ("Forcing a failure");
        }

        if (!isset (TestPersistenceEngine::$storedValues [$objectMapper->getClassName()])) {
            TestPersistenceEngine::$storedValues [$objectMapper->getClassName()] = array();
        }

        if (!$primaryKeyValues) {
            $primaryKeyValues = ++TestPersistenceEngine::$incrementId;
        }

        TestPersistenceEngine::$storedValues [$objectMapper->getClassName()] [$primaryKeyValues] = $insertValues;

        TestPersistenceEngine::$writes++;

        return sizeof($objectMapper->getPrimaryKeyFields()) > 0 ? $primaryKeyValues : null;
    }

    /**
     * @param unknown_type $parentMapper
     * @param unknown_type $childMapper
     * @param unknown_type $parentFieldRelationship
     * @param unknown_type $parentPrimaryKey
     * @param unknown_type $childPrimaryKey
     */
    public function relateChildObjects($parentMapper, $childMapper, $parentFieldRelationship, $parentPrimaryKey, $childPrimaryKeys) {
        if ($parentPrimaryKey == null) {
            $parentPrimaryKey = "NONE";
        }

        if (!isset (TestPersistenceEngine::$relatedObjectMap [$parentPrimaryKey])) {
            TestPersistenceEngine::$relatedObjectMap [$parentPrimaryKey] = array();
        }
        foreach ($childPrimaryKeys as $childPrimaryKey) {
            TestPersistenceEngine::$relatedObjectMap [$parentPrimaryKey] [$childPrimaryKey] = 1;
        }
    }

    /**
     * @param unknown_type $parentMapper
     * @param unknown_type $childMapper
     * @param unknown_type $parentFieldRelationship
     * @param unknown_type $parentPrimaryKey
     * @param unknown_type $childPrimaryKey
     */
    public function unrelateChildObjects($parentMapper, $childMapper, $parentFieldRelationship, $parentPrimaryKey, $childPrimaryKeys = null) {

        if ($childPrimaryKeys) {
            foreach ($childPrimaryKeys as $pk) {
                unset (TestPersistenceEngine::$relatedObjectMap [$parentPrimaryKey] [$pk]);
            }
        } else
            unset (TestPersistenceEngine::$relatedObjectMap [$parentPrimaryKey]);
    }

    /**
     * @param unknown_type $objectMapper
     * @param unknown_type $primaryKeyValues
     */
    public function removeObjectData($objectMapper, $primaryKeyValues, $persistableFieldValueMap) {
        if ($this->forceFailure) {
            throw new \Exception ("Forcing a failure");
        }

        if (!isset (TestPersistenceEngine::$removedValues [$objectMapper->getClassName()])) {
            TestPersistenceEngine::$removedValues [$objectMapper->getClassName()] = array();
        }

        TestPersistenceEngine::$removedValues [$objectMapper->getClassName()] [] = $primaryKeyValues;

    }

}

?>