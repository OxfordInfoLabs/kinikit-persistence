<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;

use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMNotEnoughQueryValuesException;
use Kinikit\Persistence\UPF\Engines\ORM\Query\SQLQuery;
use Kinikit\Persistence\UPF\Engines\ORM\Utils\ORMUtils;
use Kinikit\Persistence\UPF\Framework\ObjectMapper;
use Kinikit\Persistence\UPF\Framework\ObjectPersistableField;

include_once "autoloader.php";

/**
 * Test cases for the ORM SQL Query.
 *
 * @author mark
 *
 */
class ORMSQLQueryTest extends \PHPUnit\Framework\TestCase {

    public function testIfTooFewValueParametersArePassedForAQueryAnExceptionIsRaisedOnCallToGetExpandedQueryString() {

        $query = new SQLQuery ("SELECT * FROM test WHERE id = ?");
        try {
            $query->getExpandedQueryString(DefaultDB::instance(), null);
            $this->fail("Should have thrown here");
        } catch (ORMNotEnoughQueryValuesException $e) {
            // Success
        }

        $query = new SQLQuery ("SELECT * FROM test WHERE id = ? AND name = ? AND address = ?", 4, "Mark");
        try {
            $query->getExpandedQueryString(DefaultDB::instance(), null);
            $this->fail("Should have thrown here");
        } catch (ORMNotEnoughQueryValuesException $e) {
            // Success
        }

        $this->assertTrue(true);

    }

    public function testCanGetCorrectlyExpandedQueryStringUsingPassedDatabaseConnectionIfRightNumberOfParamsPassed() {

        // Firstly try one with no ? params
        $query = new SQLQuery ("SELECT * FROM test WHERE id=5");
        $this->assertEquals("SELECT * FROM test WHERE id=5", $query->getExpandedQueryString(DefaultDB::instance(), null));

        // Now try a few with different param types.
        $query = new SQLQuery ("SELECT * FROM test WHERE id = ?", 55);
        $this->assertEquals("SELECT * FROM test WHERE id = 55", $query->getExpandedQueryString(DefaultDB::instance(), null));

        $query = new SQLQuery ("SELECT * FROM test WHERE name = ?", "Mark");
        $this->assertEquals("SELECT * FROM test WHERE name = 'Mark'", $query->getExpandedQueryString(DefaultDB::instance(), null));

        $query = new SQLQuery ("SELECT * FROM test WHERE id = ? AND name = ?", 23, "Bob");
        $this->assertEquals("SELECT * FROM test WHERE id = 23 AND name = 'Bob'", $query->getExpandedQueryString(DefaultDB::instance(), null));

        $query = new SQLQuery ("SELECT * FROM rhubarb, salt WHERE people IN (?,?,?)", 12, 55, 66);
        $this->assertEquals("SELECT * FROM rhubarb, salt WHERE people IN (12,55,66)", $query->getExpandedQueryString(DefaultDB::instance(), null));

        // Now try one with a ? in the parameter value
        $query = new SQLQuery ("SELECT * FROM test WHERE id = ? AND name = ?", 145, "A ? B ? C ? D");
        $this->assertEquals("SELECT * FROM test WHERE id = 145 AND name = 'A ? B ? C ? D'", $query->getExpandedQueryString(DefaultDB::instance(), null));

    }


    public function testIfStaticTableInfoPassedThroughToQueryORMFieldsCanBePassedInQueries() {

        DefaultDB::instance()->query("DROP TABLE IF EXISTS query_test",);
        DefaultDB::instance()->query("CREATE TABLE query_test (id INTEGER, my_compound_field INTEGER, another_field VARCHAR(255), dead_field VARCHAR(255))",);

        $staticTableInfo = ORMUtils::getStaticObjectTableInfo(new ObjectMapper("Kinikit\Persistence\UPF\Engines\ORM\Query\QueryTest", array(new ObjectPersistableField("id"), new ObjectPersistableField("myCompoundField"),
                new ObjectPersistableField("anotherField"), new ObjectPersistableField("deadField"))
        ), DefaultDB::instance())["READ"];


        // Firstly try one with no ? params
        $query = new SQLQuery ("SELECT * FROM query_test WHERE myCompoundField = ? AND anotherField = ? AND deadField = ?", 3, "anotherField", "Games");
        $this->assertEquals("SELECT * FROM query_test WHERE my_compound_field = 3 AND another_field = 'anotherField' AND dead_field = 'Games'", $query->getExpandedQueryString(DefaultDB::instance(), $staticTableInfo));

    }

}

?>
