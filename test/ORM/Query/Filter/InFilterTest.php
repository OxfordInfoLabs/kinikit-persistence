<?php

namespace Kinikit\Persistence\ORM\Query\Filter;

use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class InFilterTest extends TestCase {

    public function testInFilterReturnsCorrectDataAndNegatedVersion() {

        $equals = new InFilter("test", ["bingo", "bongo"]);
        $this->assertEquals("test IN (?,?)", $equals->getSQLClause());
        $this->assertEquals(["bingo", "bongo"], $equals->getParameterValues());

        $equals = new InFilter("test", ["bingo", "bongo"], true);
        $this->assertEquals("test NOT IN (?,?)", $equals->getSQLClause());
        $this->assertEquals(["bingo", "bongo"], $equals->getParameterValues());
    }

}