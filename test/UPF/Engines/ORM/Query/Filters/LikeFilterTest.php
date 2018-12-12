<?php
namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;

use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

/**
 * Test cases for the ORM Equals Filter
 *
 * Class ORMEqualsFilterTest
 */
class LikeFilterTest extends \PHPUnit\Framework\TestCase {


    public function testCanGetFilterClauseForSingleLikeString() {

        $filter = new LikeFilter("monkey%");
        $this->assertEquals("mark LIKE 'monkey%'", $filter->evaluateFilterClause("mark", DefaultDB::instance()));

        $filter = new LikeFilter("john%ain't%mine");
        $this->assertEquals("claire LIKE '" . DefaultDB::instance()->escapeString("john%ain't%mine") . "'", $filter->evaluateFilterClause("claire", DefaultDB::instance()));

        // Also allow * wildcards
        $filter = new LikeFilter("*monkey*");
        $this->assertEquals("mark LIKE '%monkey%'", $filter->evaluateFilterClause("mark", DefaultDB::instance()));
    }


    public function testCanGetFilterClauseForMultipleLikeValues() {
        $filter = new LikeFilter(array("Mark%", "%Nathan", "%Philip%", "%Lucien%"));
        $this->assertEquals("(staff LIKE 'Mark%' OR staff LIKE '%Nathan' OR staff LIKE '%Philip%' OR staff LIKE '%Lucien%')", $filter->evaluateFilterClause("staff", DefaultDB::instance()));

        $filter = new LikeFilter(array("ain't%", "%won't", "%can't%", "%shan't%"));
        $this->assertEquals("(slang LIKE 'ain''t%' OR slang LIKE '%won''t' OR slang LIKE '%can''t%' OR slang LIKE '%shan''t%')", $filter->evaluateFilterClause("slang", DefaultDB::instance()));
    }


    public function testIfNullValueAnAlternativeNullOrClauseIsWritten() {

        $filter = new LikeFilter(null);
        $this->assertEquals("miko IS NULL", $filter->evaluateFilterClause("miko", DefaultDB::instance()));

        $filter = new LikeFilter(array("Mark%", null, "%Philip%", "%Lucien%"));
        $this->assertEquals("(staff LIKE 'Mark%' OR staff LIKE '%Philip%' OR staff LIKE '%Lucien%' OR staff IS NULL)", $filter->evaluateFilterClause("staff", DefaultDB::instance()));

    }


} 