<?php

namespace Kinikit\Persistence\ORM\Exception;

use Kinikit\Core\Exception\ItemNotFoundException;

class ObjectNotFoundException extends ItemNotFoundException {

    public function __construct($className, $primaryKey, $multipleRows = false) {
        $pk = is_array($primaryKey) ? join(", ", $primaryKey) : $primaryKey;
        if ($multipleRows) {
            parent::__construct("One or more objects cannot be found of type $className with supplied multiple keys", 404);
        } else {
            parent::__construct("The object cannot be found of type $className with primary key ($pk)", 404);
        }
    }

}
