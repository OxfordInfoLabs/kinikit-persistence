<?php
namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;

use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

/**
 * Test cases for the ORM Equals Filter
 *
 * Class ORMEqualsFilterTest
 */
class EqualsFilterTest extends \PHPUnit\Framework\TestCase {

    public function testCanGetFilterClauseForSingleNumericValue() {
        $filter = new EqualsFilter(12);
        $this->assertEquals("bob='12'", $filter->evaluateFilterClause("bob", DefaultDB::instance()));

        $filter = new EqualsFilter(15);
        $this->assertEquals("mary='15'", $filter->evaluateFilterClause("mary", DefaultDB::instance()));

    }


    public function testCanGetFilterClauseForSingleString() {

        $filter = new EqualsFilter("monkey");
        $this->assertEquals("mark='monkey'", $filter->evaluateFilterClause("mark", DefaultDB::instance()));

        $filter = new EqualsFilter("Nathan's mum's friend's monkey");
        $this->assertEquals("mark='" . DefaultDB::instance()->escapeString("Nathan's mum's friend's monkey") . "'", $filter->evaluateFilterClause("mark", DefaultDB::instance()));

    }


    public function testCanGetFilterClauseForMultipleNumericValues() {
        $filter = new EqualsFilter(array(1, 3, 5, 7, 9));
        $this->assertEquals("jonah IN ('1','3','5','7','9')", $filter->evaluateFilterClause("jonah", DefaultDB::instance()));

        $filter = new EqualsFilter(array(10, 30, 50, 70, 90));
        $this->assertEquals("jonah IN ('10','30','50','70','90')", $filter->evaluateFilterClause("jonah", DefaultDB::instance()));

    }

    public function testCanGetFilterClauseForMultipleStringValues() {
        $filter = new EqualsFilter(array("Mark", "Nathan", "Philip", "Lucien"));
        $this->assertEquals("staff IN ('Mark','Nathan','Philip','Lucien')", $filter->evaluateFilterClause("staff", DefaultDB::instance()));

        $filter = new EqualsFilter(array("ain't", "won't", "can't", "shan't"));
        $this->assertEquals("slang IN ('ain''t','won''t','can''t','shan''t')", $filter->evaluateFilterClause("slang", DefaultDB::instance()));
    }

    public function testIfNullValueAnAlternativeNullOrClauseIsWritten() {

        $filter = new EqualsFilter(null);
        $this->assertEquals("miko IS NULL", $filter->evaluateFilterClause("miko", DefaultDB::instance()));

        $filter = new EqualsFilter(array("Mark", "Nathan", null, "Lucien"));
        $this->assertEquals("(staff IN ('Mark','Nathan','Lucien') OR staff IS NULL)", $filter->evaluateFilterClause("staff", DefaultDB::instance()));
    }


} 