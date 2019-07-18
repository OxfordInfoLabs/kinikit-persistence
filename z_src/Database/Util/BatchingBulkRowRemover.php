<?php

namespace Kinikit\Persistence\Database\Util;
use Kinikit\Persistence\Database\Exception\SQLException;

/**
 * Convenience worker for managing the batching of bulk removals from a database table.  This is constructed with a database connection, 
 * table name, key column array and batch size.
 * Delete Keys can then be added using the addRowKey method as an array containing an entry for each key column or a single value in the order specified in the key column array.
 * Multiple rows can be supplied as a 2 dimensional array using addRowKeys.
 * Once enough rows have been added to reach the batch size, that batch is committed to the DB.  The current set of outstanding row deletes can be committed 
 * at any time by calling commitBatch() manually and this should also be called at the end to clean up and ensure that all row deletes are committed.
 * 
 * @author mark
 *
 */
class BatchingBulkRowRemover {
	
	private $databaseConnection;
	private $tableName;
	private $rowKeyColumns;
	private $batchSize;
	
	private $currentBatch = array ();
	
	/**
	 * Construct the bulk row remover with all required configuration parameters.
	 * 
	 * @param BaseConnection $databaseConnection
	 * @param string $tableName
	 * @param array $rowKeyColumns
	 * @param integer $batchSize
	 */
	public function __construct($databaseConnection, $tableName, $rowKeyColumns, $batchSize) {
		$this->databaseConnection = $databaseConnection;
		$this->tableName = $tableName;
		$this->rowKeyColumns = $rowKeyColumns;
		$this->batchSize = $batchSize;
	}
	
	/**
	 * Add a single row key to the remover ready for batch deletion once the threshold has been reached
	 * 
	 * @param mixed $rowKey
	 */
	public function addRowKey($rowKey) {
		$this->currentBatch [] = $rowKey;
		
		// If we need to commit, do so.
		if (sizeof ( $this->currentBatch ) == $this->batchSize) {
			$this->commitBatch ();
		}
	}
	
	/**
	 * Add an array of row keys to the remover ready for batch deletion once the threshold has been reached.
	 * 
	 * @param unknown_type $rowKeys
	 */
	public function addRowKeys($rowKeys) {
		$this->currentBatch = array_merge ( $this->currentBatch, $rowKeys );
		if (sizeof ( $this->currentBatch ) >= $this->batchSize) {
			$this->commitBatch ();
		}
	}
	
	/**
	 * Commit the deletion of the outstanding rows in the batch.  Throw an SQL Exception if a problem occurs.
	 * 
	 */
	public function commitBatch() {
		
		if (sizeof ( $this->currentBatch ) > 0) {
			
			if ($this->databaseConnection->bulkDelete ( $this->tableName, $this->rowKeyColumns, array_slice ( $this->currentBatch, 0, $this->batchSize ) ))
				$this->currentBatch = array_slice ( $this->currentBatch, $this->batchSize );
			else
				throw new SQLException ( $this->databaseConnection->getLastErrorMessage () );
		}
	}

}

?>