<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 * Exception raised if no enabled engine is available for retrieving an object.
 * 
 * @author mark
 *
 */
class NoEnabledEngineException extends \Exception {
	
	/**
	 * Construct the exception
	 * 
	 */
	public function __construct($className) {
		parent::__construct ( "An attempt was made to retrieve an object of type '" . $className . "' for which no engine has been enabled." );
	}

}

?>