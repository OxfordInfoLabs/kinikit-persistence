<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 * Exception raised if an attempt is made to access a persistence engine which does not exist
 * 
 * @author mark
 *
 */
class NoneExistentEngineException extends \Exception {
	
	/**
	 * Construct with the attempted engine identifier.
	 * 
	 * @param string $engineIdentifier
	 */
	public function __construct($engineIdentifier) {
		parent::__construct ( "An attempt was made to access the persistence engine with identifier'" . $engineIdentifier . "' which does not exist" );
	}

}

?>