<?php
namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;

use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

class ThresholdFilterTest extends \PHPUnit\Framework\TestCase {

    public function testCanGetGTFilter() {

        $filter = new ThresholdFilter(25, array(), ThresholdFilter::GREATER_THAN);
        $this->assertEquals("bobby > 25", $filter->evaluateFilterClause("bobby", DefaultDB::instance()));

        $filter = new ThresholdFilter(40, array(), ThresholdFilter::GREATER_THAN);
        $this->assertEquals("monkey > 40", $filter->evaluateFilterClause("monkey", DefaultDB::instance()));

        $filter = new ThresholdFilter('2012-01-12', array(), ThresholdFilter::GREATER_THAN);
        $this->assertEquals("monkey > '2012-01-12'", $filter->evaluateFilterClause("monkey", DefaultDB::instance()));

    }


    public function testCanGetGTEFilter() {

        $filter = new ThresholdFilter(25, array(), ThresholdFilter::GREATER_THAN_OR_EQUAL);
        $this->assertEquals("bobby >= 25", $filter->evaluateFilterClause("bobby", DefaultDB::instance()));

        $filter = new ThresholdFilter(40, array(), ThresholdFilter::GREATER_THAN_OR_EQUAL);
        $this->assertEquals("monkey >= 40", $filter->evaluateFilterClause("monkey", DefaultDB::instance()));

        $filter = new ThresholdFilter('2012-01-12', array(), ThresholdFilter::GREATER_THAN_OR_EQUAL);
        $this->assertEquals("monkey >= '2012-01-12'", $filter->evaluateFilterClause("monkey", DefaultDB::instance()));


    }

    public function testCanGetLTFilter() {

        $filter = new ThresholdFilter(13, array(), ThresholdFilter::LESS_THAN);
        $this->assertEquals("pickle < 13", $filter->evaluateFilterClause("pickle", DefaultDB::instance()));

        $filter = new ThresholdFilter(-12, array(), ThresholdFilter::LESS_THAN);
        $this->assertEquals("pook < -12", $filter->evaluateFilterClause("pook", DefaultDB::instance()));

        $filter = new ThresholdFilter('2012-01-12', array(), ThresholdFilter::LESS_THAN);
        $this->assertEquals("monkey < '2012-01-12'", $filter->evaluateFilterClause("monkey", DefaultDB::instance()));


    }

    public function testCanGetLTEFilter() {

        $filter = new ThresholdFilter(25, array(), ThresholdFilter::LESS_THAN_OR_EQUAL);
        $this->assertEquals("bobby <= 25", $filter->evaluateFilterClause("bobby", DefaultDB::instance()));

        $filter = new ThresholdFilter(40, array(), ThresholdFilter::LESS_THAN_OR_EQUAL);
        $this->assertEquals("monkey <= 40", $filter->evaluateFilterClause("monkey", DefaultDB::instance()));

        $filter = new ThresholdFilter('2012-01-12', array(), ThresholdFilter::LESS_THAN_OR_EQUAL);
        $this->assertEquals("monkey <= '2012-01-12'", $filter->evaluateFilterClause("monkey", DefaultDB::instance()));

    }


    public function testCanConvertDatesIfDateFormatSupplied() {
        $filter = new ThresholdFilter('02/01/2013', array(), ThresholdFilter::LESS_THAN_OR_EQUAL, "d/m/Y");
        $this->assertEquals("monkey <= '2013-01-02'", $filter->evaluateFilterClause("monkey", DefaultDB::instance()));

        $filter = new ThresholdFilter('09/12/2019', array(), ThresholdFilter::LESS_THAN_OR_EQUAL, "d/m/Y");
        $this->assertEquals("monkey <= '2019-12-09'", $filter->evaluateFilterClause("monkey", DefaultDB::instance()));

    }

}