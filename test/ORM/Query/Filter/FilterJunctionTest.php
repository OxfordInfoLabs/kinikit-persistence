<?php

namespace Kinikit\Persistence\ORM\Query\Filter;

use PHPUnit\Framework\TestCase;

class FilterJunctionTest extends TestCase {

    public function testParametersAndStringsReturnedCorrectlyForANDFilterJunction() {

        $filterA = new EqualsFilter("column1", "Badger");
        $filterB = new EqualsFilter("column2", "Bodger");

        $junction = new FilterJunction([$filterA, $filterB]);
        $this->assertEquals("(column1 = ?) AND (column2 = ?)", $junction->getSQLClause());
        $this->assertEquals(["Badger", "Bodger"], $junction->getParameterValues());

    }

    public function testParametersAndStringsReturnedCorrectlyForORFilterJunction() {

        $filterA = new EqualsFilter("column1", "Badger");
        $filterB = new EqualsFilter("column2", "Bodger", true);

        $junction = new FilterJunction([$filterA, $filterB], FilterJunction::LOGIC_OR);
        $this->assertEquals("(column1 = ?) OR (column2 <> ?)", $junction->getSQLClause());
        $this->assertEquals(["Badger", "Bodger"], $junction->getParameterValues());

    }

    public function testParametersAndStringsReturnedAsANDForBadFilterJunction() {

        $filterA = new EqualsFilter("column1", "Badger");
        $filterB = new EqualsFilter("column2", "Bodger", true);

        $junction = new FilterJunction([$filterA, $filterB], "WooooooHoooo");
        $this->assertEquals("(column1 = ?) AND (column2 <> ?)", $junction->getSQLClause());
        $this->assertEquals(["Badger", "Bodger"], $junction->getParameterValues());

    }


    public function testCanNestFilterJunctions() {

        $filterA = new EqualsFilter("column1", "Badger");
        $filterB = new InFilter("column2", ["Bodger", "Bidger"]);
        $filterC = new LikeFilter("column3", "Budger");
        $filterD = new EqualsFilter("column4", "Bedger");

        $filterJunctionA = new FilterJunction([$filterA, $filterB], FilterJunction::LOGIC_AND);
        $filterJunctionB = new FilterJunction([$filterC, $filterD], FilterJunction::LOGIC_AND);

        $junction = new FilterJunction([$filterJunctionA, $filterJunctionB], FilterJunction::LOGIC_OR);

        $this->assertEquals("((column1 = ?) AND (column2 IN (?,?))) OR ((column3 LIKE ?) AND (column4 = ?))", $junction->getSQLClause());
        $this->assertEquals(["Badger", "Bodger", "Bidger", "Budger", "Bedger"], $junction->getParameterValues());


    }

}