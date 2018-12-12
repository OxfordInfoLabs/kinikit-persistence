<?php

namespace Kinikit\Persistence\Database\Util;
use Kinikit\Persistence\Database\Exception\SQLException;

/**
 * Simple extension of the row inserter to facilitate bulk replacements.
 */
class BatchingBulkRowReplacer extends BatchingBulkRowInserter {

    private $primaryKeyColumnIndexes;


    /**
     * Construct the batching bulk row inserter with required stuff
     *
     * @param BaseConnection $databaseConnection
     * @param string $tableName
     * @param array $columnNames
     * @param array $primaryKeyColumnIndexes
     * @param integer $batchSize
     */
    public function __construct($databaseConnection, $tableName, $columnNames, $primaryKeyColumnIndexes, $batchSize) {
        parent::__construct($databaseConnection, $tableName, $columnNames, $batchSize);
        $this->primaryKeyColumnIndexes = $primaryKeyColumnIndexes;
    }

    /**
     * Commit the current batch of data, throw an SQL exception if a problem occurs doing so.
     *
     */
    public function commitBatch() {
        if (sizeof($this->currentBatch) > 0) {
            if ($this->databaseConnection->bulkReplace($this->tableName, $this->columnNames, $this->primaryKeyColumnIndexes, array_slice($this->currentBatch, 0, $this->batchSize)))
                $this->currentBatch = array_slice($this->currentBatch, $this->batchSize);
            else {
                throw new SQLException ($this->databaseConnection->getLastErrorMessage());
            }
        }
    }
}