<?php


namespace Kinikit\Persistence\TableMapper\Exception;


use Throwable;

class WrongJoinColumnsLengthException extends \Exception {

    public function __construct($message) {
        parent::__construct($message ?? "You have supplied the wrong number of join columns to a table relationship");
    }

}
