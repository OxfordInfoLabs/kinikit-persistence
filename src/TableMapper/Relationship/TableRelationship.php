<?php


namespace Kinikit\Persistence\TableMapper\Relationship;

use Kinikit\Persistence\TableMapper\Mapper\TableMapping;
use Kinikit\Persistence\TableMapper\Mapper\TableRelationshipSaveData;

interface TableRelationship {


    /**
     * Set parent mapper on this relationship
     *
     * @param TableMapping $parentMapping
     * @return mixed
     */
    public function setParentMapping($parentMapping);

    /**
     * @return TableMapping
     */
    public function getRelatedTableMapping();

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
     * Get the select join clause for this relationship for use in the query engine.
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
     * Get child data using the parent row data as reference.
     *
     * @param array $parentRows
     * @return array
     */
    public function retrieveChildData(&$parentRows);


    /**
     * Pre parent save trigger - all data passed by reference to make it mutable.
     *
     * @param string $saveType
     * @param TableRelationshipSaveData $relationshipData
     * @return mixed
     */
    public function preParentSaveOperation($saveType, &$relationshipData);


    /**
     * Post parent save trigger - all data passed by reference to make it mutable.
     *
     * @param $saveType
     * @param TableRelationshipSaveData $relationshipData
     * @return mixed
     */
    public function postParentSaveOperation($saveType, &$relationshipData);


    /**
     * Unrelate children from parent.  If explicit set of child rows passed
     * these are unrelated otherwise it is assumed that all child rows from the
     * parent are to be processed.
     *
     * @param array $parentRows
     * @param array $childRows
     */
    public function unrelateChildren($parentRows, $childRows = null);

}
