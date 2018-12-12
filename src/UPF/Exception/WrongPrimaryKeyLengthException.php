<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 * Exception raised if a priamry key is supplied of the wrong length to a retrieval operation.
 * 
 * @author mark
 *
 */
class WrongPrimaryKeyLengthException extends \Exception {
	
	public function __construct($objectClass, $requiredLength) {
		parent::__construct ( "The supplied primary key is the wrong length for an object of type '" . $objectClass . "'.  You must supply " . $requiredLength . " values" );
	}

}

?>