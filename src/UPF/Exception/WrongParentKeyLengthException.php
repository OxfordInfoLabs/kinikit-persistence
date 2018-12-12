<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 * Exception raised if a forei key is supplied of the wrong length to a retrieval operation.
 * 
 * @author mark
 *
 */
class WrongParentKeyLengthException extends \Exception {
	
	public function __construct($parentClass, $childField, $requiredLength) {
		parent::__construct ( "The supplied parent key for member '" . $childField . "' referring to an object of type '" . $parentClass . "' is of the wrong length.  You must define it for " . $requiredLength . " values." );
	}

}

?>