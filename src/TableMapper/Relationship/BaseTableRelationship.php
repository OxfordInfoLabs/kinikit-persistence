<?php


namespace Kinikit\Persistence\TableMapper\Relationship;

use Kinikit\Persistence\TableMapper\Mapper\TableMapper;
use Kinikit\Persistence\TableMapper\Mapper\TableMapping;
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
     * Construct parent with related table mapper and the mapped member
     * which will be added to the parent array containing the child data.
     *
     * BaseTableRelationship constructor.
     * @param TableMapping|string $relatedTableMapping
     * @param string $mappedMember
     */
    public function __construct($relatedTableMapping, $mappedMember) {
        if (is_string($relatedTableMapping))
            $relatedTableMapping = new TableMapping($relatedTableMapping);

        $this->relatedTableMapping = $relatedTableMapping;
        $this->mappedMember = $mappedMember;
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
     * Base implementation which calls the do function below
     *
     * @param string $saveType
     * @param TableRelationshipSaveData $relationshipData
     * @return mixed|void
     */
    public function preParentSaveOperation($saveType, $relationshipData) {

    }

    /**
     * Base implementation which calls the do function below
     *
     * @param string $saveType
     * @param TableRelationshipSaveData $relationshipData
     * @return mixed|void
     */
    public function postParentSaveOperation($saveType, $relationshipData) {

    }


    /**
     * Perform save operation on child
     *
     * @param $saveType
     * @param $rowData
     */
    protected function performSaveOperationOnChild($saveType, $rowData) {

        switch ($saveType) {
            case TableMapper::SAVE_OPERATION_INSERT:
                $this->relatedTableMapping->insert($rowData);
                break;

        }

    }

}
