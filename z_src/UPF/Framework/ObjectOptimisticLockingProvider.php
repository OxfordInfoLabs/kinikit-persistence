<?php

namespace Kinikit\Persistence\UPF\Framework;

/**
 * Interface which must be adhered to for implementing a locking provider for the UPF.  A locking provider
 * provides a concrete implementation of an optimistic locking strategy.  Functionally, the UPF calls the provider
 * to obtain locking data which it attaches to objects on retrieval.  When these same objects are saved back to the UPF
 * the detectLock method is called with the locking data previously attached and any conflict is detected.
 * 
 * @author mark
 *
 */
interface ObjectOptimisticLockingProvider {
	
	/**
	 * Hook method which is called by the persistence coordinator when a persistence transaction (Object Save, Object Delete)
	 * occurs.  This allows the locking provider to e.g. start a database transaction which may be rolled back in the case that 
	 * the save operation fails in any way.
	 * 
	 */
	public function persistenceTransactionStarted();
	
	/**
	 * Hook method, called by the persistence coordinator when a persistence transaction is successful.  This would normally
	 * perform an operation such as a DB commit.
	 * 
	 */
	public function persistenceTransactionSucceeded();
	
	/**
	 * Hook method, called by the persistence coordinator when a persistence transaction fails.  This would normally perform a
	 * rollback operation on a DB or similar.
	 * 
	 */
	public function persistenceTransactionFailed();
	
	/**
	 * Get the current locking data for an object identified by it's mapper and primary key.
	 * This should return something meaningful such as a version number which may then be 
	 * subsequently attached to the object for lock checking later.
	 * 
	 * @param ObjectMapper $objectMapper
	 * @param string $primaryKey
	 * @return mixed 
	 */
	public function getLockingDataForObject($objectMapper, $primaryKey);
	
	/**
	 * Update the current locking data for an object identified by it's mapper and primary key.
	 * This happens once a save / delete operation has been made successfully.
	 * 
	 * The new locking data should be returned from this function.
	 * 
	 * @param $objectMapper
	 * @param $primaryKey
	 * @return mixed 
	 */
	public function updateLockingDataForObject($objectMapper, $primaryKey);
	
	/**
	 * Detect whether or not an object is locked, based upon the mapper and primary key and the previously obtained locking data.
	 * Return a boolean (true is locked, false is not locked)
	 * 
	 * @param ObjectMapper $objectMapper
	 * @param string $primaryKey
	 * @param mixed $objectLockingData
	 */
	public function isObjectLocked($objectMapper, $primaryKey, $objectLockingData);

}

?>