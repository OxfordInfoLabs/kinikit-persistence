<?php

namespace Kinikit\Persistence\Database\Exception;

/**
 * Exception raised if the wrong number of parameters is passed for a prepared statement.
 * 
 * @author mark
 *
 */
class WrongNumberOfPreparedStatementParametersException extends \Exception {
	
	public function __construct($numberExpected, $numberSupplied) {
		parent::__construct ( "You have supplied the wrong number of parameters to a prepared statement.  You supplied " . $numberSupplied . " parameters when " . $numberExpected . " were expected." );
	}

}

?>