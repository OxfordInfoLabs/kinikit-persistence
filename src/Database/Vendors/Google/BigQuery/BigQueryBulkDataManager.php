<?php

namespace Kinikit\Persistence\Database\Vendors\Google\BigQuery;

use Kinikit\Persistence\Database\BulkData\DefaultBulkDataManager;

class BigQueryBulkDataManager extends DefaultBulkDataManager {

    /**
     * @param string $tableName
     * @param array $rows
     * @param array $insertColumns
     * @param bool $ignoreDuplicates
     * @return void
     */
    public function doInsert($tableName, $rows, $insertColumns, $ignoreDuplicates = false) {

        $escapedColumns = array_map([$this->databaseConnection, 'escapeColumn'], $insertColumns);
        $joinedColumns = implode(",", $escapedColumns);

        while ($slice = array_splice($rows, 0, $this->batchSize)) {
            $rowPlaceholders = [];
            $values = [];

            foreach ($slice as $sliceItem) {
                $currentItemPlaceholders = [];

                foreach ($insertColumns as $column) {
                    $val = $sliceItem[$column] ?? null;

                    if ($val === null) {
                        // Inject NULL literal directly into the SQL string
                        $currentItemPlaceholders[] = "NULL";
                    } else {
                        // Use a placeholder and track the value
                        $currentItemPlaceholders[] = "?";
                        $values[] = $val;
                    }
                }
                $rowPlaceholders[] = "(" . implode(",", $currentItemPlaceholders) . ")";
            }

            $allPlaceholders = implode(",", $rowPlaceholders);
            $sql = "INSERT INTO $tableName ($joinedColumns) VALUES $allPlaceholders";

            $statement = $this->getPreparedStatement("insert", $sql);
            $statement->execute($values);
        }
    }

    /**
     * @param string $tableName
     * @param array $rows
     * @param array $updateColumns
     * @param string[] $matchColumns
     * @return void
     */
    public function doUpdate($tableName, $rows, $updateColumns, $matchColumns) {

        $updateClauses = [];
        foreach ($updateColumns as $column) {
            $updateClauses[] = $this->databaseConnection->escapeColumn($column) . "=?";
        }
        $updateClause = join(",", $updateClauses);

        $matchClauses = [];
        foreach ($matchColumns as $column) {
            $matchClauses[] = $this->databaseConnection->escapeColumn($column) . "=?";
        }
        $matchClause = join(" AND ", $matchClauses);

        $statement = $this->getPreparedStatement("update", "UPDATE $tableName SET $updateClause WHERE $matchClause");

        // Now execute for each row.
        foreach ($rows as $row) {
            $updateValues = [];
            foreach ($updateColumns as $column) {
                $updateValues[] = $row[$column] ?? null;
            }
            foreach ($matchColumns as $column) {
                $updateValues[] = $row[$column] ?? null;
            }
            $statement->execute($updateValues);
        }
    }

    /**
     * @param $tableName
     * @param $rows
     * @param $replaceColumns
     * @return void
     */
    public function doReplace($tableName, $rows, $replaceColumns) {
        $this->delete($tableName, $rows);
        $this->insert($tableName, $rows);
    }

    /**
     * @param $tableName
     * @param $pkValues
     * @param $matchColumns
     * @return void
     */
    public function doDelete($tableName, $pkValues, $matchColumns) {

    }

}