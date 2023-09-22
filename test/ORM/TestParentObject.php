<?php

namespace Kinikit\Persistence\ORM;

class TestParentObject {
    /**
     * @primaryKey
     * @var ?int
     */
    private ?int $id;

    /**
     * @var ?TestObject
     * @manyToOne
     */
    private ?TestObject $testObject;

    /**
     * @param ?int $id
     * @param ?TestObject $testObject
     */
    public function __construct(?int $id, ?TestObject $testObject) {
        $this->id = $id;
        $this->testObject = $testObject;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function setId(?int $id): void {
        $this->id = $id;
    }

    public function getTestObject(): ?TestObject {
        return $this->testObject;
    }

    public function setTestObject(?TestObject $testObject): void {
        $this->testObject = $testObject;
    }



}