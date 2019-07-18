<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

/**
 * Exception raised if both an ORM Table and an ORM View SQL is defined on a mapper
 * 
 * @author mark
 *
 */
class ORMAmbiguousMapperSourceDefinitionException extends \Exception {
	
	/**
	 * Construct with the object class for which the ambiguous definition occurred.
	 * 
	 * @param unknown_type $objectClass
	 */
	public function __construct($objectClass) {
		parent::__construct ( "More than one of ormNoBackingObject,  ormTable or ormViewSQL attributes have been supplied for the mapper for the object '" . $objectClass . "'.  Please supply one or the other but not both." );
	}

}

?>