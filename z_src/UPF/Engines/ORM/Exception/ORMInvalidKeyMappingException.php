<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Exception;

class ORMInvalidKeyMappingException extends \Exception {
	
	public function __construct($parentMapper, $relatedField) {
		parent::__construct ( "An invalid key mapping was supplied for a one to many relationship between '" . $parentMapper );
	}

}

?>