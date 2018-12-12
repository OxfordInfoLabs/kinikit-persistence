<?php

namespace Kinikit\Persistence\UPF\Framework;

class TestLockingProvider implements ObjectOptimisticLockingProvider {
	
	public $lockingData = array ();
	public $lockedStatus = array ();
	public $updatesRecorded = array ();
	
	public $transactionsStarted = 0;
	public $transactionsFailed = 0;
	public $transactionsSucceeded = 0;
	
	/**
	 * Transaction failed
	 */
	public function persistenceTransactionFailed() {
		$this->transactionsFailed ++;
	}
	
	/**
	 * Transaction started
	 */
	public function persistenceTransactionStarted() {
		$this->transactionsStarted ++;
	}
	
	/**
	 * Transaction succeeded
	 */
	public function persistenceTransactionSucceeded() {
		$this->transactionsSucceeded ++;
	}
	
	/**
	 * Update the locking data
	 * 
	 * @param unknown_type $objectMapper
	 * @param unknown_type $primaryKey
	 */
	public function updateLockingDataForObject($objectMapper, $primaryKey) {
		if (! isset ( $this->updatesRecorded [$objectMapper->getClassName () . $primaryKey] )) {
			$this->updatesRecorded [$objectMapper->getClassName () . $primaryKey] = 0;
		}
		$this->updatesRecorded [$objectMapper->getClassName () . $primaryKey] ++;
		
		return "UpdatedLockingData";
	}
	
	/**
	 * @param unknown_type $objectMapper
	 * @param unknown_type $primaryKey
	 */
	public function getLockingDataForObject($objectMapper, $primaryKey) {
		
		return isset ( $this->lockingData [$objectMapper->getClassName () . $primaryKey] ) ? $this->lockingData [$objectMapper->getClassName () . $primaryKey] : null;
	}
	
	/**
	 * @param unknown_type $objectMapper
	 * @param unknown_type $primaryKey
	 * @param unknown_type $objectLockingData
	 */
	public function isObjectLocked($objectMapper, $primaryKey, $objectLockingData) {
		
		return isset ( $this->lockedStatus [$objectMapper->getClassName () . $primaryKey . $objectLockingData] ) ? $this->lockedStatus [$objectMapper->getClassName () . $primaryKey . $objectLockingData] : false;
	}

}

?>