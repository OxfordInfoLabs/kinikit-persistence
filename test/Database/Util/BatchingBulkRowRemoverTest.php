<?php

namespace Kinikit\Persistence\Database\Util;

use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

/**
 * 
 * Test cases for the batching bulk row remover object.
 * 
 * @author mark
 *
 */
class BatchingBulkRowRemoverTest extends \PHPUnit\Framework\TestCase {
	
	private $connection;
	
	public function setUp() {
		
		$this->connection = DefaultDB::instance ();
		$this->connection->query ( "DROP TABLE IF EXISTS test_data" );
		$this->connection->query ( "CREATE TABLE test_data (id INTEGER, data VARCHAR(1000) NOT NULL)" );
	}
	
	public function testCanAddSingleSimpleKeysToTheRemoverAndTheseAreCommittedOnceTheBatchSizeIsReached() {
		
		$this->connection->query ( "INSERT INTO test_data (id,data) values (1,'My Word'), (2, 'Your one'), (3, 'Good job'), (4, 'My Word'), (5, 'Bang Bang')" );
		
		// Check we have 5 at the same time
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		// Create the remover and add some delete keys
		$batchingRowRemover = new BatchingBulkRowRemover ( $this->connection, "test_data", "id", 3 );
		
		$batchingRowRemover->addRowKey ( 2 );
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		$batchingRowRemover->addRowKey ( 4 );
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		$batchingRowRemover->addRowKey ( 5 );
		$this->assertEquals ( 2, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		$this->assertEquals ( "My Word", $this->connection->queryForSingleValue ( "SELECT data FROM test_data WHERE id = 1" ) );
		$this->assertEquals ( "Good job", $this->connection->queryForSingleValue ( "SELECT data FROM test_data WHERE id = 3" ) );
	
	}
	
	public function testCanAddSingleCompoundKeysToTheRemoverAndTheseAreCommittedOnceTheBatchSizeIsReached() {
		
		$this->connection->query ( "INSERT INTO test_data (id,data) values (1,'My Word'), (2, 'Your one'), (3, 'Good job'), (4, 'My Word'), (5, 'Bang Bang')" );
		
		// Check we have 5 at the same time
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		// Create the remover and add some delete keys
		$batchingRowRemover = new BatchingBulkRowRemover ( $this->connection, "test_data", array ("id", "data" ), 2 );
		
		$batchingRowRemover->addRowKey ( array (3, "Good job" ) );
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		$batchingRowRemover->addRowKey ( array (4, "My Word" ) );
		$this->assertEquals ( 3, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		$this->assertEquals ( "My Word", $this->connection->queryForSingleValue ( "SELECT data FROM test_data WHERE id = 1" ) );
		$this->assertEquals ( "Your one", $this->connection->queryForSingleValue ( "SELECT data FROM test_data WHERE id = 2" ) );
		$this->assertEquals ( "Bang Bang", $this->connection->queryForSingleValue ( "SELECT data FROM test_data WHERE id = 5" ) );
		
		$batchingRowRemover->addRowKey ( array (1, "My Word" ) );
		$this->assertEquals ( 3, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		$batchingRowRemover->addRowKey ( array (5, "Bang Bang" ) );
		$this->assertEquals ( 1, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		$this->assertEquals ( "Your one", $this->connection->queryForSingleValue ( "SELECT data FROM test_data WHERE id = 2" ) );
	
	}
	
	public function testCanAddMultipleSimpleKeysToTheRemoverUsingAddKeysMethod() {
		
		$this->connection->query ( "INSERT INTO test_data (id,data) values (1,'My Word'), (2, 'Your one'), (3, 'Good job'), (4, 'My Word'), (5, 'Bang Bang')" );
		
		// Check we have 5 at the same time
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		// Create the remover and add some delete keys
		$batchingRowRemover = new BatchingBulkRowRemover ( $this->connection, "test_data", "id", 3 );
		
		$batchingRowRemover->addRowKeys ( array (2, 4 ) );
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		$batchingRowRemover->addRowKeys ( array (1, 3 ) );
		$this->assertEquals ( 2, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		$this->assertEquals ( "Good job", $this->connection->queryForSingleValue ( "SELECT data FROM test_data WHERE id = 3" ) );
		$this->assertEquals ( "Bang Bang", $this->connection->queryForSingleValue ( "SELECT data FROM test_data WHERE id = 5" ) );
	
	}
	
	public function testCanCommitTheBatchAtAnyTime() {
		
		$this->connection->query ( "INSERT INTO test_data (id,data) values (1,'My Word'), (2, 'Your one'), (3, 'Good job'), (4, 'My Word'), (5, 'Bang Bang')" );
		
		// Check we have 5 at the same time
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		// Create the remover and add some delete keys
		$batchingRowRemover = new BatchingBulkRowRemover ( $this->connection, "test_data", "id", 15 );
		
		$batchingRowRemover->addRowKey ( 1 );
		$batchingRowRemover->addRowKey ( 2 );
		$batchingRowRemover->addRowKey ( 4 );
		
		$this->assertEquals ( 5, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		$batchingRowRemover->commitBatch ();
		
		$this->assertEquals ( 2, $this->connection->queryForSingleValue ( "SELECT COUNT(*) FROM test_data" ) );
		
		$this->assertEquals ( "Good job", $this->connection->queryForSingleValue ( "SELECT data FROM test_data WHERE id = 3" ) );
		$this->assertEquals ( "Bang Bang", $this->connection->queryForSingleValue ( "SELECT data FROM test_data WHERE id = 5" ) );
	
	}

}

?>