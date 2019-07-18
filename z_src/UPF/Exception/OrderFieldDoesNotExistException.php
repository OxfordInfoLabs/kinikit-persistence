<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 *
 *
 * Class ORMOrderColumnDoesNotExistException
 */
class OrderFieldDoesNotExistException extends \Exception {

    public function __construct($objectClass, $field) {

        parent::__construct("An attempt was made to order by the field '" . $field . " which does not exist on the class " . $objectClass);

    }

} 