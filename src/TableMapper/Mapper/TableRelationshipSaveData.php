<?php


namespace Kinikit\Persistence\TableMapper\Mapper;


class TableRelationshipSaveData {

    /**
     * The full set of child rows to save
     *
     * @var array
     */
    private $allChildRows = [];


    /**
     * Child rows partitioned by parent for manipulation in relationship objects
     *
     * Each item is a 2 valued array with the following keys.
     *
     * parentRow: A reference to the parent row being inserted
     * childRows: The set of rows for this parent (references to the main insertRows elements)
     *
     * @var array
     */
    private $childRowsByParent = [];


    /**
     * The member on the parent class which stores the relational data.
     *
     * @var string
     */
    private $parentRelationshipMember;


    /**
     * Whether or not this relationship is multiple.
     *
     * @var boolean
     */
    private $relationshipIsMultiple;

    /**
     * Construct with a parent relationship member.
     *
     * TableRelationshipSaveData constructor.
     * @param $parentRelationshipMember
     */
    public function __construct($parentRelationshipMember, $isMultiple) {
        $this->parentRelationshipMember = $parentRelationshipMember;
        $this->relationshipIsMultiple = $isMultiple;
    }

    /**
     * Get all rows to save
     *
     * @return array
     */
    public function getAllChildRows() {
        return $this->allChildRows;
    }

    /**
     * @return array
     */
    public function getChildRowsByParent() {
        return $this->childRowsByParent;
    }


    /**
     * Add child rows for a given parent row.
     *
     * @param $parentRow
     * @param $childRows
     */
    public function addChildRows(&$parentRow, $childRows) {
        $this->childRowsByParent[] = ["parentRow" => &$parentRow, "childRows" => &$childRows];
        foreach ($childRows as $index => $childRow) {
            $this->allChildRows[] = &$childRows[$index];
        }
    }


    /**
     * Synchronise parent fields from a child field
     *
     * @param $itemKey
     * @param $itemValue
     */
    public function synchroniseParentFieldsFromChild($childFields, $parentFields) {

    }


    /**
     * Synchronise child fields from a parent field
     *
     * @param $parentFields
     * @param $childFields
     */
    public function synchroniseChildFieldsFromParent($parentFields, $childFields) {
        foreach ($this->childRowsByParent as $index => $value) {
            $parentRow = $value["parentRow"];

            foreach ($value["childRows"] as $childIndex => $childRow) {
                foreach ($parentFields as $fieldIndex => $field) {
                    $this->childRowsByParent[$index]["childRows"][$childIndex][$childFields[$fieldIndex]] = $parentRow[$field];
                }
            }

        }
    }


    /**
     * Update parent member with relational data
     */
    public function updateParentMember() {

        foreach ($this->childRowsByParent as $index => $value) {

            if ($this->relationshipIsMultiple) {
                $this->childRowsByParent[$index]["parentRow"][$this->parentRelationshipMember] = $this->childRowsByParent[$index]["childRows"];
            } else {
                $this->childRowsByParent[$index]["parentRow"][$this->parentRelationshipMember] = $this->childRowsByParent[$index]["childRows"][0] ?? null;
            }
        }

    }


}
