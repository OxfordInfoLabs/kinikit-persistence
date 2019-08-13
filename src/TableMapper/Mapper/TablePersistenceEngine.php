<?php


namespace Kinikit\Persistence\TableMapper\Mapper;


use Kinikit\Core\Binding\ObjectBinder;
use Kinikit\Core\DependencyInjection\Container;

class TablePersistenceEngine {


    // Save operations
    const SAVE_OPERATION_INSERT = "INSERT";
    const SAVE_OPERATION_UPDATE = "UPDATE";
    const SAVE_OPERATION_REPLACE = "REPLACE";
    const SAVE_OPERATION_SAVE = "SAVE";


    /**
     * Save the passed rows using a table mapping.  Perform the operation specified which are as follows:
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

        // Process save data and act accordingly
        $data = $this->processSaveData($tableMapping, $rows);

        // Gather objects for use below.
        $autoIncrementPk = $tableMapping->getAutoIncrementPk();
        $bulkDataManager = $tableMapping->getDatabaseConnection()->getBulkDataManager();
        $relationships = $tableMapping->getRelationships();
        $tableName = $tableMapping->getTableName();

        // if we have relationship data, we need to process this here.
        if (isset($data["relationshipData"])) {

            // Run pre-save operations for certain relationship types
            foreach ($data["relationshipData"] as $relationshipIndex => $relationshipDatum) {
                $relationships[$relationshipIndex]->preParentSaveOperation(self::SAVE_OPERATION_INSERT, $relationshipDatum);
            }

            // If an auto increment pk, need to insert / update each value
            if ($autoIncrementPk) {
                foreach ($data["saveRows"] as $index => $item) {
                    $bulkDataManager->insert($tableName, $item);
                    $data["saveRows"][$index][$autoIncrementPk] = $tableMapping->getDatabaseConnection()->getLastAutoIncrementId();
                }
            } else {
                $bulkDataManager->insert($tableName, $data["saveRows"]);
            }


            // Run post-save operations for certain relationship types
            foreach ($data["relationshipData"] as $relationshipIndex => $relationshipDatum) {
                $relationships[$relationshipIndex]->postParentSaveOperation(self::SAVE_OPERATION_INSERT, $relationshipDatum);
                $relationshipDatum->updateParentMember();
            }


        } else {
            $bulkDataManager->insert($tableMapping->getTableName(), $data["saveRows"]);
        }


    }



    // Process incoming data for a save operation
    // Essentially return a structured array ready for relational processing
    private function processSaveData($tableMapping, $data) {

        if (!isset($data[0])) {
            $data = [$data];
        }

        $relationships = $tableMapping->getRelationships();

        // if we have relationships, process otherwise simply return data for insert.
        if (sizeof($relationships) > 0) {

            // Sift through the relationships first and decide which ones need to pre-process and which ones can wait.
            $structuredData = ["saveRows" => [], "relationshipData" => []];
            foreach ($data as $index => $datum) {

                // Process relationship data
                foreach ($relationships as $relIndex => $relationship) {

                    if (isset($data[$index][$relationship->getMappedMember()])) {

                        // Now get the data for the relationship.
                        $relationshipData = $data[$index][$relationship->getMappedMember()];
                        if (!isset($relationshipData[0])) $relationshipData = [$relationshipData];

                        if (!isset($structuredData["relationshipData"][$relIndex]))
                            $structuredData["relationshipData"][$relIndex] = new TableRelationshipSaveData($relationship->getMappedMember(), $relationship->isMultiple());

                        $structuredData["relationshipData"][$relIndex]->addChildRows($datum, $relationshipData);

                        // Remove the mapped member from the parent insert array.
                        unset($datum[$relationship->getMappedMember()]);
                    }
                }

                $structuredData["saveRows"][] = &$datum;

            }


            return $structuredData;

        } else {
            return ["saveRows" => $data];
        }

    }


}
