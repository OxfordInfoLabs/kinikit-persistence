<?php


namespace Kinikit\Persistence\Database\BulkData;


/**
 * Standard bulk data manager used by MySQL and SQLite - implements
 * optimisations for insert and replace to use multi insert syntax
 * and REPLACE keyword.
 *
 *
 * Class StandardBulkDataManager
 * @package Kinikit\Persistence\Database\BulkData
 */
class StandardBulkDataManager extends DefaultBulkDataManager {

    /**
     * Overridden insert method to use multi insert syntax
     *
     * @param string $tableName
     * @param \mixed[][] $rows
     * @param null $insertColumns
     * @param $ignoreDuplicates
     * @return mixed|void
     */
    public function doInsert($tableName, $rows, $insertColumns, $ignoreDuplicates) {
        $this->doInsertOrReplace($tableName, $rows, $insertColumns);
    }

    public function doReplace($tableName, $rows, $replaceColumns) {
        $this->doInsertOrReplace($tableName, $rows, $replaceColumns, "REPLACE");
    }


    // Actually do an insert or replace (they are basically the same except for keyword difference).
    protected function doInsertOrReplace($tableName, $rows, $insertColumns, $type = "INSERT") {


        $escapedColumns = [];
        foreach ($insertColumns as $insertColumn) {
            $escapedColumns[] = $this->databaseConnection->escapeColumn($insertColumn);
        }

        $joinedColumns = join(",", $escapedColumns);

        // Loop in batch sizes.
        while ($slice = array_splice($rows, 0, (int)$this->batchSize, [])) {

            // Derive placeholders and get a prepared statement
            $placeholders = rtrim(str_repeat("?,", sizeof($insertColumns)), ",");
            $placeholders = rtrim(str_repeat("(" . $placeholders . "),", sizeof($slice)), ",");

            $statement = $this->getPreparedStatement(strtolower($type), $type . " INTO $tableName ($joinedColumns) VALUES $placeholders");

            $values = [];
            foreach ($slice as $sliceItem) {
                foreach ($insertColumns as $column) {
                    $values[] = $sliceItem[$column] ?? null;
                }
            }


            $statement->execute($values);


        }


    }


}
