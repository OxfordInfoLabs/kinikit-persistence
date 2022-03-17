<?php


namespace Kinikit\Persistence\Database\BulkData;


use Kinikit\Persistence\Database\Exception\SQLException;

/**
 * Default bulk data manager - returned from the Base Database connection.
 * This is conservative and as such should work on the majority of platforms.
 * All commands are wrapped in transactions and use only single insert / update
 * syntax except for delete which is IN / Multi or based.
 *
 * Class DefaultBulkDataManager
 * @package Kinikit\Persistence\Database\BulkData
 */
class DefaultBulkDataManager extends BaseBulkDataManager {

    /**
     * Insert the passed rows into the database table.
     *
     * Each row should contain key => value pairs for column => value mapping and must have the same
     * row format.
     *
     * @param string $tableName
     * @param mixed[][] $rows
     * @param null $insertColumns
     * @throws SQLException
     */
    public function doInsert($tableName, $rows, $insertColumns) {

        // Escape all insert columns
        foreach ($insertColumns as  $insertColumn) {
            $escapedColumns[] = $this->databaseConnection->escapeColumn($insertColumn);
        }

        $joinedColumns = join(",", $escapedColumns);
        $placeholders = rtrim(str_repeat("?,", sizeof($insertColumns)), ",");

        // Prepare an insert command first up
        $statement = $this->getPreparedStatement("insert", "INSERT INTO $tableName ($joinedColumns) VALUES ($placeholders)");

        // Now execute for each row.
        foreach ($rows as $row) {
            $insertValues = [];
            foreach ($insertColumns as $column) {
                $insertValues[] = $row[$column] ?? null;
            }
            $statement->execute($insertValues);
        }

    }

    /**
     * Update the passed rows in the database table
     *
     * Each row should contain key => value pairs for column => value mapping.
     * It is safe and possible to send partial rows for partial update
     *
     * The optional matchColumns array will be used instead of the primary key
     * if a bulk update is occurring for non-primary key.
     *
     *
     * @param string $tableName
     * @param mixed[][] $rows
     * @param null $updateColumns
     * @param string[] $matchColumns
     *
     * @throws SQLException
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
     * Replace the passed rows in the database table.  Unlike update,
     * this will replace the whole row with the new row data so it is
     * not safe to send partial data unless you intend for some columns
     * to be nulled.
     *
     * Each row should contain key => value pairs for column => value mapping.
     *
     *
     * @param string $tableName
     * @param mixed[][] $rows
     *
     * @param null $replaceColumns
     * @throws SQLException
     */
    public function doReplace($tableName, $rows, $replaceColumns) {
        $this->delete($tableName, $rows);
        $this->insert($tableName, $rows);
    }

    /**
     * Delete the rows in the database table matching the array of pk values passed.
     *
     * @param string $tableName
     * @param string[] $pkValues
     *
     * @param $matchColumns
     * @throws SQLException
     */
    public function doDelete($tableName, $pkValues, $matchColumns) {

        $inClause = !is_array($pkValues[0] ?? null);

        // Loop in batch sizes.
        while ($slice = array_splice($pkValues, 0, $this->batchSize, [])) {

            $query = "DELETE FROM $tableName WHERE ";

            $values = [];

            if ($inClause) {
                $query .= $this->databaseConnection->escapeColumn($matchColumns[0]) . " IN (" . rtrim(str_repeat("?,", sizeof($slice)), ",") . ")";
                $values = $slice;
            } else {

                $rowClauses = [];

                foreach ($slice as $sliceRow) {

                    $matchClauses = [];

                    foreach ($matchColumns as $index => $matchColumn) {
                        $value = $sliceRow[$matchColumn] ?? $sliceRow[$index] ?? null;

                        // Add the value provided it is not null
                        if ($value !== null) {
                            $matchClauses[] = $this->databaseConnection->escapeColumn($matchColumn) . "=?";
                            $values[] = $value;
                        } else {
                            $matchClauses[] = $this->databaseConnection->escapeColumn($matchColumn) . " IS NULL";
                        }
                    }

                    $rowClauses[] = "(" . join(" AND ", $matchClauses) . ")";

                }

                $query .= join(" OR ", $rowClauses);

            }

            $statement = $this->getPreparedStatement("delete", $query);
            $statement->execute($values);

        }


    }


}
