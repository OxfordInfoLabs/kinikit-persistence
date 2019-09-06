<?php


namespace Kinikit\Persistence\TableMapper\Mapper;

class TablePersistenceEngine {


    // Save operations
    const SAVE_OPERATION_INSERT = "INSERT";
    const SAVE_OPERATION_UPDATE = "UPDATE";
    const SAVE_OPERATION_REPLACE = "REPLACE";
    const SAVE_OPERATION_SAVE = "SAVE";


    /**
     * Save the passed rows using a table mapping.  Return updated row structure with new primary keys etc.
     *
     * INSERT: Strictly insert rows including any linked relational rows.
     * UPDATE: Strictly update rows including linked relational rows - this will fail silently if row data doesn't exist.
     * REPLACE: Insert / Update rows including linked relational rows - this effectively removes and inserts.
     * SAVE: Used by persistence framework.  This will do a replace operation and remove any old relational data if required.
     *
     * @param TableMapping $tableMapping
     * @param mixed[][string] $rows
     * @param string $saveOperation
     */
    public function saveRows($tableMapping, $rows, $saveOperation = self::SAVE_OPERATION_SAVE) {

        if (sizeof($rows) == 0) {
            return $rows;
        }

        if (!isset($rows[0])) {
            $rows = [$rows];
        }
        $this->__saveRows($tableMapping, $rows, $saveOperation);
        return $rows;
    }


    /**
     * Delete the passed rows using a table mapping.  This will follow delete cascade rules to
     * ensure that
     *
     * @param TableMapping $tableMapping
     * @param $rows
     */
    public function deleteRows($tableMapping, $rows) {
        if (sizeof($rows) == 0) {
            return;
        }

        if (!isset($rows[0])) {
            $rows = [$rows];
        }


        // Gather objects for use below.
        $relationships = $tableMapping->getRelationships();

        // if we have relationship data, we need to unrelate any children.
        if (sizeof($relationships) > 0) {
            foreach ($relationships as $relationship) {
                $relationship->unrelateChildren($rows);
            }
        }


        // Delete the rows
        $bulkDataManager = $tableMapping->getDatabaseConnection()->getBulkDataManager();
        $bulkDataManager->delete($tableMapping->getTableName(), $rows, $tableMapping->getPrimaryKeyColumnNames());


    }


    /**
     * Internal save rows function.  This saves the main row and processes any relationship data.
     *
     * @param $tableMapping
     * @param $rows
     * @param $saveOperation
     */
    public function __saveRows($tableMapping, &$rows, $saveOperation) {

        // Gather objects for use below.
        $relationships = $tableMapping->getRelationships();


        // if we have relationship data, we need to process this here.
        if (sizeof($relationships) > 0) {

            $relationalData = [];

            // Get relational save data
            $this->populateRelationalData($relationships, $rows, $relationalData);


            // Run pre-save operations where certain relationship types require it.
            $relationshipColumns = [];
            foreach ($relationships as $index => $relationship) {
                $relationship->preParentSaveOperation($saveOperation, $relationalData[$index]);
                $relationshipColumns[] = $relationship->getMappedMember();
            }

            // Gather the save columns by removing relationship columns as required.
            $saveColumns = array_diff(array_keys($rows[0]), $relationshipColumns);

            // Save the main row.
            $this->saveRowData($tableMapping, $saveOperation, $rows, $saveColumns);

            // Run post-save operations where certain relationship types require it.
            foreach ($relationships as $index => $relationship) {
                $relationship->postParentSaveOperation($saveOperation, $relationalData[$index]);
            }


        } else {
            $saveColumns = array_keys($rows[0]);
            $this->saveRowData($tableMapping, $saveOperation, $rows, $saveColumns);
        }

    }


    // Fill the save data with a relational structure from the save row array.
    private function populateRelationalData($relationships, &$saveRows, &$relationalData) {

        foreach ($relationships as $index => $relationship) {
            $relationalData[$index] = ["allRelatedItems" => [], "relatedItemsByParent" => []];
            $mappedMember = $relationship->getMappedMember();
            foreach ($saveRows as $rowIndex => $saveRow) {

                $relationalData[$index]["relatedItemsByParent"][$rowIndex] = ["parentRow" => &$saveRows[$rowIndex], "items" => []];

                // If we have relational data, add it in with a parent indicator
                if (isset($saveRow[$mappedMember])) {
                    if (isset($saveRow[$mappedMember][0])) {
                        for ($i = 0; $i < sizeof($saveRow[$mappedMember]); $i++) {
                            $relationalData[$index]["allRelatedItems"][] = &$saveRows[$rowIndex][$mappedMember][$i];
                            $relationalData[$index]["relatedItemsByParent"][$rowIndex]["items"][] = &$saveRows[$rowIndex][$mappedMember][$i];
                        }
                    } else {
                        $relationalData[$index]["allRelatedItems"][] = &$saveRows[$rowIndex][$mappedMember];
                        $relationalData[$index]["relatedItemsByParent"][$rowIndex]["items"][] = &$saveRows[$rowIndex][$mappedMember];
                    }
                }

            }
        }
    }


    // Save row data, taking account of any
    private function saveRowData($tableMapping, $saveOperation, &$data, $saveColumns) {

        $autoIncrementPk = $tableMapping->getAutoIncrementPk();

        // If an auto increment pk, need to insert / update each value
        if ($saveOperation != self::SAVE_OPERATION_UPDATE && $autoIncrementPk) {
            foreach ($data as $index => $item) {
                $this->saveRowDataForOperation($tableMapping, $saveOperation, $data[$index], $saveColumns);
                if (!isset($data[$index][$autoIncrementPk]))
                    $data[$index][$autoIncrementPk] = $tableMapping->getDatabaseConnection()->getLastAutoIncrementId();
            }
        } else {
            $this->saveRowDataForOperation($tableMapping, $saveOperation, $data, $saveColumns);
        }
    }


    /**
     * Actually save row data using the table mapping and the defined save operation.
     *
     * @param TableMapping $tableMapping
     * @param string $saveOperation
     * @param mixed[] $data
     */
    private function saveRowDataForOperation($tableMapping, $saveOperation, $data, $saveColumns) {
        $bulkDataManager = $tableMapping->getDatabaseConnection()->getBulkDataManager();
        switch ($saveOperation) {
            case self::SAVE_OPERATION_INSERT:
                $bulkDataManager->insert($tableMapping->getTableName(), $data, $saveColumns);
                break;
            case self::SAVE_OPERATION_UPDATE:
                $bulkDataManager->update($tableMapping->getTableName(), $data, $saveColumns);
                break;
            case self::SAVE_OPERATION_REPLACE:
            case self::SAVE_OPERATION_SAVE:
                $bulkDataManager->replace($tableMapping->getTableName(), $data, $saveColumns);
                break;
        }

    }


}
