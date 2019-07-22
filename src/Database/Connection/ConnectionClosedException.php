<?php


namespace Kinikit\Persistence\Database\Connection;


class ConnectionClosedException extends \Exception {

    public function __construct($message = null) {
        parent::__construct($message ? $message : "The database connection has been closed.");
    }


}
