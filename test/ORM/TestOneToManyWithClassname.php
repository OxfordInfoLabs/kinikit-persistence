<?php

namespace Kinikit\Persistence\ORM;

class TestOneToManyWithClassname {

    /**
     * @var integer
     */
    private $id;

    /**
     * @var TestObject
     * @oneToMany
     * @childJoinColumns id,blah=CLASSNAME
     */
    private $testObject;

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @return TestObject
     */
    public function getTestObject() {
        return $this->testObject;
    }

    /**
     * @param TestObject $testObject
     */
    public function setTestObject($testObject) {
        $this->testObject = $testObject;
    }


}