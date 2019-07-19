<?php


namespace Kinikit\Persistence\Database\Connection;


use Throwable;

class InvalidDatabaseConfigurationException extends \Exception {


    public function __construct($message) {
        parent::__construct($message);
    }


}
