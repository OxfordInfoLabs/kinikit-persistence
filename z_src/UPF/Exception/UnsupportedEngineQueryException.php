<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 * Unsupported engine query exception, raised if an unsupported query object is passed to an engine query method.
 * 
 * @author mark
 *
 */
class UnsupportedEngineQueryException extends \Exception {
	
	/**
	 * Construct with the query object
	 * 
	 * @param mixed $queryObject
	 */
	public function __construct($queryObject, $engineIdentifier) {
		parent::__construct ( "An unsupported query object of type '" . get_class ( $queryObject ) . "' was passed to the engine with identifier '" . $engineIdentifier . "'" );
	}

}

?>