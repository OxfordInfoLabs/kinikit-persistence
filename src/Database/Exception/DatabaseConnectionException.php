<?php

namespace Kinikit\Persistence\Database\Exception;

/**
 * Database connection exception
 *
 */
class DatabaseConnectionException extends \Exception {
	
	public function __construct($databaseType) {
		parent::__construct ( "There was a problem connecting to a database of type '" . $databaseType . "'. Please contact the IT team." );
	}

}

?>