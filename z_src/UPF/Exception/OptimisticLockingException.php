<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 * Exception raised if an optimistic lock occurs.  
 * 
 * @author mark
 *
 */
class OptimisticLockingException extends \Exception {
	
	/**
	 * Construct the exception with information about the locked object
	 * 
	 * @param $className
	 * @param $primaryKey
	 */
	public function __construct($className, $primaryKey) {
		parent::__construct ( "An attempt was made to save an object of type '" . $className . "' with primary key '" . $primaryKey . "' which has been changed by another user" );
	}

}

?>