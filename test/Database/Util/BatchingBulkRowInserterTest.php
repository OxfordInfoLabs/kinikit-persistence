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
class BatchingBulkRowInserterTest extends \PHPUnit\Framework\TestCase {
	
	private $connection;
	
	public function setUp() {
		
		$this->connection = DefaultDB::instance ();
		$this->connection->query ( "DROP TABLE IF EXISTS test_data" );
		$this->connection->query ( "CREATE TABLE test_data (id INTEGER, data VARCHAR(1000) NOT NULL) " );

		$this->connection->query("DROP TABLE IF EXISTS test_data_with_pk");
		$this->connection->query ( "CREATE TABLE test_data_with_pk (id INTEGER, data VARCHAR(1000), PRIMARY KEY(id)) " );

	}
	
	public function testCanAddSingleRowsToBatchAndTheseAreCommittedOnceBatchSizeIsReached() {
		
		$batchingBulkRowInserter = new BatchingBulkRowInserter ( $this->connection, "test_data", array ("id", "data" ), 5 );
		
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		$batchingBulkRowInserter->addRow ( array (1, "Bob" ) );
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		$batchingBulkRowInserter->addRow ( array (2, "Mary" ) );
		$batchingBulkRowInserter->addRow ( array (3, "Paul" ) );
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		$batchingBulkRowInserter->addRow ( array (4, "Pedro" ) );
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		$batchingBulkRowInserter->addRow ( array (5, "Bonzo" ) );
		
		// Check all batch committed
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		$this->assertEquals ( "Bob", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 1" ) );
		$this->assertEquals ( "Mary", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 2" ) );
		$this->assertEquals ( "Paul", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 3" ) );
		$this->assertEquals ( "Pedro", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 4" ) );
		$this->assertEquals ( "Bonzo", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 5" ) );
		
		// Add another batch
		$batchingBulkRowInserter->addRow ( array (6, "Amy" ) );
		$batchingBulkRowInserter->addRow ( array (7, "Ben" ) );
		$batchingBulkRowInserter->addRow ( array (8, "Mark" ) );
		$batchingBulkRowInserter->addRow ( array (9, "Philip" ) );
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		
		$batchingBulkRowInserter->addRow ( array (10, "Lucien" ) );
		
		// Check second batch committed
		$this->assertEquals ( 10, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		$this->assertEquals ( "Bob", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 1" ) );
		$this->assertEquals ( "Mary", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 2" ) );
		$this->assertEquals ( "Paul", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 3" ) );
		$this->assertEquals ( "Pedro", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 4" ) );
		$this->assertEquals ( "Bonzo", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 5" ) );
		$this->assertEquals ( "Amy", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 6" ) );
		$this->assertEquals ( "Ben", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 7" ) );
		$this->assertEquals ( "Mark", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 8" ) );
		$this->assertEquals ( "Philip", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 9" ) );
		$this->assertEquals ( "Lucien", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 10" ) );
	}
	
	public function testCanAddMultipleRowsToBatchAndBatchIsCommittedOnceReached() {
		
		$batchingBulkRowInserter = new BatchingBulkRowInserter ( $this->connection, "test_data", array ("id", "data" ), 5 );
		
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		
		// Add 4 and check that we are not yet committed
		$batchingBulkRowInserter->addRows ( array (array (1, "Monday" ), array (2, "Tuesday" ), array (3, "Wednesday" ), array (4, "Thursday" ) ) );
		
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		
		// Add 4 more and check
		$batchingBulkRowInserter->addRows ( array (array (5, "Friday" ), array (6, "Saturday" ), array (7, "Sunday" ), array (8, "January" ) ) );
		
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		$this->assertEquals ( "Monday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 1" ) );
		$this->assertEquals ( "Tuesday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 2" ) );
		$this->assertEquals ( "Wednesday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 3" ) );
		$this->assertEquals ( "Thursday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 4" ) );
		$this->assertEquals ( "Friday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 5" ) );
		
		$batchingBulkRowInserter->addRows ( array (array (9, "February" ), array (10, "March" ) ) );
		
		$this->assertEquals ( 10, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		$this->assertEquals ( "Monday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 1" ) );
		$this->assertEquals ( "Tuesday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 2" ) );
		$this->assertEquals ( "Wednesday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 3" ) );
		$this->assertEquals ( "Thursday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 4" ) );
		$this->assertEquals ( "Friday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 5" ) );
		$this->assertEquals ( "Saturday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 6" ) );
		$this->assertEquals ( "Sunday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 7" ) );
		$this->assertEquals ( "January", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 8" ) );
		$this->assertEquals ( "February", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 9" ) );
		$this->assertEquals ( "March", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 10" ) );
	
	}
	
	public function testCanCommitResidualItemsManuallyIfRequired() {
		
		$batchingBulkRowInserter = new BatchingBulkRowInserter ( $this->connection, "test_data", array ("id", "data" ), 5 );
		
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		
		$batchingBulkRowInserter->addRows ( array (array (1, "Monday" ), array (2, "Tuesday" ), array (3, "Wednesday" ), array (4, "Thursday" ) ) );
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		
		$batchingBulkRowInserter->commitBatch ();
		$this->assertEquals ( 4, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		$this->assertEquals ( "Monday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 1" ) );
		$this->assertEquals ( "Tuesday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 2" ) );
		$this->assertEquals ( "Wednesday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 3" ) );
		$this->assertEquals ( "Thursday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 4" ) );
		
		$batchingBulkRowInserter->addRows ( array (array (5, "Friday" ), array (6, "Saturday" ), array (7, "Sunday" ), array (8, "January" ) ) );
		
		$batchingBulkRowInserter->commitBatch ();
		
		$this->assertEquals ( 8, $this->connection->queryForSingleValue ( "SELECT count(*) from test_data" ) );
		$this->assertEquals ( "Monday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 1" ) );
		$this->assertEquals ( "Tuesday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 2" ) );
		$this->assertEquals ( "Wednesday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 3" ) );
		$this->assertEquals ( "Thursday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 4" ) );
		$this->assertEquals ( "Friday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 5" ) );
		$this->assertEquals ( "Saturday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 6" ) );
		$this->assertEquals ( "Sunday", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 7" ) );
		$this->assertEquals ( "January", $this->connection->queryForSingleValue ( "SELECT data from test_data where id = 8" ) );
	
	}





}

?>