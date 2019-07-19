<?php

namespace Kinikit\Persistence\Database\Connection\SQLite3;

use Kinikit\Persistence\Database\Connection\BlobWrapper;
use Kinikit\Persistence\Database\Connection\PreparedStatement;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Exception\SQLException;

include_once "autoloader.php";

/*
 * Test cases for the sqlite 3 database implementation
 */

class SQLite3DatabaseConnectionTest extends \PHPUnit\Framework\TestCase {

    private $dbLocation = "Database/Connection/SQLite3/testsqlite3.db";

    public function testCanCreateNewSQLLite3DatabaseByTargetingNoneExistentPath() {

        if (file_exists($this->dbLocation)) {
            unlink($this->dbLocation);
        }

        $database = new SQLite3DatabaseConnection ($this->dbLocation);
        $this->assertTrue(file_exists($this->dbLocation));

    }

    public function testCanQuerySqlLite3DatabaseByTargetingExistingPath() {

        $database = new SQLite3DatabaseConnection ($this->dbLocation);
        $database->query("CREATE TABLE TEST (id INTEGER PRIMARY KEY, name VARCHAR(20))",);
        $database->query("INSERT INTO TEST VALUES (1, 'Hello world of fun')",);
        $database->query("INSERT INTO TEST VALUES (2, 'Goodbye Goodbye')",);

        $comparison = new \PDO ("sqlite:Database/Connection/SQLite3/testsqlite3.db");
        $results = $comparison->prepare("SELECT * FROM TEST")->execute();
        self::assertTrue(true);
    }

    public function testCanQuerySqlLite3DatabaseWithResults() {

        $database = new SQLite3DatabaseConnection ($this->dbLocation);

        $results = $database->queryWithResults("SELECT * FROM TEST",);

        $row = $results->nextRow();
        $this->assertEquals(1, $row ["id"]);
        $this->assertEquals("Hello world of fun", $row ["name"]);

        $row = $results->nextRow();
        $this->assertEquals(2, $row ["id"]);
        $this->assertEquals("Goodbye Goodbye", $row ["name"]);

        // Check we can get the column names as an array as well
        $columnNames = $results->getColumnNames();
        $this->assertEquals(2, sizeof($columnNames));

        $this->assertEquals("id", $columnNames [0]);
        $this->assertEquals("name", $columnNames [1]);

        $results->close();

    }

    public function testFalseReturnedIfQueryFails() {

        $database = new SQLite3DatabaseConnection ($this->dbLocation);

        try {
            $results = $database->queryWithResults("SELECT * FROM TEST_MONKEY",);
            $this->fail("Should have thrown");
        } catch (SQLException $e){
            // Success
        }

        try {
            $results = $database->query("SELECT * FROM TEST_MONKEY",);
            $this->fail("Should have thrown");
        } catch (SQLException $e){
            // Success
        }

        $this->assertTrue(true);

    }

    public function testCanEscapeAStringUsingEscapeString() {

        $database = new SQLite3DatabaseConnection ($this->dbLocation);

        $string = "Hello 'Simon' and 'Joff'";
        $escaped = $database->escapeString($string);

        $this->assertEquals("Hello ''Simon'' and ''Joff''", $escaped);

    }

    public function testCloseClosesConnectionAndNullifiesConnectionObject() {
        $database = new SQLite3DatabaseConnection ($this->dbLocation);
        $database->close();
        self::assertTrue(true);
        try {
            $results = $database->queryWithResults("SELECT * FROM TEST",);
        } catch (Exception $e) {
            // Success
        }

    }

    public function testLastInsertIdIsCorrectlySetIfApplicable() {

        $database = new SQLite3DatabaseConnection ($this->dbLocation);
        $database->query("INSERT INTO TEST ('name') VALUES ('Booskaboo')",);
        $this->assertEquals(3, $database->getLastAutoIncrementId());

    }

