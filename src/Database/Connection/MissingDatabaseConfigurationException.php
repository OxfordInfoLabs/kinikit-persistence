<?php


namespace Kinikit\Persistence\Database\Connection;


use Throwable;

class MissingDatabaseConfigurationException extends \Exception {


    public function __construct($key = null) {
        if ($key) {
            parent::__construct("No database configuration exists for the key $key either in the configuration file or explicitly");
        } else {
            parent::__construct("No default database configuration exists");
        }
    }


}
