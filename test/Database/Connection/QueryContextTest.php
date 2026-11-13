<?php

namespace Kinikit\Persistence\Database\Connection;

use Kinikit\Persistence\Database\Vendors\MySQL\MySQLDatabaseConnection;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class QueryContextTest extends TestCase {

    protected function tearDown(): void {
        QueryContext::setTimeout(null);
    }

    public function testTimeoutCanBeConfiguredAndCleared() {
        QueryContext::setTimeout(500);
        $this->assertSame(500, QueryContext::getTimeout());

        QueryContext::setTimeout(null);
        $this->assertNull(QueryContext::getTimeout());
    }

    public function testMySQLParserAppliesTimeoutOnlyToSelectStatements() {
        $connection = (new \ReflectionClass(MySQLDatabaseConnection::class))->newInstanceWithoutConstructor();
        QueryContext::setTimeout(250);

        $this->assertSame(
            "SELECT /*+ MAX_EXECUTION_TIME(250) */ * FROM records",
            $connection->parseSQL("SELECT * FROM records")
        );
        $this->assertSame(
            "UPDATE records SET active = 1",
            $connection->parseSQL("UPDATE records SET active = 1")
        );

        QueryContext::setTimeout(null);
        $this->assertSame("SELECT * FROM records", $connection->parseSQL("SELECT * FROM records"));
    }

    public function testMySQLParserAppliesToFirstSelectStatementOnly() {
        $connection = (new \ReflectionClass(MySQLDatabaseConnection::class))->newInstanceWithoutConstructor();
        QueryContext::setTimeout(250);

        $this->assertSame(
            "SELECT /*+ MAX_EXECUTION_TIME(250) */ name, age FROM (SELECT * FROM records UNION SELECT * FROM records_2)",
            $connection->parseSQL("SELECT name, age FROM (SELECT * FROM records UNION SELECT * FROM records_2)")
        );

        QueryContext::setTimeout(null);
        $this->assertSame(
            "SELECT name, age FROM (SELECT * FROM records UNION SELECT * FROM records_2)",
            $connection->parseSQL("SELECT name, age FROM (SELECT * FROM records UNION SELECT * FROM records_2)")
        );
    }
}
