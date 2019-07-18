<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 * Exception raised if an invalid interceptor is added to the collection of interceptors within the interceptor evaluator.
 * 
 * @author matthew
 *
 */
class InvalidObjectInterceptorException extends \Exception {
	
	public function __construct($className) {
		parent::__construct ( "An attempt was made to add an object interceptor of class '" . $className . "' which does not extend UPFObjectInterceptorBase" );
	}

}

?>