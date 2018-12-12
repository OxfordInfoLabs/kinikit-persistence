<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\SerialisableObject;

/**
 * Special facade object used when lazy loading is employed through the persistence engine.  This is an object which is configured with
 * the backing object's Type and Primary Key and also the Retrieval Engine instance used to pull the real version if required.  Application code should
 * simply call the getRealObject method to return the real object pulled by primary key.
 * 
 */
class ObjectArrayFacade extends SerialisableObject {
	
	protected $underlyingObjectClass;
	protected $underlyingObjectFieldValues;
	protected $engineIdentifier;
	private $persistenceCoordinatorInstance;
	
	private $cachedInstance = null;
	
	/**
	 * Construct this facade with all data required to pull the real object instance as required.
	 * This essentially contains the information to then perform the 
	 * 
	 * @param string $underlyingObjectClass
	 * @param mixed $underlyingObjectPrimaryKey
	 * @param ObjectRetrievalEngine $retrievalEngineInstance
	 */
	public function __construct($underlyingObjectClass = null, $underlyingObjectFieldValues = null, $engineIdentifier = null, $persistenceCoordinatorInstance = null) {
		$this->underlyingObjectClass = $underlyingObjectClass;
		$this->underlyingObjectFieldValues = $underlyingObjectFieldValues;
		$this->engineIdentifier = $engineIdentifier;
		$this->persistenceCoordinatorInstance = $persistenceCoordinatorInstance;
	}
	
	/**
	 * @return the $underlyingObjectClass
	 */
	public function getUnderlyingObjectClass() {
		return $this->underlyingObjectClass;
	}
	
	/**
	 * @return the $underlyingObjectPrimaryKey
	 */
	public function getUnderlyingObjectFieldValues() {
		return $this->underlyingObjectFieldValues;
	}
	
	/**
	 * @return the $engineIdentifier
	 */
	public function getEngineIdentifier() {
		return $this->engineIdentifier;
	}
	
	/**
	 * Set / Override the defined persistence coordinator instance for this Facade.
	 * 
	 * @param $persistenceCoordinator
	 */
	public function injectPersistenceCoordinatorInstance($persistenceCoordinatorInstance) {
		$this->persistenceCoordinatorInstance = $persistenceCoordinatorInstance;
	}
	
	/**
	 * @return the $persistenceCoordinatorInstance
	 */
	public function returnPersistenceCoordinatorInstance() {
		return $this->persistenceCoordinatorInstance;
	}
	
	/**
	 * Pull the real version of the object from the configured facade.
	 * 
	 */
	public function returnRealObject($noCache = false) {
		
		// If we have all required parameters set, pull the full version using configured information
		if ($this->underlyingObjectClass && $this->underlyingObjectFieldValues && $this->persistenceCoordinatorInstance) {
			if ($noCache || $this->cachedInstance == null) {
				$matches = $this->persistenceCoordinatorInstance->getObjectsForFieldValues ( $this->underlyingObjectClass, $this->underlyingObjectFieldValues, $this->engineIdentifier );


                if (sizeof ( $matches ) > 0) {
					$newInstance = $matches;
				} else {
					$newInstance = null;
				}
				$this->cachedInstance = $noCache ? null : $newInstance;
			} else {
				$newInstance = $this->cachedInstance;
			}
			
			return $newInstance;
		}
	
	}

}

?>