<?php

namespace Kinikit\Persistence\UPF\Exception;

/**
 * Exception raised if a field relationship has been badly constructed.
 *
 * @author mark
 *
 */
class InvalidFieldRelationshipException extends \Exception {

    /**
     * Construct the exception with both parent class and persistence field arguments.
     *
     * @param string $parentClassName
     * @param string $persistenceFieldName
     */
    public function __construct($parentClassName, $persistenceFieldName) {
        parent::__construct("There has been a problem constructing the relationship for member '" . $persistenceFieldName . "' on the mapper for the class '" . $parentClassName . "'.  Please ensure that you have supplied at least a related class name");
    }

}

?>