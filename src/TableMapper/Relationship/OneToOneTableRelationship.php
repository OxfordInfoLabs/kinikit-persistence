<?php


namespace Kinikit\Persistence\TableMapper\Relationship;


use Kinikit\Persistence\TableMapper\Mapper\TableRelationshipSaveData;

class OneToOneTableRelationship extends BaseTableRelationship {

    /**
     * @var string[]
     */
    private $childJoinColumnNames;

    /**
     * Construct a one to one relationship
     *
     * OneToOneTableRelationship constructor.
     * @param $relatedTableMapper
     * @param $mappedMember
     * @param $parentJoinColumnName
     */
    public function __construct($relatedTableMapper, $mappedMember, $childJoinColumnNames) {
        parent::__construct($relatedTableMapper, $mappedMember);

        // Ensure we have an array of the right length for parent join columns.
        if (!is_array($childJoinColumnNames)) {
            $childJoinColumnNames = [$childJoinColumnNames];
        }

        $this->childJoinColumnNames = $childJoinColumnNames;
    }

    /**
     * Return a boolean indicating whether or not this
     * relationship expects an array or single object.
     *
     * @return boolean
     */
    public function isMultiple() {
        return false;
    }

    /**
     * Get the select join clause for this relationship
     *
     * @param string $parentAlias
     * @param string $myAlias
     * @param $parentTableName
     * @param $parentPrimaryKeyColumns
     * @return mixed
     */
    public function getSelectJoinClause($parentAlias, $myAlias) {

        $parentPrimaryKeyColumns = $this->parentMapper->getPrimaryKeyColumnNames();

        $clause = "LEFT JOIN " . $this->relatedTableMapper->getTableName() . " $myAlias ON ";

        $onClauses = [];
        foreach ($this->childJoinColumnNames as $index => $joinColumnName) {
            $onClauses[] = "$parentAlias.{$parentPrimaryKeyColumns[$index]} = $myAlias.$joinColumnName";
        }

        $clause .= join(" AND ", $onClauses);

        return $clause;


    }

    /**
     * Implement post action as one to one's should have parent id fields.
     *
     * @param string $saveType
     * @param TableRelationshipSaveData $relationshipData
     * @return mixed|void
     */
    public function postParentSaveOperation($saveType, $relationshipData) {

        // Synchronise the child fields from the parent
        $relationshipData->synchroniseChildFieldsFromParent($this->parentMapper->getPrimaryKeyColumnNames(), $this->childJoinColumnNames);

        // Perform a save operation using the child rows.
        $this->performSaveOperationOnChild($saveType, $relationshipData->getAllChildRows());

    }


}