    public function testCanGetLastErrorString() {

        $database = new SQLite3DatabaseConnection ($this->dbLocation);

        try {
            $database->query("SELECT * FROM TEST_MONKEY",);
            $this->fail("Should have thrown here");
        } catch (SQLException $e){
            // Success
        }
        $this->assertNotNull($database->getLastErrorMessage());

    }

    public function testCanGetTableMetaDataFromConnection() {

        $database = new SQLite3DatabaseConnection ($this->dbLocation);

        $tableMetaData = $database->getTableMetaData("TEST");

        $this->assertEquals("TEST", $tableMetaData->getTableName());
        $columns = $tableMetaData->getColumns();

        $this->assertEquals(2, sizeof($columns));
        $this->assertEquals(new TableColumn ("id", TableColumn::SQL_INT, 0), $columns ["id"]);
        $this->assertEquals(new TableColumn ("name", TableColumn::SQL_VARCHAR, 20), $columns ["name"]);

    }

    public function testCanQueryUsingSQRTFunction() {

        $database = new SQLite3DatabaseConnection ($this->dbLocation);
        $results = $database->queryWithResults("SELECT SQRT(id) myval FROM test",);
        $row = $results->nextRow();
        $this->assertEquals("1.0", $row ["myval"]);
        $results->close();

    }

    public function testCanExecuteAPreparedStatement() {

        $database = new SQLite3DatabaseConnection ($this->dbLocation);

        $statement = new PreparedStatement ("INSERT INTO TEST (name) VALUES (?)");
        $statement->addBindParameter(TableColumn::SQL_VARCHAR, "Testing Testing 1,2,3");

        $database->executePreparedStatement($statement);

        $results = $database->queryWithResults("SELECT * FROM TEST WHERE id = " . $database->getLastAutoIncrementId(),);
        $row = $results->nextRow();
        $this->assertEquals("Testing Testing 1,2,3", $row ["name"]);
        $results->close();
    }

    public function testPreparedStatementsWithBlobObjectsAreHandledCorrectly() {

        // Get the mysql connection object
        $sqlite3Connection = new SQLite3DatabaseConnection ($this->dbLocation);

        $sqlite3Connection->query("DROP TABLE IF EXISTS test_with_blob",);
        $sqlite3Connection->query("CREATE TABLE test_with_blob (id INTEGER PRIMARY KEY, blob_data LONGBLOB)",);

        $preparedStatement = new PreparedStatement ("INSERT INTO test_with_blob (blob_data) VALUES (?)");
        $preparedStatement->addBindParameter(TableColumn::SQL_BLOB, new BlobWrapper ("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE"));

        // Execute an explicit one.
        $sqlite3Connection->executePreparedStatement($preparedStatement);

        // Check it made it in
        $results = $sqlite3Connection->queryWithResults("SELECT * from test_with_blob WHERE id = " . $sqlite3Connection->getLastAutoIncrementId(),);
        $row = $results->nextRow();
        $this->assertEquals("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE", $row ["blob_data"]);
        $results->close();


    }


    public function testLargeBlobsCanAlsoBeInserted() {


        $sqlite3Connection = new SQLite3DatabaseConnection ($this->dbLocation);


        // Now do one via filename
        $preparedStatement = new PreparedStatement ("INSERT INTO test_with_blob (blob_data) VALUES (?)");
        $preparedStatement->addBindParameter(TableColumn::SQL_BLOB, new BlobWrapper (null, "Database/Connection/testlargeobject.pdf"));

        // Execute a filename based one
        $sqlite3Connection->executePreparedStatement($preparedStatement);

        // Now check it made it in.
        $results = $sqlite3Connection->queryWithResults("SELECT * from test_with_blob WHERE id = " . $sqlite3Connection->getLastAutoIncrementId(),);
        $row = $results->nextRow();


        $this->assertEquals(file_get_contents("Database/Connection/testlargeobject.pdf"), $row ["blob_data"]);

        $results->close();

    }

}

?>
