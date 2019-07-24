<?php


namespace Kinikit\Persistence\TableMapper\Relationship;


use Kinikit\Persistence\TableMapper\Mapper\TableMapper;
use Kinikit\Persistence\TableMapper\Mapper\TableRelationshipSaveData;

interface TableRelationship {


    /**
     * Set parent mapper on this relationship
     *
     * @param TableMapper $parentMapper
     * @return mixed
     */
    public function setParentMapper($parentMapper);

    /**
     * @return TableMapper
     */
    public function getRelatedTableMapper();

    /**
     * @return string
     */
    public function getMappedMember();


    /**
     * Return a boolean indicating whether or not this
     * relationship expects an array or single object.
     *
     * @return boolean
     */
    public function isMultiple();


    /**
     * Get the select join clause for this relationship
     *
     * Pass the parent and my alias through
     *
     * @param string $parentAlias
     * @param string $myAlias
     *
     * @param $parentTableName
     * @param $parentPrimaryKeyColumns
     * @return mixed
     */
    public function getSelectJoinClause($parentAlias, $myAlias);


    /**
     * Pre parent save trigger - all data passed by reference to make it mutable.
     *
     * @param string $saveType
     * @param TableRelationshipSaveData $relationshipData
     * @return mixed
     */
    public function preParentSaveOperation($saveType, $relationshipData);


    /**
     * Post parent save trigger - all data passed by reference to make it mutable.
     *
     * @param $saveType
     * @param TableRelationshipSaveData $relationshipData
     * @return mixed
     */
    public function postParentSaveOperation($saveType, $relationshipData);

}
