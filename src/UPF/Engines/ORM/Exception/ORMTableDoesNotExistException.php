<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

/**
 * Exception raised if an attempt is made to map to a table which does not exist.
 * 
 * @author mark
 *
 */
class ORMTableDoesNotExistException extends \Exception {
	
	public function __construct($objectClass, $tableName) {
		parent::__construct ( "An attempt was made to read / write an object of class '" . $objectClass . "' to the database table '" . $tableName . "' which does not exist" );
	}

}

?>