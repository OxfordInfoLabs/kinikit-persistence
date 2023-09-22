<?php

namespace Kinikit\Persistence\ORM;


class TestMissingMappingObject {
    private string $name;
    private TestObject $testObject;

    /**
     * @param string $name
     * @param TestObject $testObject
     */
    public function __construct($name, $testObject) {
        $this->name = $name;
        $this->testObject = $testObject;
    }

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getTestObject(): TestObject {
        return $this->testObject;
    }

    public function setTestObject(TestObject $testObject): void {
        $this->testObject = $testObject;
    }


}