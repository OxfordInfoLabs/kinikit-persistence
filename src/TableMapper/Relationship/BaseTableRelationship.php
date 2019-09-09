<?php


namespace Kinikit\Persistence\TableMapper\Relationship;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\TableMapper\Mapper\TableMapper;
use Kinikit\Persistence\TableMapper\Mapper\TableMapping;
use Kinikit\Persistence\TableMapper\Mapper\TablePersistenceEngine;
use Kinikit\Persistence\TableMapper\Mapper\TableQueryEngine;
use Kinikit\Persistence\TableMapper\Mapper\TableRelationshipSaveData;

abstract class BaseTableRelationship implements TableRelationship {

    /**
     * @var TableMapping
     */
    protected $relatedTableMapping;


    /**
     * @var TableMapping
     */
    protected $parentMapping;


    /**
     * @var string
     */
    protected $mappedMember;


    /**
     * Save cascade - if set to true, child related elements will be
     * saved when the parent is saved.  Otherwise they will simply be
     * associated if required.
     *
     * @var boolean
     */
    protected $saveCascade;

    /**
     * Delete cascade - if set to true, child related elements will
     * also be deleted when the parent is deleted.  Otherwise they will
     * simply be disassociated if required.
     *
     * @var boolean
     */
    protected $deleteCascade;

    /**
     * @var TableQueryEngine
     */
    protected $tableQueryEngine;


    /**
     * @var TablePersistenceEngine
     */
    protected $tablePersistenceEngine;

    /**
     * Construct parent with related table mapper and the mapped member
     *
     * BaseTableRelationship constructor.
     * @param TableMapping|string $relatedTableMapping
     * @param string $mappedMember
     */
    public function __construct($relatedTableMapping, $mappedMember, $saveCascade = true, $deleteCascade = false) {
        if (is_string($relatedTableMapping))
            $relatedTableMapping = new TableMapping($relatedTableMapping);

        $this->relatedTableMapping = $relatedTableMapping;
        $this->mappedMember = $mappedMember;

        $this->saveCascade = $saveCascade;
        $this->deleteCascade = $deleteCascade;
        $this->tablePersistenceEngine = new TablePersistenceEngine();
        $this->tableQueryEngine = new TableQueryEngine();


    }

    /**
     * Set parent mapper
     *
     * @param TableMapping $parentMapping
     * @return mixed|void
     */
    public function setParentMapping($parentMapping) {
        $this->parentMapping = $parentMapping;
    }


    /**
     * Get the related table mapper in use.
     *
     * @return TableMapping
     */
    public function getRelatedTableMapping() {
        return $this->relatedTableMapping;
    }

    /**
     * @return string
     */
    public function getMappedMember() {
        return $this->mappedMember;
    }

    /**
     * @return boolean
     */
    public function isSaveCascade() {
        return $this->saveCascade;
    }

    /**
     * @return bool
     */
    public function isDeleteCascade() {
        return $this->deleteCascade;
    }


    /**
     * Base implementation which calls the do function below
     *
     * @param string $saveType
     * @param TableRelationshipSaveData $relationshipData
     * @return mixed|void
     */
    public function preParentSaveOperation($saveType, &$relationshipData) {

    }

    /**
     * Base implementation which calls the do function below
     *
     * @param string $saveType
     * @param array $relationshipData
     * @return mixed|void
     */
    public function postParentSaveOperation($saveType, &$relationshipData) {

    }


    /**
     * Query for child data using a query string
     *
     * @param $queryString
     */
    protected function queryForChildData($queryString, $placeholderValues) {
        $data = $this->tableQueryEngine->query($this->relatedTableMapping, $queryString, $placeholderValues);
        return array_values($data);
    }


    /**
     * Synchronise parent fields from a child field
     *
     * @param $itemKey
     * @param $itemValue
     */
    protected function synchroniseParentFieldsFromChild($childFields, $parentFields, &$relationshipData) {

        // Synchronise child fields for all parent items.
        foreach ($relationshipData["relatedItemsByParent"] as $index => $relatedItems) {
            if (sizeof($relatedItems["items"]) > 0) {
                foreach ($relatedItems["items"] as $itemIndex => $item) {
                    foreach ($parentFields as $fieldIndex => $parentField) {
                        $childField = $childFields[$fieldIndex];
                        $relationshipData["relatedItemsByParent"][$index]["parentRow"][$parentField] = $item[$childField];
                    }
                }
            } else {
                foreach ($parentFields as $fieldIndex => $parentField) {
                    $relationshipData["relatedItemsByParent"][$index]["parentRow"][$parentField] = null;
                }
            }
        }

    }


    /**
     * Synchronise child fields from a parent field
     *
     * @param $parentFields
     * @param $childFields
     */
    protected function synchroniseChildFieldsFromParent($parentFields, $childFields, &$relationshipData) {

        // Synchronise child fields for all parent items.
        foreach ($relationshipData["relatedItemsByParent"] as $index => $relatedItems) {
            $parentRow = $relatedItems["parentRow"];
            foreach ($relatedItems["items"] as $itemIndex => $item) {
                foreach ($parentFields as $fieldIndex => $parentField) {
                    $childField = $childFields[$fieldIndex];
                    $relationshipData["relatedItemsByParent"][$index]["items"][$itemIndex][$childField] = $parentRow[$parentField];
                }
            }
        }
    }

    /**
     * Perform save operation on child
     *
     * @param $saveType
     * @param $rowData
     */
    protected function performSaveOperationOnChildren($saveType, &$relationshipData) {

        // If saving, ensure that we also clean up any no longer required items.
        if ($saveType == TablePersistenceEngine::SAVE_OPERATION_SAVE) {

            $removeObjects = $relationshipData["removeObjects"] ?? [];

            if ($removeObjects) {
                $this->unrelateChildren($removeObjects);
            }
        }


        // Get the global persistence engine instance and save the children if they exist
        if (sizeof($relationshipData["allRelatedItems"]))
            $this->tablePersistenceEngine->__saveRows($this->relatedTableMapping, $relationshipData["allRelatedItems"], $saveType);

    }

}
