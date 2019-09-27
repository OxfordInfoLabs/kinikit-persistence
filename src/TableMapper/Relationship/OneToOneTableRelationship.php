<?php


namespace Kinikit\Persistence\TableMapper\Relationship;


use Kinikit\Persistence\TableMapper\Mapper\TableMapping;
use Kinikit\Persistence\TableMapper\Mapper\TablePersistenceEngine;
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
     * @param $relatedTableMapping
     * @param $mappedMember
     * @param $parentJoinColumnName
     */
    public function __construct($relatedTableMapping, $mappedMember, $childJoinColumnNames, $saveCascade = true, $deleteCascade = true) {
        parent::__construct($relatedTableMapping, $mappedMember, $saveCascade, $deleteCascade);

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
     * @return \string[]
     */
    public function getChildJoinColumnNames() {
        return $this->childJoinColumnNames;
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

        $parentPrimaryKeyColumns = $this->parentMapping->getPrimaryKeyColumnNames();

        $clause = "LEFT JOIN " . $this->relatedTableMapping->getTableName() . " $myAlias ON ";

        $onClauses = [];
        foreach ($this->childJoinColumnNames as $index => $joinColumnName) {

            // Handle static values
            if (strpos($joinColumnName, "=")) {
                $splitColumnName = explode("=", $joinColumnName);
                $staticValue = trim($splitColumnName[1]);
                if (!is_numeric($staticValue)) $staticValue = "'" . $staticValue . "'";
                $onClauses[] = "$myAlias.$splitColumnName[0] = $staticValue";
            } else {
                $onClauses[] = "$parentAlias.{$parentPrimaryKeyColumns[$index]} = $myAlias.$joinColumnName";
            }
        }

        $clause .= join(" AND ", $onClauses);

        return $clause;


    }

    /**
     * Retrieve child data
     *
     * @param $parentRow
     * @return mixed|void
     */
    public function retrieveChildData(&$parentRows) {

        // Calculate which rows we need to fetch based upon parent rows with missing data.
        $fetchRows = [];
        $queryValues = [];
        $queryClauses = [];
        foreach ($parentRows as $index => $parentRow) {
            if (!isset($parentRow[$this->mappedMember])) {
                $pkValues = $this->parentMapping->getPrimaryKeyValues($parentRow);
                $fetchRows[join("||", $pkValues)] = $index;

                $queryClause = [];
                foreach ($this->parentMapping->getPrimaryKeyColumnNames() as $pkIndex => $columnName) {
                    $queryValues[] = $pkValues[$columnName];
                    $queryClause[] = $this->childJoinColumnNames[$pkIndex] . "=?";
                }
                $queryClauses[] = "(" . join(" AND ", $queryClause) . ")";

                $parentRows[$index][$this->mappedMember] = [];

            }
        }

        // Fetch the required child rows and map back onto the parent.
        if (sizeof($queryClauses) > 0) {
            $fetchedRows = $this->tableQueryEngine->query($this->relatedTableMapping, "WHERE " . join(" OR ", $queryClauses), $queryValues);
            foreach ($fetchedRows as $fetchedRow) {

                $pkString = [];
                foreach ($this->childJoinColumnNames as $childJoinColumnName) {
                    $pkString[] = $fetchedRow[$childJoinColumnName];
                }

                $pkString = join("||", $pkString);
                $parentRows[$fetchRows[$pkString]][$this->mappedMember][] = $fetchedRow;
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

        // Synchronise the child fields from the parent
        $this->synchroniseChildFieldsFromParent($this->parentMapping->getPrimaryKeyColumnNames(), $this->childJoinColumnNames, $relationshipData);

        // Save the children
        $this->performSaveOperationOnChildren($saveType, $relationshipData);


    }


    /**
     * Unrelate children
     *
     * @param $parentRows
     * @param null $childRows
     * @return mixed|void
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


        // If delete cascade, delete all child rows.  Otherwise, update to null
        if ($this->deleteCascade) {
            $this->tablePersistenceEngine->deleteRows($this->relatedTableMapping, $rowsToDelete);
        } else {
            foreach ($rowsToDelete as $index => $row) {
                foreach ($this->childJoinColumnNames as $childJoinColumnName) {
                    $rowsToDelete[$index][$childJoinColumnName] = null;
                }
            }
            $this->tablePersistenceEngine->saveRows($this->relatedTableMapping, $rowsToDelete, TablePersistenceEngine::SAVE_OPERATION_UPDATE);
        }

    }


}
