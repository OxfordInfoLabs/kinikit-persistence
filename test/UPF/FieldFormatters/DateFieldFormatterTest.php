<?php

namespace Kinikit\Persistence\UPF\FieldFormatters;

include_once "autoloader.php";

/**
 * Test cases for the date field formatter.
 * 
 * @author oxil
 *
 */
class DateFieldFormatterTest extends \PHPUnit\Framework\TestCase {
	
	public function testDefaultConfiguredFormatterIgnoresAndReturnsIntactNonStandardSQLDatesOnFormat() {
		$formatter = new DateFieldFormatter ();
		$this->assertEquals ( "Not even a date", $formatter->format ( "Not even a date" ) );
		$this->assertEquals ( "01-05-2011", $formatter->format ( "01-05-2011" ) );
		$this->assertEquals ( "11/07/2009", $formatter->format ( "11/07/2009" ) );
	
	}
	
	public function testDefaultConfiguredFormatterFormatsStandardSQLDatesToBritishDateOnlyFormat() {
		$formatter = new DateFieldFormatter ();
		$this->assertEquals ( "01/05/2011", $formatter->format ( "2011-05-01" ) );
		$this->assertEquals ( "01/05/2011", $formatter->format ( "2011-05-01 10:34" ) );
		$this->assertEquals ( "01/05/2011", $formatter->format ( "2011-05-01 10:34:30" ) );
	}
	
	public function testCustomTargetFormatIsUsedOnFormatIfSupplied() {
		
		$formatter = new DateFieldFormatter ( "myformat", "d, m, Y" );
		
		$this->assertEquals ( "01, 05, 2011", $formatter->format ( "2011-05-01" ) );
		$this->assertEquals ( "01, 05, 2011", $formatter->format ( "2011-05-01 10:34" ) );
		$this->assertEquals ( "01, 05, 2011", $formatter->format ( "2011-05-01 10:34:30" ) );
	}
	
	public function testBadlyFormattedDatesAreNotUnformattedButReturnedIntactWhenUnformatCalled() {
		$formatter = new DateFieldFormatter ();
		$this->assertEquals ( "2011", $formatter->unformat ( "2011" ) );
		$this->assertEquals ( "01,01,2001", $formatter->unformat ( "01,01,2001" ) );
	}
	
	public function testDatesSuppliedInBritishFormatAreUnformattedToSQLDateFormatInDefaultConfig() {
		
		$formatter = new DateFieldFormatter ();
		$this->assertEquals ( "2011-05-01", $formatter->unformat ( "01/05/2011" ) );
		$this->assertEquals ( "2012-12-11", $formatter->unformat ( "11/12/2012" ) );
	
	}
	
	public function testIfCustomSourceAndTargetFormatsSuppliedTheyAreUsedInFormatAndUnformat() {
		
		$formatter = new DateFieldFormatter ( "MyFormatter", "d, m, Y", "Y, m, d" );
		$this->assertEquals ( "01, 05, 2011", $formatter->format ( "2011, 05, 01" ) );
		$this->assertEquals ( "2011, 05, 01", $formatter->unformat ( "01, 05, 2011" ) );
	
	}

}

?>