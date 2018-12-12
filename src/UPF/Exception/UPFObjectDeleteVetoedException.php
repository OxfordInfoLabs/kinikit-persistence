<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 * Exception raised if an attempt is made to delete an object which had an intercepter returning false.
 * 
 * @author matthew
 *
 */
class UPFObjectDeleteVetoedException extends \Exception {
	
	public function __construct($objectType) {
		parent::__construct ( "An attempt was made to delete an object of type '" . $objectType . "' which was vetoed by an interceptor" );
	}

}

?>