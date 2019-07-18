<?php

namespace Kinikit\Persistence\Database\Util;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Exception\SQLException;

/**
 * Convenience worker for managing the batching of bulk inserts.  This is constructed with a table name, column array and batch size.
 * Data rows can then be added using the addRow method as an array containing an entry for each column in the order specified in the column array.
 * Multiple rows can be supplied as a 2 dimensional array using addRows.
 * Once enough rows have been added to reach the batch size, that batch is committed to the DB.  The current set of outstanding rows can be committed
 * at any time by calling commitBatch() manually and this should also be called at the end to clean up and ensure that all rows are committed.
 *
 * @author mark
 *
 */
class BatchingBulkRowInserter {

    protected $databaseConnection;
    protected $tableName;
    protected $columnNames;
    protected $batchSize;

    protected $currentBatch = array();

    /**
     * Construct the batching bulk row inserter with required stuff
     *
     * @param DatabaseConnection $databaseConnection
     * @param string $tableName
     * @param array $columnNames
     * @param integer $batchSize
     */
    public function __construct($databaseConnection, $tableName, $columnNames, $batchSize) {
        $this->databaseConnection = $databaseConnection;
        $this->tableName = $tableName;
        $this->columnNames = $columnNames;
        $this->batchSize = $batchSize;
    }

    /**
     * Add a single row to the current batch for insert.
     *
     * @param array $data
     */
    public function addRow($data) {
        $this->currentBatch [] = $data;

        // If we need to commit, do so.
        if (sizeof($this->currentBatch) == $this->batchSize) {
            $this->commitBatch();
        }
    }

    /**
     * Add a number of rows to the current batch for insert.
     * This should be a 2 dimensional array of row data.
     *
     * @param array $data
     */
    public function addRows($data) {
        $this->currentBatch = array_merge($this->currentBatch, $data);

        while (sizeof($this->currentBatch) >= $this->batchSize) {
            $this->commitBatch();
        }
    }

    /**
     * Commit the current batch of data, throw an SQL exception if a problem occurs doing so.
     *
     */
    public function commitBatch() {
        if (sizeof($this->currentBatch) > 0) {

            if ($this->databaseConnection->bulkInsert($this->tableName, $this->columnNames, array_slice($this->currentBatch, 0, $this->batchSize)))
                $this->currentBatch = array_slice($this->currentBatch, $this->batchSize);
            else {
                throw new SQLException ($this->databaseConnection->getLastErrorMessage());
            }
        }
    }

}

?>