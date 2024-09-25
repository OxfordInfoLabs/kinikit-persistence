<?php


namespace Kinikit\Persistence\TableMapper\Exception;


class WrongJoinColumnsLengthException extends \Exception {

    public function __construct($message) {
        parent::__construct($message ?? "You have supplied the wrong number of join columns to a table relationship");
    }

}
