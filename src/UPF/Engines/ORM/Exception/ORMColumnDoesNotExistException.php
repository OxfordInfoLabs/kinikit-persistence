<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

/**
 * Exception raised if an attempt is made to write to a column on a table which does not exist.
 * 
 * @author mark
 *
 */
class ORMColumnDoesNotExistException extends \Exception {
	
	public function __construct($objectClass, $fieldName, $tableName, $columnName) {
		
		parent::__construct ( "An attempt was made to read / write the field '" . $fieldName . "' for the object '" . $objectClass . "' to the column '" . $columnName . "' on the table '" . $tableName . "' which does not exist" );
	
	}

}

?>