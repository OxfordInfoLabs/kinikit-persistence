<?php

namespace Kinikit\Persistence\ORM\Query\Filter;

use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class EqualsFilterTest extends TestCase {

    public function testEqualsFilterReturnsCorrectDataAndNegatedVersion() {

        $equals = new EqualsFilter("test", "bingo");
        $this->assertEquals("test = ?", $equals->getSQLClause());
        $this->assertEquals(["bingo"], $equals->getParameterValues());


        $equals = new EqualsFilter("test", "bingo", true);
        $this->assertEquals("test <> ?", $equals->getSQLClause());
        $this->assertEquals(["bingo"], $equals->getParameterValues());
    }

}