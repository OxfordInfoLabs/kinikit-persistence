<?php


namespace Kinikit\Persistence\TableMapper\Relationship;


class ManyToOneTableRelationship extends BaseTableRelationship {

    private $parentJoinColumnNames;

    /**
     * Construct a one to one relationship
     *
     * OneToOneTableRelationship constructor.
     * @param $relatedTableMapping
     * @param $mappedMember
     * @param $parentJoinColumnName
     */
    public function __construct($relatedTableMapping, $mappedMember, $parentJoinColumnNames, $saveCascade = false, $deleteCascade = false) {
        parent::__construct($relatedTableMapping, $mappedMember, $saveCascade, $deleteCascade);

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
     * @param $parentTableName
     * @param $parentPrimaryKeyColumns
     * @return mixed
     */
    public function getSelectJoinClause($parentAlias, $myAlias) {
        $clause = "LEFT JOIN " . $this->relatedTableMapping->getTableName() . " $myAlias ON ";

        $onClauses = [];
        $relatedPk = $this->relatedTableMapping->getPrimaryKeyColumnNames();
        foreach ($this->parentJoinColumnNames as $index => $joinColumnName) {
            $onClauses[] = "$parentAlias.$joinColumnName = $myAlias.{$relatedPk[$index]}";
        }

        $clause .= join(" AND ", $onClauses);

        return $clause;


    }

    /**
     * Retrieve child data for parent rows.
     *
     * @param array $parentRows
     * @return array|void
     */
    public function retrieveChildData(&$parentRows) {
        // TODO: Implement retrieveChildData() method.
    }


    public function preParentSaveOperation($saveType, &$relationshipData) {

        // Save the child first
        $this->performSaveOperationOnChild($saveType, $relationshipData);

        // Synchronise parent columns.
        $this->synchroniseParentFieldsFromChild($this->relatedTableMapping->getPrimaryKeyColumnNames(), $this->parentJoinColumnNames, $relationshipData);
    }


    /**
     * Unrelate children
     *
     * @param array $parentRows
     * @param null $childRows
     */
    public function unrelateChildren($parentRows, $childRows = null) {

        $childPKColumns = $this->relatedTableMapping->getPrimaryKeyColumnNames();

        $childPks = [];
        foreach ($parentRows as $parentRow) {
            $childPk = [];
            foreach ($this->parentJoinColumnNames as $index => $parentJoinColumnName) {
                $childPk[$childPKColumns[$index]] = $parentRow[$parentJoinColumnName];
            }
            $childPks[] = $childPk;
        }

        if ($this->deleteCascade) {
            $this->tablePersistenceEngine->deleteRows($this->relatedTableMapping, $childPks);
        }

    }

}
