<?php

namespace Kinikit\Persistence\Database\Util;

use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

/**
 * Test cases for the Batching Bulk Row Inserter
 *
 * @author mark
 *
 */
class BatchingBulkRowReplacerTest extends \PHPUnit\Framework\TestCase {

    private $connection;

    public function setUp() {

        $this->connection = DefaultDB::instance();
        $this->connection->query("DROP TABLE IF EXISTS test_data");
        $this->connection->query("CREATE TABLE test_data (id INTEGER, data VARCHAR(1000) NOT NULL, PRIMARY KEY (id))");
    }

    public function testCanAddSingleRowsToBatchAndTheseAreCommittedOnceBatchSizeIsReached() {

        $batchingBulkRowReplacer = new BatchingBulkRowReplacer ($this->connection, "test_data", array("id", "data"), array(0), 5);

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $batchingBulkRowReplacer->addRow(array(1, "Bob"));
        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $batchingBulkRowReplacer->addRow(array(2, "Mary"));
        $batchingBulkRowReplacer->addRow(array(3, "Paul"));
        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $batchingBulkRowReplacer->addRow(array(4, "Pedro"));
        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $batchingBulkRowReplacer->addRow(array(5, "Bonzo"));

        // Check all batch committed
        $this->assertEquals(5, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Bob", $this->connection->queryForSingleValue("SELECT data from test_data where id = 1"));
        $this->assertEquals("Mary", $this->connection->queryForSingleValue("SELECT data from test_data where id = 2"));
        $this->assertEquals("Paul", $this->connection->queryForSingleValue("SELECT data from test_data where id = 3"));
        $this->assertEquals("Pedro", $this->connection->queryForSingleValue("SELECT data from test_data where id = 4"));
        $this->assertEquals("Bonzo", $this->connection->queryForSingleValue("SELECT data from test_data where id = 5"));

        // Add another replacement batch
        $batchingBulkRowReplacer->addRow(array(2, "Amy"));
        $batchingBulkRowReplacer->addRow(array(3, "Ben"));
        $batchingBulkRowReplacer->addRow(array(4, "Mark"));
        $batchingBulkRowReplacer->addRow(array(5, "Philip"));
        $this->assertEquals(5, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));

        $batchingBulkRowReplacer->addRow(array(6, "Lucien"));

        // Check second batch committed
        $this->assertEquals(6, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Bob", $this->connection->queryForSingleValue("SELECT data from test_data where id = 1"));
        $this->assertEquals("Amy", $this->connection->queryForSingleValue("SELECT data from test_data where id = 2"));
        $this->assertEquals("Ben", $this->connection->queryForSingleValue("SELECT data from test_data where id = 3"));
        $this->assertEquals("Mark", $this->connection->queryForSingleValue("SELECT data from test_data where id = 4"));
        $this->assertEquals("Philip", $this->connection->queryForSingleValue("SELECT data from test_data where id = 5"));
        $this->assertEquals("Lucien", $this->connection->queryForSingleValue("SELECT data from test_data where id = 6"));

    }

    public function testCanAddMultipleRowsToBatchAndBatchIsCommittedOnceReached() {

        $batchingBulkRowReplacer = new BatchingBulkRowReplacer ($this->connection, "test_data", array("id", "data"), array(0), 5);

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));

        // Add 4 and check that we are not yet committed
        $batchingBulkRowReplacer->addRows(array(array(1, "Monday"), array(2, "Tuesday"), array(3, "Wednesday"), array(4, "Thursday")));

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));

        // Add 4 more and check
        $batchingBulkRowReplacer->addRows(array(array(3, "Friday"), array(4, "Saturday"), array(5, "Sunday"), array(6, "January")));

        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Monday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 1"));
        $this->assertEquals("Tuesday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 2"));
        $this->assertEquals("Friday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 3"));
        $this->assertEquals("Thursday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 4"));

        $batchingBulkRowReplacer->addRows(array(array(7, "February"), array(8, "March")));

        $this->assertEquals(8, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Monday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 1"));
        $this->assertEquals("Tuesday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 2"));
        $this->assertEquals("Friday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 3"));
        $this->assertEquals("Saturday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 4"));
        $this->assertEquals("Sunday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 5"));
        $this->assertEquals("January", $this->connection->queryForSingleValue("SELECT data from test_data where id = 6"));
        $this->assertEquals("February", $this->connection->queryForSingleValue("SELECT data from test_data where id = 7"));
        $this->assertEquals("March", $this->connection->queryForSingleValue("SELECT data from test_data where id = 8"));

    }

    public function testCanCommitResidualItemsManuallyIfRequired() {

        $batchingBulkRowReplacer = new BatchingBulkRowReplacer ($this->connection, "test_data", array("id", "data"), array(0), 5);

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));

        $batchingBulkRowReplacer->addRows(array(array(1, "Monday"), array(2, "Tuesday"), array(3, "Wednesday"), array(4, "Thursday")));
        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));

        $batchingBulkRowReplacer->commitBatch();
        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Monday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 1"));
        $this->assertEquals("Tuesday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 2"));
        $this->assertEquals("Wednesday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 3"));
        $this->assertEquals("Thursday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 4"));

        $batchingBulkRowReplacer->addRows(array(array(5, "Friday"), array(6, "Saturday"), array(7, "Sunday"), array(8, "January")));

        $batchingBulkRowReplacer->commitBatch();

        $this->assertEquals(8, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Monday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 1"));
        $this->assertEquals("Tuesday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 2"));
        $this->assertEquals("Wednesday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 3"));
        $this->assertEquals("Thursday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 4"));
        $this->assertEquals("Friday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 5"));
        $this->assertEquals("Saturday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 6"));
        $this->assertEquals("Sunday", $this->connection->queryForSingleValue("SELECT data from test_data where id = 7"));
        $this->assertEquals("January", $this->connection->queryForSingleValue("SELECT data from test_data where id = 8"));

    }

}

?>