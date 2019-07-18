<?php

namespace Kinikit\Persistence\UPF\Framework;

class NewObjectWithIdExtended extends NewObjectWithId {

    public function __construct($name = null, $postcode = null, $mobile = null, $id = null) {
        parent::__construct($name, $postcode, $mobile, $id);
    }

}

?>