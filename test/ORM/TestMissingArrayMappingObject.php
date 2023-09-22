<?php

namespace Kinikit\Persistence\ORM;

class TestMissingArrayMappingObject {
    /**
     * @var TestObject[] $testArray
     */
    private array $testArray;

    /**
     * @param TestObject[] $testArray
     */
    public function __construct(array $testArray) {
        $this->testArray = $testArray;
    }

    public function getTestArray(): array {
        return $this->testArray;
    }

    public function setTestArray(array $testArray): void {
        $this->testArray = $testArray;
    }
}