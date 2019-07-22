<?php


namespace Kinikit\Persistence\TableMapper\Relationship;

use Kinikit\Persistence\TableMapper\Mapper\TableMapper;

abstract class BaseTableRelationship implements TableRelationship {

    /**
     * @var TableMapper
     */
    protected $relatedTableMapper;


    /**
     * @var string
     */
    protected $mappedMember;

    /**
     * Construct parent with related table mapper and the mapped member
     * which will be added to the parent array containing the child data.
     *
     * BaseTableRelationship constructor.
     * @param $relatedTableMapper
     */
    public function __construct($relatedTableMapper, $mappedMember) {
        $this->relatedTableMapper = $relatedTableMapper;
        $this->mappedMember = $mappedMember;
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
    public function getMappedMember(){
        return $this->mappedMember;
    }


}
