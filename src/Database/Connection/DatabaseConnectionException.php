<?php

namespace Kinikit\Persistence\Database\Connection;

/**
 * Database connection exception
 *
 */
class DatabaseConnectionException extends \Exception {

    public function __construct($message = null) {
        parent::__construct($message ?: "There was a problem connecting to the database.");
    }

}

?>
