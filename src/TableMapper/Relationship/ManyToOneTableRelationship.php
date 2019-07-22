<?php


namespace Kinikit\Persistence\TableMapper\Relationship;


class ManyToOneTableRelationship extends BaseTableRelationship {

    private $parentJoinColumnNames;

    /**
     * Construct a one to one relationship
     *
     * OneToOneTableRelationship constructor.
     * @param $relatedTableMapper
     * @param $mappedMember
     * @param $parentJoinColumnName
     */
    public function __construct($relatedTableMapper, $mappedMember, $parentJoinColumnNames) {
        parent::__construct($relatedTableMapper, $mappedMember);

        // Ensure we have an array of the right length for parent join columns.
        if (!is_array($parentJoinColumnNames)) {
            $parentJoinColumnNames = [$parentJoinColumnNames];
        }

        $this->parentJoinColumnNames = $parentJoinColumnNames;
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
     * @return mixed
     */
    public function getSelectJoinClause($parentAlias, $myAlias) {
        $clause = "LEFT JOIN " . $this->relatedTableMapper->getTableName() . " $myAlias ON ";

        $onClauses = [];
        $relatedPk = $this->relatedTableMapper->getPrimaryKeyColumns();
        foreach ($this->parentJoinColumnNames as $index => $joinColumnName) {
            $onClauses[] = "$parentAlias.$joinColumnName = $myAlias.{$relatedPk[$index]}";
        }

        $clause .= join(" AND ", $onClauses);

        return $clause;


    }


}
