<?php


namespace Kinikit\Persistence\TableMapper\Relationship;


use Kinikit\Persistence\TableMapper\Mapper\TableMapper;

interface TableRelationship {


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
     * @return mixed
     */
    public function getSelectJoinClause($parentAlias, $myAlias);


}
