<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Utils;


include_once "autoloader.php";

/**
 * Test cases for the ORM Utils functionality.
 * 
 * @author mark
 *
 */
class ORMUtilsTest extends \PHPUnit\Framework\TestCase {
	
	public function testLowerCasedStringsReturnIntactFromConversion() {
		
		$this->assertEquals ( "testlower", ORMUtils::convertCamelCaseToUnderscore ( "testlower" ) );
	
	}
	
	public function testUpperCasedStringsReturnLowerCaseFromConversion() {
		
		$this->assertEquals ( "testupper", ORMUtils::convertCamelCaseToUnderscore ( "TESTUPPER" ) );
	
	}
	
	public function testCamelCasedStringsReturnWithUnderscoresOnConversion() {
		
		$this->assertEquals ( "test_camel_case", ORMUtils::convertCamelCaseToUnderscore ( "testCamelCase" ) );
		$this->assertEquals ( "another_camel_case", ORMUtils::convertCamelCaseToUnderscore ( "anotherCamelCase" ) );
		$this->assertEquals ( "a_big_boy", ORMUtils::convertCamelCaseToUnderscore ( "aBigBoy" ) );
		
		// Now try some with multi caps
		$this->assertEquals ( "my_special_text", ORMUtils::convertCamelCaseToUnderscore ( "mySPECIALText" ) );
		$this->assertEquals ( "simple_orm_mapper", ORMUtils::convertCamelCaseToUnderscore ( "simpleORMMapper" ) );
		
		$this->assertEquals ( "simple_orm_mapper", ORMUtils::convertCamelCaseToUnderscore ( "SimpleOrmMapper" ) );
	
	}

}

?>