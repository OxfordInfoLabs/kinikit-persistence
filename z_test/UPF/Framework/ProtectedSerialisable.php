<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Object\SerialisableObject;

/**
 * Protected Serialisable object
 *
 * @author mark
 *
 */
class ProtectedSerialisable extends SerialisableObject {

    private $mother;
    protected $father;
    protected $brother;

    /**
     * Construct with initial values
     *
     * @param unknown_type $mother
     * @param unknown_type $father
     * @param unknown_type $brother
     */
    public function __construct($mother = null, $father = null, $brother = null) {
        $this->mother = $mother;
        $this->father = $father;
        $this->brother = $brother;
    }

    // Protected setter
    protected function setMother($mother) {
        $this->mother = $mother;
    }

    // Protected setter for brother.  Prepend a string.
    protected function setBrother($brother) {
        $this->brother = "Brother:" . $brother;
    }

    protected function getMother() {
        return $this->mother;
    }

    public function toString() {
        return $this->mother . "," . $this->father . "," . $this->brother;
    }

}

?>
