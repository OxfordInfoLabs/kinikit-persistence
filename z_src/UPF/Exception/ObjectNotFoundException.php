<?php


namespace Kinikit\Persistence\UPF\Exception;

/**
 * Generic object not found exception.  Usually raised when a primary key lookup is requested for an object which doesn't exist.
 * 
 * @author mark
 *
 */
class ObjectNotFoundException extends \Exception {
	
	public function __construct($objectClass, $primaryKey) {
		parent::__construct ( "The object could not be found of type '" . $objectClass . "' with primary key '" . $primaryKey . "'" );
	}

}

?>