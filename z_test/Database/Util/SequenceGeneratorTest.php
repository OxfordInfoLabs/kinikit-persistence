<?php

namespace Kinikit\Persistence\Database\Util;


use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

/**
 * Test cases for the sequence generator
 * 
 * @author mark
 *
 */
class SequenceGeneratorTest extends \PHPUnit\Framework\TestCase {
	
	private $connection;
	
	public function setUp():void {
		$this->connection = DefaultDB::instance ();
		$this->connection->query ( "DROP TABLE IF EXISTS kinikit_sequence" );
	}
	
	public function testSequenceTableIsCreatedIfRequiredAndNewSequenceAddedUponFirstRequestForCurrentSequenceValue() {
		
		$sequenceGenerator = new SequenceGenerator ( $this->connection );
		
		$this->assertEquals ( 0, $sequenceGenerator->getCurrentSequenceValue ( "MySeq" ) );
		$this->assertEquals ( 1, $this->connection->queryForSingleValue ( "SELECT count(*) FROM kinikit_sequence" ) );
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'MySeq'" ) );
		
		// Now create another one to check
		$this->assertEquals ( 0, $sequenceGenerator->getCurrentSequenceValue ( "NewSeq" ) );
		$this->assertEquals ( 2, $this->connection->queryForSingleValue ( "SELECT count(*) FROM kinikit_sequence" ) );
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'MySeq'" ) );
		$this->assertEquals ( 0, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'NewSeq'" ) );
	}
	
	public function testCanIncrementExistingSequencesAndRetrieveCurrentValues() {
		
		$sequenceGenerator = new SequenceGenerator ( $this->connection );
		$this->assertEquals ( 1, $sequenceGenerator->incrementSequence ( "BrandNew" ) );
		$this->assertEquals ( 1, $this->connection->queryForSingleValue ( "SELECT count(*) FROM kinikit_sequence" ) );
		$this->assertEquals ( 1, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'BrandNew'" ) );
		
		// Increment again
		$this->assertEquals ( 2, $sequenceGenerator->incrementSequence ( "BrandNew" ) );
		$this->assertEquals ( 1, $this->connection->queryForSingleValue ( "SELECT count(*) FROM kinikit_sequence" ) );
		$this->assertEquals ( 2, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'BrandNew'" ) );
		
		$this->assertEquals ( 3, $sequenceGenerator->incrementSequence ( "BrandNew" ) );
		$this->assertEquals ( 1, $this->connection->queryForSingleValue ( "SELECT count(*) FROM kinikit_sequence" ) );
		$this->assertEquals ( 3, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'BrandNew'" ) );
		
		// Now create a competely different sequence by increment
		$this->assertEquals ( 1, $sequenceGenerator->incrementSequence ( "BobSeq" ) );
		$this->assertEquals ( 2, $this->connection->queryForSingleValue ( "SELECT count(*) FROM kinikit_sequence" ) );
		$this->assertEquals ( 3, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'BrandNew'" ) );
		$this->assertEquals ( 1, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'BobSeq'" ) );
	
		$this->assertEquals ( 2, $sequenceGenerator->incrementSequence ( "BobSeq" ) );
		$this->assertEquals ( 2, $this->connection->queryForSingleValue ( "SELECT count(*) FROM kinikit_sequence" ) );
		$this->assertEquals ( 3, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'BrandNew'" ) );
		$this->assertEquals ( 2, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'BobSeq'" ) );
		
		
		$this->assertEquals(2, $sequenceGenerator->getCurrentSequenceValue("BobSeq"));
		$this->assertEquals(3, $sequenceGenerator->getCurrentSequenceValue("BrandNew"));
		
		// Check get current didn't change the state.
		$this->assertEquals ( 2, $this->connection->queryForSingleValue ( "SELECT count(*) FROM kinikit_sequence" ) );
		$this->assertEquals ( 3, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'BrandNew'" ) );
		$this->assertEquals ( 2, $this->connection->queryForSingleValue ( "SELECT current_value FROM kinikit_sequence WHERE sequence_name = 'BobSeq'" ) );
		
		
		
	}

}

?>
