<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

/**
 * Exception raised if an attempt is made to persist to an ORM relationship table (many to many link table) which
 * does not exist. 
 * 
 * @author mark
 *
 */
class ORMRelationshipTableDoesNotExistException extends \Exception {
	
	public function __construct($parentClassName, $childClassName, $attemptedTableName) {
		parent::__construct ( "An attempt was made to read / write to the relationship table '" . $attemptedTableName . "' which does not exist for relating '" . $childClassName . "' objects to '" . $parentClassName . "'" );
	}

}

?>