<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

/**
 * Exception raised if an object configured with ORMViewSQL is attempted to be saved / removed
 * 
 * @author mark
 *
 */
class ORMObjectNotWritableException extends \Exception {
	
	/**
	 * Construct with the object class
	 * 
	 * @param unknown_type $objectClass
	 */
	public function __construct($objectClass) {
		parent::__construct ( "An attempt was made to save / remove an object of type '" . $objectClass . "' which is not writable." );
	}

}

?>