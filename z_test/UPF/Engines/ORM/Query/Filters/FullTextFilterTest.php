<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;

use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";


class FullTextFilterTest extends \PHPUnit\Framework\TestCase {


    public function testIfSimpleFilterSuppliedSingleColumnMatchPerformed() {

        $filter = new FullTextFilter("mandy");
        $this->assertEquals("MATCH (markus) AGAINST ('mandy' IN BOOLEAN MODE)", $filter->evaluateFilterClause("markus", DefaultDB::instance()));


        $filter = new FullTextFilter("+john +andrew");
        $this->assertEquals("MATCH (david) AGAINST ('+john +andrew' IN BOOLEAN MODE)", $filter->evaluateFilterClause("david", DefaultDB::instance()));


    }


    public function testIfFilterSuppliedWithMultipleMatchColumnsMultiColumnMatchPerformed() {

        $filter = new FullTextFilter("mandy", array("budgy", "parrot"));
        $this->assertEquals("MATCH (budgy, parrot) AGAINST ('mandy' IN BOOLEAN MODE)", $filter->evaluateFilterClause("markus", DefaultDB::instance()));


        $filter = new FullTextFilter("+john +andrew", array("red", "black", "green"));
        $this->assertEquals("MATCH (red, black, green) AGAINST ('+john +andrew' IN BOOLEAN MODE)", $filter->evaluateFilterClause("david", DefaultDB::instance()));


    }


}