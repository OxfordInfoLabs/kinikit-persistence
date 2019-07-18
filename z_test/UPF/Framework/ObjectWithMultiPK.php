<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Object\SerialisableObject;

class ObjectWithMultiPK extends SerialisableObject {

    private $element1;
    private $element2;
    private $element3;
    private $message;

    function __construct($element1 = null, $element2 = null, $element3 = null, $message = null) {
        $this->element1 = $element1;
        $this->element2 = $element2;
        $this->element3 = $element3;
        $this->message = $message;
    }


    /**
     * @param mixed $element1
     */
    public function setElement1($element1) {
        $this->element1 = $element1;
    }

    /**
     * @return mixed
     */
    public function getElement1() {
        return $this->element1;
    }

    /**
     * @param mixed $element2
     */
    public function setElement2($element2) {
        $this->element2 = $element2;
    }

    /**
     * @return mixed
     */
    public function getElement2() {
        return $this->element2;
    }

    /**
     * @param mixed $element3
     */
    public function setElement3($element3) {
        $this->element3 = $element3;
    }

    /**
     * @return mixed
     */
    public function getElement3() {
        return $this->element3;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message) {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getMessage() {
        return $this->message;
    }


}