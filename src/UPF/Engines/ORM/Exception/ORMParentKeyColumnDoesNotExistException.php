<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

/**
 * Exception raised if a parent key column does not exist.
 * 
 * @author mark
 *
 */
class ORMParentKeyColumnDoesNotExistException extends \Exception {
	
	/**
	 * Construct with relevant info for exception.
	 * 
	 * @param string $parentClassName
	 * @param string $childClassName
	 * @param string $parentKeyField
	 * @param string $parentKeyColumn
	 */
	public function __construct($parentClassName, $childClassName, $parentKeyField, $parentKeyColumn) {
		parent::__construct ( "The table column '" . $parentKeyColumn . "' expected for relating '" . $parentClassName . "' to '" . $childClassName . "' objects using the field '" . $parentKeyField . "' on the class '" . $childClassName . "' does not exist" );
	}

}

?>