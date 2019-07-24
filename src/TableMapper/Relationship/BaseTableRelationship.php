<?php


namespace Kinikit\Persistence\TableMapper\Relationship;

use Kinikit\Persistence\TableMapper\Mapper\TableMapper;
use Kinikit\Persistence\TableMapper\Mapper\TableRelationshipSaveData;

abstract class BaseTableRelationship implements TableRelationship {

    /**
     * @var TableMapper
     */
    protected $relatedTableMapper;


    /**
     * @var TableMapper
     */
    protected $parentMapper;


    /**
     * @var string
     */
    protected $mappedMember;

    /**
     * Construct parent with related table mapper and the mapped member
     * which will be added to the parent array containing the child data.
     *
     * BaseTableRelationship constructor.
     * @param TableMapper|string $relatedTableMapper
     * @param string $mappedMember
     */
    public function __construct($relatedTableMapper, $mappedMember) {
        if (is_string($relatedTableMapper))
            $relatedTableMapper = new TableMapper($relatedTableMapper);

        $this->relatedTableMapper = $relatedTableMapper;
        $this->mappedMember = $mappedMember;
    }

    /**
     * Set parent mapper
     *
     * @param TableMapper $parentMapper
     * @return mixed|void
     */
    public function setParentMapper($parentMapper) {
        $this->parentMapper = $parentMapper;
    }


    /**
     * Get the related table mapper in use.
     *
     * @return TableMapper
     */
    public function getRelatedTableMapper() {
        return $this->relatedTableMapper;
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
                $this->relatedTableMapper->insert($rowData);
                break;

        }

    }

}
