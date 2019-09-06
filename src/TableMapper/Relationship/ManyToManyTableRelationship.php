<?php


namespace Kinikit\Persistence\TableMapper\Relationship;


use Kinikit\Persistence\TableMapper\Mapper\TableMapping;
use Kinikit\Persistence\TableMapper\Mapper\TablePersistenceEngine;

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
    public function __construct($relatedTableMapping, $mappedMember, $linkTableName, $saveCascade = false, $deleteCascade = false) {
        parent::__construct($relatedTableMapping, $mappedMember, $saveCascade, $deleteCascade);
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


    /**
     * Get child data using the parent row data as reference.
     *
     * @param array $parentRows
     * @return array
     */
    public function retrieveChildData(&$parentRows) {

        $parentTableName = $this->parentMapping->getTableName();

        $fetchChildren = [];
        $fetchClauses = [];
        $fetchValues = [];
        foreach ($parentRows as $index => $parentRow) {
            if (!isset($parentRow[$this->mappedMember])) {
                $parentPks = $this->parentMapping->getPrimaryKeyValues($parentRow);
                $fetchChildren[join("||", $parentPks)] = $index;

                $clause = [];
                foreach ($parentPks as $column => $value) {
                    $clause[] = $parentTableName . "_" . $column . " =?";
                    $fetchValues[] = $value;
                }
                $fetchClauses[] = "(" . join(" AND ", $clause) . ")";

                $parentRows[$index][$this->mappedMember] = [];
            }
        }

        if (sizeof($fetchChildren) > 0) {
            $childTableName = $this->relatedTableMapping->getTableName();
            $linkTableName = $this->linkTableName;
            $childPKColumns = $this->relatedTableMapping->getPrimaryKeyColumnNames();
            $parentPKColumns = $this->parentMapping->getPrimaryKeyColumnNames();

            $linkTableMapping = $this->getLinkTableMapping();
            $linkRecords = $this->tableQueryEngine->query($linkTableMapping, "WHERE " . join(" OR ", $fetchClauses), $fetchValues);

            // Now create a relational lookup table for mapping after the fact
            $parentLookup = [];
            $childClauses = [];
            $childValues = [];
            foreach ($linkRecords as $linkRecord) {
                $parentPk = [];
                foreach ($parentPKColumns as $parentPKColumn) {
                    $parentPk[] = $linkRecord[$parentTableName . "_" . $parentPKColumn];
                }

                $childPk = [];
                $childClause = [];
                foreach ($childPKColumns as $childPKColumn) {
                    $childPk[] = $linkRecord[$childTableName . "_" . $childPKColumn];
                    $childValues[] = $linkRecord[$childTableName . "_" . $childPKColumn];
                    $childClause[] = "$childPKColumn=?";
                }
                $childClauses[] = "(" . join(" AND ", $childClause) . ")";

                $parentLookup[join("||", $childPk)] = join("||", $parentPk);

            }

            // Now grab the children
            $children = $this->tableQueryEngine->query($this->relatedTableMapping, "WHERE " . join(" OR ", $childClauses), $childValues);

            // Now map the child back to the parent
            foreach ($children as $child) {
                $pk = join("||", $this->relatedTableMapping->getPrimaryKeyValues($child));
                if (isset($parentLookup[$pk])) {
                    $parentRows[$fetchChildren[$parentLookup[$pk]]][$this->mappedMember][] = $child;
                }
            }

        }

    }


    /**
     * Implement post action as one to one's should have parent id fields.
     *
     * @param string $saveType
     * @param array $relationshipData
     * @return mixed|void
     */
    public function postParentSaveOperation($saveType, &$relationshipData) {

        // Perform a save operation using the child rows.
        $this->performSaveOperationOnChild($saveType, $relationshipData);

        $parentTableName = $this->parentMapping->getTableName();
        $childTableName = $this->getRelatedTableMapping()->getTableName();
        $parentPrimaryKeyColumns = $this->parentMapping->getPrimaryKeyColumnNames();
        $childPrimaryKeyColumns = $this->getRelatedTableMapping()->getPrimaryKeyColumnNames();

        $insertData = [];
        foreach ($relationshipData["relatedItemsByParent"] as $relatedItem) {
            foreach ($relatedItem["items"] as $item) {
                $row = [];
                foreach ($parentPrimaryKeyColumns as $parentPrimaryKeyColumn) {
                    $columnName = $parentTableName . "_" . $parentPrimaryKeyColumn;
                    $row[$columnName] = $relatedItem["parentRow"][$parentPrimaryKeyColumn] ?? "";
                }

                foreach ($childPrimaryKeyColumns as $childPrimaryKeyColumn) {
                    $columnName = $childTableName . "_" . $childPrimaryKeyColumn;
                    $row[$columnName] = $item[$childPrimaryKeyColumn] ?? "";
                }

                $insertData[] = $row;
            }
        }

        $linkTableMapper = $this->getLinkTableMapping();
        $persistenceEngine = new TablePersistenceEngine();
        $persistenceEngine->saveRows($linkTableMapper, $insertData, $saveType);
    }


    /**
     * Unrelate children from parent.  If explicit set of child rows passed
     * these are unrelated otherwise it is assumed that all child rows from the
     * parent are to be processed.
     *
     * @param array $parentRows
     * @param array $childRows
     */
    public function unrelateChildren($parentRows, $childRows = null) {

        if (sizeof($parentRows) == 0)
            return;

        // Ensure we have loaded data for parent rows.
        $this->retrieveChildData($parentRows);

        $parentTableName = $this->parentMapping->getTableName();
        $childTableName = $this->getRelatedTableMapping()->getTableName();
        $parentPrimaryKeyColumns = $this->parentMapping->getPrimaryKeyColumnNames();
        $childPrimaryKeyColumns = $this->getRelatedTableMapping()->getPrimaryKeyColumnNames();

        $linkRowsToDelete = [];
        $rowsToDelete = [];
        foreach ($parentRows as $parentRow) {
            if (isset($parentRow[$this->mappedMember]) && is_array($parentRow[$this->mappedMember]) && sizeof($parentRow[$this->mappedMember])) {
                $children = isset($parentRow[$this->mappedMember][0]) ? $parentRow[$this->mappedMember] : [$parentRow[$this->mappedMember]];

                foreach ($children as $child) {


                    $linkRow = [];
                    foreach ($parentPrimaryKeyColumns as $parentPrimaryKeyColumn) {
                        $columnName = $parentTableName . "_" . $parentPrimaryKeyColumn;
                        $linkRow[$columnName] = $parentRow[$parentPrimaryKeyColumn] ?? "";
                    }

                    foreach ($childPrimaryKeyColumns as $childPrimaryKeyColumn) {
                        $columnName = $childTableName . "_" . $childPrimaryKeyColumn;
                        $linkRow[$columnName] = $child[$childPrimaryKeyColumn] ?? "";
                    }
                    $linkRowsToDelete[] = $linkRow;
                }

                $rowsToDelete = array_merge($rowsToDelete, $children);
            }
        }


        if (sizeof($linkRowsToDelete) > 0) {
            $linkTableMapping = $this->getLinkTableMapping();
            $persistenceEngine = new TablePersistenceEngine();
            $persistenceEngine->deleteRows($linkTableMapping, $linkRowsToDelete);
        }

        if ($this->deleteCascade) {
            $this->tablePersistenceEngine->deleteRows($this->relatedTableMapping, $rowsToDelete);
        }

    }


    // Get the link table mapping
    private function getLinkTableMapping() {

        $parentTableName = $this->parentMapping->getTableName();
        $childTableName = $this->relatedTableMapping->getTableName();

        $linkTablePkColumns = [];
        foreach ($this->parentMapping->getPrimaryKeyColumnNames() as $columnName) {
            $linkTablePkColumns[] = $parentTableName . "_" . $columnName;
        }
        foreach ($this->relatedTableMapping->getPrimaryKeyColumnNames() as $columnName) {
            $linkTablePkColumns[] = $childTableName . "_" . $columnName;
        }

        return new TableMapping($this->linkTableName, [], $this->relatedTableMapping->getDatabaseConnection(), $linkTablePkColumns);

    }


}
