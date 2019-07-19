<?php

namespace Kinikit\Persistence\TableMapper\Exception;

use Kinikit\Core\Exception\StatusException;

class PrimaryKeyRowNotFoundException extends StatusException {

    public function __construct($tableName, $primaryKey, $multipleRows = false) {
        $pk = join(", ", $primaryKey);
        if ($multipleRows) {
            parent::__construct("One or more database rows cannot be found for table $tableName with supplied multiple keys", 404);
        } else {
            parent::__construct("The database row cannot be found for table $tableName with primary key ($pk)", 404);
        }
    }

}
