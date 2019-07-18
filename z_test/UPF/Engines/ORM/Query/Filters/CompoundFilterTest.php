<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;

use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

/**
 * Created by PhpStorm.
 * User: mark
 * Date: 16/02/16
 * Time: 13:22
 */
class CompoundFilterTest extends \PHPUnit\Framework\TestCase {

    public function testAndLogicCompoundFilterCorrectlyEvaluates() {


        $equalsFilter1 = new EqualsFilter("myshipsailed", "quotation");
        $equalsFilter2 = new EqualsFilter("author", "Mary");

        $compoundFilter = new CompoundFilter(array($equalsFilter1, $equalsFilter2));

        $this->assertEquals("(quotation='myshipsailed') AND (Mary='author')", $compoundFilter->evaluateAllFilterClauses("test", DefaultDB::instance()));


        // Try one with a compound array filter
        $equalsFilter3 = new EqualsFilter(array(10, 11, 12), "age");
        $compoundFilter = new CompoundFilter(array($equalsFilter1, $equalsFilter3));

        $this->assertEquals("(quotation='myshipsailed') AND (age IN ('10','11','12'))", $compoundFilter->evaluateAllFilterClauses("test", DefaultDB::instance()));


    }


    public function testOrLogicCompoundFilterCorrectlyEvaluates() {


        $equalsFilter1 = new EqualsFilter("myshipsailed", "quotation");
        $equalsFilter2 = new EqualsFilter("author", "Mary");

        $compoundFilter = new CompoundFilter(array($equalsFilter1, $equalsFilter2), CompoundFilter::LOGIC_OR);

        $this->assertEquals("(quotation='myshipsailed') OR (Mary='author')", $compoundFilter->evaluateAllFilterClauses("test", DefaultDB::instance()));

    }

}