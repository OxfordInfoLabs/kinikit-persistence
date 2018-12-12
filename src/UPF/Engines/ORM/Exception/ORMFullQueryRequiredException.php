<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

/**
 * Exception raised if both an ORM Table and an ORM View SQL is defined on a mapper
 * 
 * @author mark
 *
 */
class ORMFullQueryRequiredException extends \Exception {
	
	/**
	 * Construct with the object class for which the ambiguous definition occurred.
	 * 
	 * @param unknown_type $objectClass
	 */
	public function __construct($objectClass) {
		parent::__construct ( "A full SELECT query must be supplied (not just a WHERE clause) for the object  '" . $objectClass . "' which has a mapper that has been defined with no backing object." );
	}

}

?>