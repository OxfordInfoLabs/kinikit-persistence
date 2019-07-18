<?php

namespace Kinikit\Persistence\Database\Connection\MSSQL;


include_once "autoloader.php";

/**
 * Test cases for the MSSQL Query Parser
 */
class MSSQLQueryParserTest extends \PHPUnit\Framework\TestCase {


    public function testCanConvertStandardMySQLLimitStyleQueriesIntoMSSQLTopFormat() {

        $parser = new MSSQLQueryParser();

        $sourceQuery = "SELECT * FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon' LIMIT 10";
        $outputQuery = "SELECT TOP 10 * FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon'" ;

        $this->assertEquals($outputQuery, $parser->parse($sourceQuery));


        $sourceQuery = "SELECT a, b, c FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon' LIMIT 50";
        $outputQuery = "SELECT TOP 50 a, b, c FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon'";

        $this->assertEquals($outputQuery, $parser->parse($sourceQuery));


    }


    public function testCanConvertStandardMySQLOffsetStyleQueriesIntoMSSQLExceptFormat() {

        $parser = new MSSQLQueryParser();

        $sourceQuery = "SELECT * FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon' OFFSET 50";
        $outputQuery = "SELECT * FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon' EXCEPT SELECT TOP 50 * FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon'";

        $this->assertEquals($outputQuery, $parser->parse($sourceQuery));

    }


    public function testCanConvertStandardMySQLOffsetAndLimitStyleQueriesIntoMSSQLTopExceptFormat() {

        $parser = new MSSQLQueryParser();

        $sourceQuery = "SELECT * FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon' LIMIT 10 OFFSET 50";
        $outputQuery = "SELECT TOP 60 * FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon' EXCEPT SELECT TOP 50 * FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon'";

        $this->assertEquals($outputQuery, $parser->parse($sourceQuery));


    }


    public function testCanConvertOffsetAndLimitQueriesWithOrderByClausesIntoCorrectMSSQLSyntax() {

        $parser = new MSSQLQueryParser();

        $sourceQuery = "SELECT * FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon' ORDER BY beaver LIMIT 10 OFFSET 50";
        $outputQuery = "SELECT TOP 60 row_number() OVER (ORDER BY beaver) row,* FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon'  EXCEPT SELECT TOP 50 row_number() OVER (ORDER BY beaver) row,* FROM monkey WHERE badger = 'fudge' AND beaver = 'bonbon'";

        $this->assertEquals($outputQuery, $parser->parse($sourceQuery));


    }






}
