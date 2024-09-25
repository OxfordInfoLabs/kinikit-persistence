<?php


namespace Kinikit\Persistence\TableMapper\Exception;


class WrongPrimaryKeyLengthException extends \Exception {

    public function __construct($tableName, $primaryKey, $expectedValues) {
        $pk = join(", ", $primaryKey);
        parent::__construct("The primary key ($pk) supplied is the wrong length for table $tableName.  Expected $expectedValues values");
    }

}
