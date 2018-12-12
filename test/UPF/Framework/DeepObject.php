<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\SerialisableObject;

/**
 * Created by JetBrains PhpStorm.
 * User: mark
 * Date: 10/09/2012
 * Time: 11:06
 * To change this template use File | Settings | File Templates.
 */
class DeepObject extends SerialisableObject {

    private $id;
    private $subObjectId;
    private $subObjectId2;

    /**
     * @unmapped
     */
    private $subObject;

    /**
     * @unmapped
     */
    private $subObject2;


    /**
     * Construct a new deep object
     *
     * @param null $id
     * @param null $subObject
     */
    public function __construct($subObject = null, $subObject2 = null) {
        $this->subObject = $subObject;
        $this->subObject2 = $subObject2;
    }


    public function setId($id) {
        $this->id = $id;
    }

    public function getId() {
        return $this->id;
    }

    public function setSubObject($subObject) {
        $this->subObject = $subObject;
    }

    public function getSubObject() {
        return $this->subObject;
    }

    public function setSubObjectId($subObjectId) {
        $this->subObjectId = $subObjectId;
    }

    public function getSubObjectId() {
        return $this->subObjectId;
    }

    public function setSubObjectId2($subObjectId2) {
        $this->subObjectId2 = $subObjectId2;
    }

    public function getSubObjectId2() {
        return $this->subObjectId2;
    }

    /**
     * @param mixed $subObject2
     */
    public function setSubObject2($subObject2) {
        $this->subObject2 = $subObject2;
    }

    /**
     * @return mixed
     */
    public function getSubObject2() {
        return $this->subObject2;
    }


}
