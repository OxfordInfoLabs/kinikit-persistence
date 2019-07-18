<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

/**
 * Exception raised if an attempt is made to write to a relationship table for which a none existent column exists.
 * 
 * @author mark
 *
 */
class ORMRelationshipColumnDoesNotExistException extends \Exception {
	
	/**
	 * Construct the exception with required data
	 * 
	 * @param string $parentClassName
	 * @param string $childClassName
	 * @param string $relationshipTableName
	 * @param string $attemptedColumnName
	 */
	public function __construct($parentClassName, $childClassName, $relationshipTableName, $attemptedColumnName) {
		parent::__construct ( "An attempt was made to read / write to the column '" . $attemptedColumnName . "' on the relationship table '" . $relationshipTableName . "' for relating '" . $childClassName . "' objects to '" . $parentClassName . "' which does not exist." );
	}

}

?>