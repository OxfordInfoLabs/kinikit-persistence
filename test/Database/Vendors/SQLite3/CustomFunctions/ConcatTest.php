<?php

namespace Kinikit\Persistence\Database\Vendors\SQLite3\CustomFunctions;

include_once "autoloader.php";

class ConcatTest extends \PHPUnit\Framework\TestCase {

    public function testConcatConcatenatesAllPassedArguments() {

        $concat = new Concat();
        $this->assertEquals("Hello", $concat->execute("Hello"));
        $this->assertEquals("Hello World", $concat->execute("Hello", " ", "World"));
        $this->assertEquals("12345", $concat->execute("1", "2", "3", "4", "5"));

    }

}