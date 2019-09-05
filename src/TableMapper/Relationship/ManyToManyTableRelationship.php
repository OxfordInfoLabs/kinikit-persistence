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
        // TODO: Implement retrieveChildData() method.
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

        $linkTableMapper = new TableMapping($this->linkTableName);
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
        // TODO: Implement unrelateChildren() method.
    }
}
