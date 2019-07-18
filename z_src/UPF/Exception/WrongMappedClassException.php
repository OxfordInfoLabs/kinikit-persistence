<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 * Exception raised if an attempt is made to persist a class using a mapper for a different type.
 * 
 * @author mark
 *
 */
class WrongMappedClassException extends \Exception {
	
	public function __construct($expectedClass = null, $suppliedClass = null) {
		parent::__construct ( "An attempt was made to map a class of type '" . $suppliedClass . "' when the expected type was '" . $expectedClass . "'" );
	}

}

?>