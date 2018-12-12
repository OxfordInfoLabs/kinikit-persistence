<?php

namespace Kinikit\Persistence\UPF\Framework;

class ContactVariant extends Contact {

	public function __construct ( $id = null, $name = null, $telephone = null, $address = null, $friends = null ) {
		parent::__construct($id, $name, $telephone, $address, $friends);
	}


}

?>