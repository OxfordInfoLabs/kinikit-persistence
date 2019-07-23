<?php

namespace Kinikit\Persistence\Database\BulkData;

use Kinikit\Persistence\Database\Exception\SQLException;

interface BulkDataManager {


    /**
     * Update the batch size, used where supported to control the
     * number of items being processed by the below methods in a single query / transaction.
     *
     * @param int $batchSize
     */
    public function setBatchSize($batchSize);


    /**
     *
     * Insert the passed rows into the database table.
     *
     * Each row should contain key => value pairs for column => value mapping.
     *
     * If insert columns is supplied these will be used, otherwise the first row will be taken
     * as the blueprint for all rows.
     *
     * @param string $tableName
     * @param mixed[][] $rows
     * @param string[] $insertColumns
     * @throws SQLException
     */
    public function insert($tableName, $rows, $insertColumns = null);


    /**
     * Update the passed rows in the database table
     *
     * Each row should contain key => value pairs for column => value mapping.
     * It is safe and possible to send partial rows for partial update
     *
     * If update columns is supplied these will be used, otherwise the first row will be taken
     * as the blueprint for all rows.
     *
     * The optional matchColumns array will be used instead of the primary key
     * if a bulk update is occurring for non-primary key.
     *
     *
     * @param string $tableName
     * @param mixed[][] $rows
     * @param string[] $updateColumns
     * @param string[] $matchColumns
     *
     * @throws SQLException
     */
    public function update($tableName, $rows, $updateColumns = null, $matchColumns = null);


    /**
     * Replace the passed rows in the database table.  Unlike update,
     * this will replace the whole row with the new row data so it is
     * not safe to send partial data unless you intend for some columns
     * to be nulled.
     *
     * Each row should contain key => value pairs for column => value mapping.
     *
     * If replace columns is supplied these will be used, otherwise the first row will be taken
     * as the blueprint for all rows.
     *
     *
     * @param string $tableName
     * @param mixed[][] $rows
     * @param string[] $replaceColumns
     * @throws SQLException
     */
    public function replace($tableName, $rows, $replaceColumns = null);


    /**
     * Delete the rows in the database table matching the array of pk values passed.
     *
     * @param string $tableName
     * @param string[] $pkValues
     *
     * @param null $matchColumns
     * @throws SQLException
     */
    public function delete($tableName, $pkValues, $matchColumns = null);


    /**
     * Should be called by client code once use of the bulk data manager has completed.
     * This is particularly important if the manager is caching prepared statements for
     * repeated use.
     *
     * @return mixed
     */
    public function cleanup();

}
