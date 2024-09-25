<?php


namespace Kinikit\Persistence\Database\Connection;


class InvalidDatabaseConfigurationException extends \Exception {


    public function __construct($message) {
        parent::__construct($message);
    }


}
