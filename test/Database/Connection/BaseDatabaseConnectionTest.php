<?php

namespace Kinikit\Persistence\Database\Connection;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableIndexColumn;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\BindParameterType;

include_once 'autoloader.php';

/**
 * Base database connection
 *
 * Class BaseDatabaseConnectionTest
 */
class BaseDatabaseConnectionTest extends \PHPUnit\Framework\TestCase {

    public function setUp(): void {

        if (file_exists("DB/db-log.txt"))
            unlink("DB/db-log.txt");

    }

    public function testDefaultConfiguredDBParametersArePassedToConnectionOnConstruction() {

        $dbConnection = new TestDatabaseConnection();
        $this->assertEquals(["provider" => "sqlite3", "filename" => "DB/application.db", "logFile" => "DB/db-log.txt"], $dbConnection->configParams);

        $dbConnection = new TestDatabaseConnection(Configuration::instance()->getParametersMatchingPrefix("mysql.db.", true));
        $this->assertEquals(["provider" => "mysql", "host" => "127.0.0.1", "port" => 3310,
            "database" => "kinikittest", "username" => "kinikittest", "password" => "kinikittest", "logFile" => 'DB/mysql-log.txt',
            'exceptionRetries' => '2'], $dbConnection->configParams);


    }


    public function testCanLogStringsAndTimedCallbacksIfDefinedInConfigFile() {


        $dbConnection = new TestDatabaseConnection();
        $dbConnection->log("My name is paul");
        $this->assertStringContainsString("My name is paul", file_get_contents("DB/db-log.txt"));


        $result = $dbConnection->executeCallableWithLogging(function () {
            return "33";
        }, "Timed block hey hey hey");

        $this->assertEquals(33, $result);
        $this->assertStringContainsString("Timed block hey hey hey", file_get_contents("DB/db-log.txt"));
        $this->assertStringContainsString("Completed in", file_get_contents("DB/db-log.txt"));
    }


    public function testNoLoggingOccursAndResultStillReturnedWhereNoLoggingSet() {

        $dbConnection = new TestDatabaseConnection([]);
        $dbConnection->log("My name is paul");

        $result = $dbConnection->executeCallableWithLogging(function () {
            return "33";
        }, "Timed block hey hey hey");

        $this->assertEquals(33, $result);

        $this->assertFalse(file_exists("DB/db-log.txt"));

    }


    public function testDefaultTransactionLogicIsCalledAsExpected() {

        // Progressively begin and roll back savepoints.
        $dbConnection = new TestDatabaseConnection();
        $dbConnection->beginTransaction();
        $this->assertEquals("BEGIN", $dbConnection->lastSQL);

        $dbConnection->beginTransaction();
        $this->assertEquals("SAVEPOINT SP2", $dbConnection->lastSQL);

        $dbConnection->beginTransaction();
        $this->assertEquals("SAVEPOINT SP3", $dbConnection->lastSQL);


        $dbConnection->rollback(false);
        $this->assertEquals("ROLLBACK TO SAVEPOINT SP3", $dbConnection->lastSQL);

        $dbConnection->rollback(false);
        $this->assertEquals("ROLLBACK TO SAVEPOINT SP2", $dbConnection->lastSQL);

        $dbConnection->rollback(false);
        $this->assertEquals("ROLLBACK", $dbConnection->lastSQL);


        // Whole transaction rollback
        $dbConnection->beginTransaction();
        $this->assertEquals("BEGIN", $dbConnection->lastSQL);

        $dbConnection->beginTransaction();
        $this->assertEquals("SAVEPOINT SP2", $dbConnection->lastSQL);

        $dbConnection->rollback(true);
        $this->assertEquals("ROLLBACK", $dbConnection->lastSQL);


        // Commit
        $dbConnection->beginTransaction();
        $this->assertEquals("BEGIN", $dbConnection->lastSQL);

        $dbConnection->commit();
        $this->assertEquals("COMMIT", $dbConnection->lastSQL);

    }

    public function testQueryNormalisesVariadicAndArrayBindParameters(): void {
        $dbConnection = new TestDatabaseConnection([]);
        $explicitParameter = new BindParameter("explicit", BindParameterType::TYPE_STRING);

        $dbConnection->query("SELECT ?, ?", 123, $explicitParameter);
        $this->assertSame(BindParameterType::TYPE_INT, $dbConnection->lastParameters[0]->getType());
        $this->assertSame(123, $dbConnection->lastParameters[0]->getValue());
        $this->assertSame($explicitParameter, $dbConnection->lastParameters[1]);

        $dbConnection->query("SELECT ?, ?", [true, "value"]);
        $this->assertSame(BindParameterType::TYPE_BOOL, $dbConnection->lastParameters[0]->getType());
        $this->assertSame(BindParameterType::TYPE_STRING, $dbConnection->lastParameters[1]->getType());
    }

    public function testExecutePassesNormalisedBindParametersToPreparedStatement(): void {
        $dbConnection = new TestDatabaseConnection([]);
        $dbConnection->execute("INSERT INTO test VALUES (?, ?)", [123, "value"]);

        $this->assertSame(BindParameterType::TYPE_INT, $dbConnection->lastPreparedStatement->executedParameters[0]->getType());
        $this->assertSame(123, $dbConnection->lastPreparedStatement->executedParameters[0]->getValue());
        $this->assertSame(BindParameterType::TYPE_STRING, $dbConnection->lastPreparedStatement->executedParameters[1]->getType());
        $this->assertTrue($dbConnection->lastPreparedStatement->closed);
    }


    public function testTableMetaDataCorrectlyCompiledFromColumnsAndIndexes() {

        // Progressively begin and roll back savepoints.
        $dbConnection = new TestDatabaseConnection();

        $this->assertEquals(new TableMetaData("test_table", [new TableColumn("bingo", "int")],
            [new TableIndex("testindex", [new TableIndexColumn("bingo")])]), $dbConnection->getTableMetaData("test_table"));

    }


    public function testCanExecuteScriptDelimitedBySemiColons() {

        $dbConnection = new TestDatabaseConnection();
        $testScript = "INSERT INTO test VALUES (1,2,3); INSERT INTO test VALUES (4,5,6); DELETE FROM test;";

        $dbConnection->executeScript($testScript);
        $this->assertEquals([
            "INSERT INTO test VALUES (1,2,3)",
            "INSERT INTO test VALUES (4,5,6)",
            "DELETE FROM test"
        ], $dbConnection->executedStatements);


    }


    public function testCanExecuteScriptDelimitedBySemiColonsWithNestedExpressionsUsingCommentedSemiColons() {

        $dbConnection = new TestDatabaseConnection();
        $testScript = "INSERT INTO test VALUES (1,2,3);-- COMMIT; INSERT INTO test VALUES (4,5,6); DELETE FROM test;";

        $dbConnection->executeScript($testScript);
        $this->assertEquals([
            "INSERT INTO test VALUES (1,2,3); COMMIT",
            "INSERT INTO test VALUES (4,5,6)",
            "DELETE FROM test"
        ], $dbConnection->executedStatements);


    }



}
