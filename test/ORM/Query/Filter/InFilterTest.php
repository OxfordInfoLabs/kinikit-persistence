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


    public function testInFilterAutomaticallyHandlesNullValuesAndCreatesAdditionalClause() {
        $filter = new InFilter("test", ["bingo", null, "bongo"]);
        $this->assertEquals("(test IN (?,?) OR test IS NULL)", $filter->getSQLClause());
        $this->assertEquals(["bingo", "bongo"], $filter->getParameterValues());

        $filter = new InFilter("test", ["bingo", null, "bongo"],true);
        $this->assertEquals("(test NOT IN (?,?) AND test IS NOT NULL)", $filter->getSQLClause());
        $this->assertEquals(["bingo", "bongo"], $filter->getParameterValues());
    }

}