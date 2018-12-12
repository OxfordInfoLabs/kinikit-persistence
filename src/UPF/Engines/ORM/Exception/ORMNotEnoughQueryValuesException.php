<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

/**
 * Exception raised if an SQL query is constructed with more ? parameters supplied in the SQL than 
 * actual values passed.
 * 
 * @author mark
 *
 */
class ORMNotEnoughQueryValuesException extends \Exception {
	
	public function __construct($sql) {
		parent::__construct ( "Not enough values were supplied to the ORM Query for the query '" . $sql . "'" );
	}

}

?>