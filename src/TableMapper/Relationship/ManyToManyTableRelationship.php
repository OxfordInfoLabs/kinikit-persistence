<?php


namespace Kinikit\Persistence\TableMapper\Relationship;


class ManyToManyTableRelationship extends BaseTableRelationship {

    /**
     * @var string
     */
    private $linkTableName;

    /**
     * Construct a many to many relationship
     *
     * OneToOneTableRelationship constructor.
     *
     * @param $relatedTableMapping
     * @param $mappedMember
     * @param $parentJoinColumnName
     */
    public function __construct($relatedTableMapping, $mappedMember, $linkTableName) {
        parent::__construct($relatedTableMapping, $mappedMember);
        $this->linkTableName = $linkTableName;
    }

    /**
     * Return a boolean indicating whether or not this
     * relationship expects an array or single object.
     *
     * @return boolean
     */
    public function isMultiple() {
        return true;
    }

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
    public function getSelectJoinClause($parentAlias, $myAlias) {

        $parentPrimaryKeyColumns = $this->parentMapping->getPrimaryKeyColumnNames();
        $parentTableName = $this->parentMapping->getTableName();

        $linkAlias = $myAlias . "L";

        $parentJoinClauses = [];
        $childJoinClauses = [];
        foreach ($parentPrimaryKeyColumns as $parentPrimaryKeyColumn) {
            $parentJoinClauses[] = $parentAlias . "." . $parentPrimaryKeyColumn . " = " . $linkAlias . "." . $parentTableName . "_" . $parentPrimaryKeyColumn;
        }
        foreach ($this->getRelatedTableMapping()->getPrimaryKeyColumnNames() as $childPrimaryKeyColumn) {
            $childJoinClauses[] = $linkAlias . "." . $this->getRelatedTableMapping()->getTableName() . "_" . $childPrimaryKeyColumn . " = " . $myAlias . "." . $childPrimaryKeyColumn;
        }


        $joinClause = "LEFT JOIN {$this->linkTableName} $linkAlias ON " . join(" AND ", $parentJoinClauses);
        $joinClause .= " LEFT JOIN {$this->getRelatedTableMapping()->getTableName()} $myAlias ON " . join(" AND ", $childJoinClauses);

        return $joinClause;


    }

   
}
