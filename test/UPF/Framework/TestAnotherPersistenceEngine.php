<?php

namespace Kinikit\Persistence\UPF\Framework;

class TestAnotherPersistenceEngine extends ObjectPersistenceEngine {

    public static $returnMap = array();
    public static $relatedMap = array();
    public static $relatedPksMap = array();

    public static $storedValues = array();
    public static $removedValues = array();

    public static $incrementId = 0;
    public static $writes = 0;

    public $lastQueryObject = null;
    public $queryResults;

    public function __construct($identifier = null) {
        parent::__construct($identifier);
    }

    /**
     *
     */
    public function persistenceTransactionFailed() {
    }

    /**
     *
     */
    public function persistenceTransactionStarted() {
    }

    /**
     *
     */
    public function persistenceTransactionSucceeded() {
    }

    /**
     * @param unknown_type $objectClass
     * @param unknown_type $objectPrimaryKey
     */
    public function getObjectDataByPrimaryKey($objectMapper, $objectPrimaryKey) {

        return isset (TestAnotherPersistenceEngine::$returnMap [$objectPrimaryKey]) ? TestAnotherPersistenceEngine::$returnMap [$objectPrimaryKey] : null;
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
            if ($value)
                $results [$key] = $value;
        }
        return $results;
    }

    /**
     * @param unknown_type $relatedObjectMapper
     * @param unknown_type $parentFieldRelationship
     * @param unknown_type $parentForeignKey
     * @param unknown_type $returnPKsOnly
     */
    public function getRelatedObjectsDataByParentForeignKey($parentObjectMapper, $relatedObjectMapper, $parentFieldRelationship, $parentForeignKey, $returnPKsOnly) {
        $parentForeignKey = join("||", $parentForeignKey);
        if ($returnPKsOnly) {
            return isset (TestAnotherPersistenceEngine::$relatedPksMap [$parentObjectMapper->getClassName()] [$parentFieldRelationship->getRelatedClassName()] [$parentForeignKey]) ? TestAnotherPersistenceEngine::$relatedPksMap [$parentObjectMapper->getClassName()] [$parentFieldRelationship->getRelatedClassName()] [$parentForeignKey] : null;
        } else {
            return isset (TestAnotherPersistenceEngine::$relatedMap [$parentObjectMapper->getClassName()] [$parentFieldRelationship->getRelatedClassName()] [$parentForeignKey]) ? TestAnotherPersistenceEngine::$relatedMap [$parentObjectMapper->getClassName()] [$parentFieldRelationship->getRelatedClassName()] [$parentForeignKey] : null;

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
        if (!isset (TestAnotherPersistenceEngine::$storedValues [$objectMapper->getClassName()])) {
            TestAnotherPersistenceEngine::$storedValues [$objectMapper->getClassName()] = array();
        }

        if (!$primaryKeyValues) {
            $primaryKeyValues = ++TestAnotherPersistenceEngine::$incrementId;
        }

        TestAnotherPersistenceEngine::$storedValues [$objectMapper->getClassName()] [$primaryKeyValues] = $insertValues;

        TestAnotherPersistenceEngine::$writes++;

        return sizeof($objectMapper->getPrimaryKeyFields()) > 0 ? $primaryKeyValues : null;
    }

    /**
     * @param unknown_type $objectMapper
     * @param unknown_type $primaryKeyValues
     */
    public function removeObjectData($objectMapper, $primaryKeyValues, $persistableFieldValueMap) {
        if (!isset (TestAnotherPersistenceEngine::$removedValues [$objectMapper->getClassName()])) {
            TestAnotherPersistenceEngine::$removedValues [$objectMapper->getClassName()] = array();
        }

        TestAnotherPersistenceEngine::$removedValues [$objectMapper->getClassName()] [] = $primaryKeyValues;

    }

    /**
     * @param unknown_type unknown_type $parentMapper
     * @param unknown_type unknown_type $childMapper
     * @param unknown_type unknown_type $parentFieldRelationship
     * @param unknown_type unknown_type $parentPrimaryKey
     * @param unknown_type unknown_type $childPrimaryKey
     */
    public function relateChildObjects($parentMapper, $childMapper, $parentFieldRelationship, $parentPrimaryKey, $childPrimaryKeys) {

    }

    /**
     * @param unknown_type unknown_type $parentMapper
     * @param unknown_type unknown_type $childMapper
     * @param unknown_type unknown_type $parentFieldRelationship
     * @param unknown_type unknown_type $parentPrimaryKey
     * @param unknown_type unknown_type $childPrimaryKey
     */
    public function unrelateChildObjects($parentMapper, $childMapper, $parentFieldRelationship, $parentPrimaryKey, $childPrimaryKeys = null) {

    }

    /* (non-PHPdoc)
     * @see ObjectPersistenceEngine::getObjectsDataForFieldValues()
     */
    public function getObjectsDataForFieldValues($objectMapper, $fieldValues, $orderingFields) {
        // TODO Auto-generated method stub


    }

}

?>