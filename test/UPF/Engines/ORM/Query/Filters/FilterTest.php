<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;

use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

/**
 * Test generic functionality of ORM filter
 *
 * Class ORMFilterTest
 */
class FilterTest extends \PHPUnit\Framework\TestCase {

    public function testIfNoFilterColumnsDefinedEvaluateAllFilterClausesSimplyCallsTheAbstractMethodPassingTheFilterAsColumn() {

        $dbConnection = DefaultDB::instance();

        $filter = new EqualsFilter("marko");
        $this->assertEquals($filter->evaluateFilterClause("marko", $dbConnection), $filter->evaluateAllFilterClauses("marko", $dbConnection));

        $filter = new EqualsFilter(99);
        $this->assertEquals($filter->evaluateFilterClause("marko", $dbConnection), $filter->evaluateAllFilterClauses("marko", $dbConnection));

    }


    public function testIfSingleFilterColumnSuppliedThisIsUsedInLieuOfFilterNameWhenEvaluatingAllFilterClauses() {
        $dbConnection = DefaultDB::instance();

        $filter = new EqualsFilter("marko", array("bing"));
        $this->assertEquals($filter->evaluateFilterClause("bing", $dbConnection), $filter->evaluateAllFilterClauses("marko", $dbConnection));

        $filter = new EqualsFilter(99, array("bong"));
        $this->assertEquals($filter->evaluateFilterClause("bong", $dbConnection), $filter->evaluateAllFilterClauses("marko", $dbConnection));

    }


    public function testIfMultipleFilterColumnsSuppliedTheseAreLogicallyOredToAllowForMultipleColumnSearch() {
        $dbConnection = DefaultDB::instance();

        $filter = new EqualsFilter("marko", array("bing", "bong", "bang"));

        $result =
            "(" . $filter->evaluateFilterClause("bing", $dbConnection) . " OR " . $filter->evaluateFilterClause("bong", $dbConnection) . " OR " . $filter->evaluateFilterClause("bang", $dbConnection) . ")";

        $this->assertEquals($result, $filter->evaluateAllFilterClauses("marko", $dbConnection));


        $filter = new EqualsFilter("marko", array("mark", "luke"));

        $result =
            "(" . $filter->evaluateFilterClause("mark", $dbConnection) . " OR " . $filter->evaluateFilterClause("luke", $dbConnection) . ")";

        $this->assertEquals($result, $filter->evaluateAllFilterClauses("marko", $dbConnection));


    }


} 