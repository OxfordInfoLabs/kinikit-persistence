<?php

namespace Kinikit\Persistence\Database\Connection;

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

        $dbConnection = new TestDatabaseConnection("mysql");
        $this->assertEquals(["provider" => "mysql", "host" => "127.0.0.1",
            "database" => "kinikittest", "username" => "kinikittest", "password" => "kinikittest"], $dbConnection->configParams);


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

        $dbConnection = new TestDatabaseConnection("mysql");
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


}
