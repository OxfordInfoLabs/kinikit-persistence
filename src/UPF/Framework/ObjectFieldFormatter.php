<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\SerialisableObject;

/**
 * Base abstract class which provides the basic functionality and interface for 
 * a field formatter.  These are defined with an identifier as a minimum and 
 * possibly other configuration fields as required by the sub class.
 * 
 * A formatter may be attached to any mapper field using the formatter attribute.  A formatter 
 * must implement as a minimum the two abstract functions below:
 * 
 * format - format the value returned from the in use engine before binding.
 * unformat - restore a value to the original engine format before saving / deleting.
 * 
 * @author oxil
 *
 */
abstract class ObjectFieldFormatter extends SerialisableObject {
	
	private $identifier;
	
	/**
	 * Persistence engine constructed with an optional identifier.
	 * 
	 * @param string $identifier
	 */
	public function __construct($identifier = null) {
		$this->identifier = $identifier;
	}
	
	/**
	 * @return the $identifier
	 */
	public function getIdentifier() {
		return $this->identifier;
	}
	
	/**
	 * @param $identifier the $identifier to set
	 */
	public function setIdentifier($identifier) {
		$this->identifier = $identifier;
	}
	
	/**
	 * Format a value returned from an in use engine following a query 
	 * 
	 * @param mixed $sourceValue
	 */
	public abstract function format($unformattedValue);
	
	/**
	 * Unformat an object value into engine format before saving / removing the object.
	 * 
	 * @param mixed $objectValue
	 */
	public abstract function unformat($formattedValue);

}

?>