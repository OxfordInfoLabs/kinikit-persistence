<?php

namespace Kinikit\Persistence\TableMapper\Exception;

use Kinikit\Core\Exception\StatusException;

class PrimaryKeyRowNotFoundException extends StatusException {

    public function __construct($tableName, $primaryKey) {
        $pk = join(", ", $primaryKey);
        parent::__construct("The database row cannot be found for table $tableName with primary key ($pk)", 404);
    }

}
