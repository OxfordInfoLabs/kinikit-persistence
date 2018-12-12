<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

/**
 * Exception raised if an attempt is made to access a one to many relationship where no key mappings have been supplied.
 * 
 * @author mark
 *
 */
class ORMNoKeyMappingsSuppliedForRelationshipException extends \Exception {
	
	/**
	 * Construct with parent object mapper and the relationship
	 * 
	 * @param ObjectMapper $parentObjectMapper
	 * @param ObjectPersistableField $field
	 */
	public function __construct($parentObjectMapper, $relatedField) {
		
		parent::__construct ( "An attempt was made to access a one-many relationship between '" . $parentObjectMapper->getClassName () . "' and '" . $relatedField->getRelationship ()->getRelatedClassName () . "' for the field '" . $relatedField->getFieldName () . "' where there are no key mappings defined" );
	
	}

}

?>