<?php
namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;

use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

/**
 * Test cases for the ORM Equals Filter
 *
 * Class ORMEqualsFilterTest
 */
class NotEqualsFilterTest extends \PHPUnit\Framework\TestCase {

    public function testCanGetFilterClauseForSingleNumericValue() {
        $filter = new NotEqualsFilter(12);
        $this->assertEquals("bob <> '12'", $filter->evaluateFilterClause("bob", DefaultDB::instance()));

        $filter = new NotEqualsFilter(15);
        $this->assertEquals("mary <> '15'", $filter->evaluateFilterClause("mary", DefaultDB::instance()));

    }


    public function testCanGetFilterClauseForSingleString() {

        $filter = new NotEqualsFilter("monkey");
        $this->assertEquals("mark <> 'monkey'", $filter->evaluateFilterClause("mark", DefaultDB::instance()));

        $filter = new NotEqualsFilter("Nathan's mum's friend's monkey");
        $this->assertEquals("mark <> '" . DefaultDB::instance()->escapeString("Nathan's mum's friend's monkey") . "'", $filter->evaluateFilterClause("mark", DefaultDB::instance()));

    }


    public function testCanGetFilterClauseForMultipleNumericValues() {
        $filter = new NotEqualsFilter(array(1, 3, 5, 7, 9));
        $this->assertEquals("jonah NOT IN ('1','3','5','7','9')", $filter->evaluateFilterClause("jonah", DefaultDB::instance()));

        $filter = new NotEqualsFilter(array(10, 30, 50, 70, 90));
        $this->assertEquals("jonah NOT IN ('10','30','50','70','90')", $filter->evaluateFilterClause("jonah", DefaultDB::instance()));

    }

    public function testCanGetFilterClauseForMultipleStringValues() {
        $filter = new NotEqualsFilter(array("Mark", "Nathan", "Philip", "Lucien"));
        $this->assertEquals("staff NOT IN ('Mark','Nathan','Philip','Lucien')", $filter->evaluateFilterClause("staff", DefaultDB::instance()));

        $filter = new NotEqualsFilter(array("ain't", "won't", "can't", "shan't"));
        $this->assertEquals("slang NOT IN ('ain''t','won''t','can''t','shan''t')", $filter->evaluateFilterClause("slang", DefaultDB::instance()));
    }

    public function testIfNullValueAnAlternativeNullOrClauseIsWritten() {

        $filter = new NotEqualsFilter(null);
        $this->assertEquals("miko IS NOT NULL", $filter->evaluateFilterClause("miko", DefaultDB::instance()));

        $filter = new NotEqualsFilter(array("Mark", "Nathan", null, "Lucien"));
        $this->assertEquals("(staff NOT IN ('Mark','Nathan','Lucien') AND staff IS NOT NULL)", $filter->evaluateFilterClause("staff", DefaultDB::instance()));
    }



}