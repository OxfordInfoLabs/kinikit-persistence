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
     * @return array|bool
     */
    public function getParentJoinColumnNames() {
        return $this->parentJoinColumnNames;
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

        // Calculate which rows we need to fetch based upon parent rows with missing data.
        $fetchChildren = [];
        $fetchParents = [];
        $parentQueryValues = [];
        $parentQueryClauses = [];
        $childQueryValues = [];
        $childQueryClauses = [];
        foreach ($parentRows as $index => $parentRow) {
            if (!isset($parentRow[$this->mappedMember])) {

                $joinValues = [];
                foreach ($this->parentJoinColumnNames as $columnName) {
                    if (isset($parentRow[$columnName])) {
                        $joinValues[] = $parentRow[$columnName];
                        $childQueryValues[] = $parentRow[$columnName];
                    }
                }

                // If we have the required data in the parent object but need to pull the child, add it to our list of clauses for pulling.
                if (sizeof($joinValues) == sizeof($this->parentJoinColumnNames)) {
                    $fetchChildren[join("||", $joinValues)] = $index;

                    $queryClause = [];
                    foreach ($this->relatedTableMapping->getPrimaryKeyColumnNames() as $childPkColumn) {
                        $queryClause[] = $childPkColumn . "=?";
                    }
                    $childQueryClauses[] = "(" . join(" AND ", $queryClause) . ")";

                    $parentRows[$index][$this->mappedMember] = [];
                } else {
                    $pks = $this->parentMapping->getPrimaryKeyValues($parentRow);

                    $queryClause = [];
                    foreach ($pks as $pkColumn => $pkValue) {
                        $queryClause[] = $pkColumn . "=?";
                        $parentQueryValues[] = $pkValue;
                    }
                    $parentQueryClauses[] = "(" . join(" AND ", $queryClause) . ")";

                    $fetchParents[join("||", $pks)] = $index;
                }

            }
        }

        if (sizeof($fetchParents) > 0) {
            $parents = $this->tableQueryEngine->query($this->parentMapping, "WHERE " . join(" OR ", $parentQueryClauses), $parentQueryValues);
            foreach ($parents as $key => $parent) {
                if (isset($fetchParents[$key])) {
                    foreach ($parent as $itemKey => $value) {
                        $parentRows[$fetchParents[$key]][$itemKey] = $value;
                    }
                }
            }
        }

        if (sizeof($fetchChildren) > 0) {
            $children = $this->tableQueryEngine->query($this->relatedTableMapping, "WHERE " . join(" OR ", $childQueryClauses), $childQueryValues);
            foreach ($children as $key => $child) {
                if (isset($fetchChildren[$key])) {
                    $parentRows[$fetchChildren[$key]][$this->mappedMember] = $child;
                }
            }
        }


    }


    public function preParentSaveOperation($saveType, &$relationshipData) {

        // Save the child first
        $this->performSaveOperationOnChildren($saveType, $relationshipData);

        // Synchronise parent columns.
        $this->synchroniseParentFieldsFromChild($this->relatedTableMapping->getPrimaryKeyColumnNames(), $this->parentJoinColumnNames, $relationshipData);
    }


    /**
     * Unrelate children
     *
     * @param array $parentRows
     * @param null $childRows
     */
    public function unrelateChildren($parentRows) {

        $rowsToDelete = [];

        // Retrieve and fill in child data for each parent row.
        $this->retrieveChildData($parentRows);

        foreach ($parentRows as $parentRow) {
            if (isset($parentRow[$this->mappedMember]) && is_array($parentRow[$this->mappedMember]) && sizeof($parentRow[$this->mappedMember])) {
                $children = isset($parentRow[$this->mappedMember][0]) ? $parentRow[$this->mappedMember] : [$parentRow[$this->mappedMember]];
                $rowsToDelete = array_merge($rowsToDelete, $children);
            }
        }


        if ($this->deleteCascade) {
            $this->tablePersistenceEngine->deleteRows($this->relatedTableMapping, $rowsToDelete);
        }

    }

}
