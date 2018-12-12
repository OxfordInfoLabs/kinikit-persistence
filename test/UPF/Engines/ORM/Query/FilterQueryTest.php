<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query;

use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\UPF\Engines\ORM\Query\Filters\LikeFilter;
use Kinikit\Persistence\UPF\Engines\ORM\Query\Filters\ThresholdFilter;

include_once "autoloader.php";

/**
 * Created by JetBrains PhpStorm.
 * User: Mark
 * Date: 21/03/13
 * Time: 15:36
 * To change this template use File | Settings | File Templates.
 */

/**
 * Test cases for the ORM Filter query (useful to avoid lots of elaborate looping logic in code when variable filters
 * are supplied to a query)
 *
 * Class ORMFilterQueryTest
 */
class ORMFilterQueryTest extends \PHPUnit\Framework\TestCase {

    /**
     * Test for correct where clause
     */
    public function testCorrectWhereClauseSQLIsReturnedForSimpleFiltersAndLimits() {

        $filterQuery = new FilterQuery(array("name" => "bob", "address" => "3 flat street"));
        $this->assertEquals("WHERE name='bob' AND address='3 flat street' LIMIT 10 OFFSET 0", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));


        $filterQuery = new FilterQuery(array("name" => "bob", "address" => "3 flat street",
            "tel" => "5 the row"), null, null, null, FilterQuery::FILTER_OR);
        $this->assertEquals("WHERE name='bob' OR address='3 flat street' OR tel='5 the row'", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));


        $filterQuery = new FilterQuery(array("name" => "bob", "address" => "3 flat street"));
        $this->assertEquals("WHERE name='bob' AND address='3 flat street' LIMIT 10 OFFSET 0", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));


        $filterQuery = new FilterQuery(array("name" => "bob", "tel" => "3565656"), array("name", "tel DESC"));
        $this->assertEquals("WHERE name='bob' AND tel='3565656' ORDER BY name, tel DESC LIMIT 10 OFFSET 0", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));

        $filterQuery = new FilterQuery(null, array("name", "tel DESC"));
        $this->assertEquals("ORDER BY name, tel DESC LIMIT 10 OFFSET 0", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));


        $filterQuery = new FilterQuery(array("name" => "mary"), null, 10, 5);
        $this->assertEquals("WHERE name='mary' LIMIT 10 OFFSET 40", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));

        $filterQuery = new FilterQuery(null, null, 10);
        $this->assertEquals("LIMIT 10 OFFSET 0", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));


    }


//    public function testFullSelectStatementsAreSuppliedWhenCustomTableNameIsSupplied() {
//        $filterQuery =
//            new FilterQuery(array("test" => "bingo", "goon" => "bongo"), array("home"), 20, 2, "bingochops");
//        $this->assertEquals("SELECT * FROM bingochops WHERE test='bingo' AND goon='bongo' ORDER BY home LIMIT 20 OFFSET 20", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));
//    }

    public function testWhenMultipleValuesAreSuppliedAsArrayToFiltersInClausesAreWrittenCorrectly() {

        $filterQuery = new FilterQuery(array("name" => array("bob", "mary", "jane"), "address" => "3 flat street"));
        $this->assertEquals("WHERE name IN ('bob','mary','jane') AND address='3 flat street' LIMIT 10 OFFSET 0", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));

        $filterQuery =
            new FilterQuery(array("name" => array("bob", "mary", "jane"), "pics" => array("blue", "red", "grey")));
        $this->assertEquals("WHERE name IN ('bob','mary','jane') AND pics IN ('blue','red','grey') LIMIT 10 OFFSET 0", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));

    }

//    public function testCanGetFilterOnlyClause() {
//        $filterQuery = new FilterQuery(array("name" => "bob", "tel" => "3565656"), array("name", "tel DESC"));
//        $this->assertEquals("WHERE name='bob' AND tel='3565656'", $filterQuery->getFilterOnlyClause(DefaultDB::instance()));
//
//
//        $filterQuery =
//            new FilterQuery(array("test" => "bingo", "goon" => "bongo"), array("home"), 20, 10, "bingochops");
//        $this->assertEquals("FROM bingochops WHERE test='bingo' AND goon='bongo'", $filterQuery->getFilterOnlyClause(DefaultDB::instance()));
//
//
//    }


    public function testCanSupplyOtherFilterTypes() {
        $filterQuery = new FilterQuery(array("name" => new LikeFilter("%bob%"), "address" => "3 flat street",
            "age" => new ThresholdFilter(33, array(), ThresholdFilter::LESS_THAN)));
        $this->assertEquals("WHERE name LIKE '%bob%' AND address='3 flat street' AND age < 33 LIMIT 10 OFFSET 0", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));


        $filterQuery = new FilterQuery(array("name" => new LikeFilter("%bob%", array("column1", "column2")),
            "address" => "3 flat street", "age" => new ThresholdFilter(33, array(), ThresholdFilter::LESS_THAN)));
        $this->assertEquals("WHERE (column1 LIKE '%bob%' OR column2 LIKE '%bob%') AND address='3 flat street' AND age < 33 LIMIT 10 OFFSET 0", $filterQuery->getExpandedQueryString(DefaultDB::instance(), null));


    }


}