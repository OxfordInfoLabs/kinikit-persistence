<?php

namespace Kinikit\Persistence\Database\Vendors\SQLite3;

use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\Connection\ConnectionClosedException;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\PreparedStatement\BlobWrapper;
use Kinikit\Persistence\Database\PreparedStatement\ColumnType;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
use Kinikit\Persistence\Database\Exception\SQLException;

include_once "autoloader.php";

/*
 * Test cases for the sqlite 3 database implementation
 */

class SQLite3DatabaseConnectionTest extends \PHPUnit\Framework\TestCase {

    private $dbLocation = "Database/Vendors/SQLite3/testsqlite3.db";

    public function testCanCreateNewSQLLite3DatabaseByTargetingNoneExistentPath() {

        if (file_exists($this->dbLocation)) {
            unlink($this->dbLocation);
        }

        $database = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);
        $this->assertTrue(file_exists($this->dbLocation));

    }

    public function testCanQuerySqlLite3DatabaseByTargetingExistingPath() {

        $database = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);
        $database->execute("CREATE TABLE TEST (id INTEGER PRIMARY KEY, name VARCHAR(20))");
        $database->execute("INSERT INTO TEST VALUES (?, ?)", 1, 'Hello world of fun');
        $database->execute("INSERT INTO TEST VALUES (2, 'Goodbye Goodbye')");

        $comparison = new \PDO ("sqlite:Database/Vendors/SQLite3/testsqlite3.db");
        $results = $comparison->prepare("SELECT * FROM TEST")->execute();
        self::assertTrue(true);
    }

    public function testCanQuerySqlLite3DatabaseWithResults() {

        $database = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $results = $database->query("SELECT * FROM TEST");

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

        $database = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        try {
            $results = $database->query("SELECT * FROM TEST_MONKEY");
            $this->fail("Should have thrown");
        } catch (SQLException $e) {
            // Success
        }

        try {
            $results = $database->query("SELECT * FROM TEST_MONKEY");
            $this->fail("Should have thrown");
        } catch (SQLException $e) {
            // Success
        }


        $this->assertTrue(true);

    }

    public function testSQLStateExceptionCodesReturnedCorrectlyInSQLExceptions() {

        $database = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $database->execute("DROP TABLE IF EXISTS example_pdo");
        $database->execute("CREATE TABLE example_pdo (id INTEGER, name VARCHAR(50), PRIMARY KEY(id))");
        $database->execute("INSERT INTO example_pdo VALUES (1, 'Mark'),(2, 'Luke'),(3, 'Bob')");

        try {
            $database->execute("INSERT INTO example_pdo VALUES (1, 'Mark')");
            $this->fail("Should have thrown here");
        } catch (SQLException $e) {
            $this->assertEquals(23000, $e->getSqlStateCode());
        }


    }


    public function testCanEscapeAStringUsingEscapeString() {

        $database = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $string = "Hello 'Simon' and 'Joff'";
        $escaped = $database->escapeString($string);

        $this->assertEquals("'Hello ''Simon'' and ''Joff'''", $escaped);

    }

    public function testCloseClosesConnectionAndNullifiesConnectionObject() {
        $database = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);
        $database->close();
        self::assertTrue(true);
        try {
            $results = $database->query("SELECT * FROM TEST");
        } catch (ConnectionClosedException $e) {
            // Success
        }

    }

    public function testLastInsertIdIsCorrectlySetIfApplicable() {

        $database = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);
        $database->query("INSERT INTO TEST ('name') VALUES ('Booskaboo')");
        $this->assertEquals(3, $database->getLastAutoIncrementId());

    }

    public function testCanGetLastErrorString() {

        $database = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        try {
            $database->query("SELECT * FROM TEST_MONKEY");
            $this->fail("Should have thrown here");
        } catch (SQLException $e) {
            // Success
        }
        $this->assertNotNull($database->getLastErrorMessage());

    }


    public function testCanExecuteAPreparedStatement() {

        $database = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $statement = $database->createPreparedStatement("INSERT INTO TEST (name) VALUES (?)");
        $statement->execute(["Testing Testing 1,2,3"]);


        $results = $database->query("SELECT * FROM TEST WHERE id = " . $database->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals("Testing Testing 1,2,3", $row ["name"]);
        $results->close();
    }

    public function testPreparedStatementsWithBlobObjectsAreHandledCorrectly() {

        // Get the mysql connection object
        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $sqlite3Connection->query("DROP TABLE IF EXISTS test_with_blob");
        $sqlite3Connection->query("CREATE TABLE test_with_blob (id INTEGER PRIMARY KEY, blob_data LONGBLOB)");

        $preparedStatement = $sqlite3Connection->createPreparedStatement("INSERT INTO test_with_blob(blob_data) VALUES(?)");
        $preparedStatement->execute([new BlobWrapper ("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE")]);


        // Check it made it in
        $results = $sqlite3Connection->query("SELECT * from test_with_blob WHERE id = " . $sqlite3Connection->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE", $row ["blob_data"]);
        $results->close();


    }


    public function testLargeBlobsCanAlsoBeInserted() {


        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);


        // Now do one via filename
        $preparedStatement = $sqlite3Connection->createPreparedStatement("INSERT INTO test_with_blob(blob_data) VALUES(?)");
        $preparedStatement->execute([new BlobWrapper (null, "Database/Connection/testlargeobject.txt")]);


        // Now check it made it in.
        $results = $sqlite3Connection->query("SELECT * from test_with_blob WHERE id = " . $sqlite3Connection->getLastAutoIncrementId());
        $row = $results->nextRow();


        $this->assertEquals(file_get_contents("Database/Connection/testlargeobject.txt"), $row ["blob_data"]);

        $results->close();

    }

    public function testCanGetTableColumnMetaData() {

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $sqlite3Connection->execute("DROP TABLE IF EXISTS test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, small_int SMALLINT, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(1,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB, long_blob_val LONGBLOB,
            text_val TEXT, long_text_val LONGTEXT)";

        $sqlite3Connection->execute($query);

        $tableColumns = $sqlite3Connection->getTableColumnMetaData("test_types");

        $this->assertEquals(17, sizeof($tableColumns));
        $this->assertEquals(new TableColumn("id", "INTEGER", null, null, null, true, true, false), $tableColumns["id"]);
        $this->assertEquals(new TableColumn("name", "VARCHAR", 500), $tableColumns["name"]);
        $this->assertEquals(new TableColumn("tiny_int", "TINYINT", null, null, 33, false, null, true), $tableColumns["tiny_int"]);
        $this->assertEquals(new TableColumn("small_int", "SMALLINT"), $tableColumns["small_int"]);
        $this->assertEquals(new TableColumn("big_int", "BIGINT"), $tableColumns["big_int"]);
        $this->assertEquals(new TableColumn("float_val", "FLOAT"), $tableColumns["float_val"]);
        $this->assertEquals(new TableColumn("double_val", "DOUBLE"), $tableColumns["double_val"]);
        $this->assertEquals(new TableColumn("real_val", "REAL"), $tableColumns["real_val"]);
        $this->assertEquals(new TableColumn("decimal_val", "DECIMAL", 1, 1), $tableColumns["decimal_val"]);
        $this->assertEquals(new TableColumn("date_val", "DATE"), $tableColumns["date_val"]);
        $this->assertEquals(new TableColumn("time_val", "TIME"), $tableColumns["time_val"]);
        $this->assertEquals(new TableColumn("date_time", "DATETIME"), $tableColumns["date_time"]);
        $this->assertEquals(new TableColumn("timestamp_val", "TIMESTAMP"), $tableColumns["timestamp_val"]);
        $this->assertEquals(new TableColumn("blob_val", "BLOB"), $tableColumns["blob_val"]);
        $this->assertEquals(new TableColumn("long_blob_val", "LONGBLOB"), $tableColumns["long_blob_val"]);
        $this->assertEquals(new TableColumn("text_val", "TEXT"), $tableColumns["text_val"]);
        $this->assertEquals(new TableColumn("long_text_val", "LONGTEXT"), $tableColumns["long_text_val"]);


    }

    public function testCanGetColumnsFromSQLiteResultSetWhenNoDataReturned() {

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $sqlite3Connection->execute("DROP TABLE IF EXISTS test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, small_int SMALLINT, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(1,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB, long_blob_val LONGBLOB,
            text_val TEXT, long_text_val LONGTEXT)";

        $sqlite3Connection->execute($query);

        $query = $sqlite3Connection->query("SELECT * FROM test_types LIMIT 10 OFFSET 10");

        $columns = $query->getColumns();
        $this->assertEquals(17, sizeof($columns));

        $this->assertEquals(new ResultSetColumn("id", "INTEGER", null), $columns[0]);
        $this->assertEquals(new ResultSetColumn("name", "VARCHAR", 500), $columns[1]);
        $this->assertEquals(new ResultSetColumn("tiny_int", "TINYINT"), $columns[2]);
        $this->assertEquals(new ResultSetColumn("small_int", "SMALLINT"), $columns[3]);
        $this->assertEquals(new ResultSetColumn("big_int", "BIGINT"), $columns[4]);
        $this->assertEquals(new ResultSetColumn("float_val", "FLOAT"), $columns[5]);
        $this->assertEquals(new ResultSetColumn("double_val", "DOUBLE"), $columns[6]);
        $this->assertEquals(new ResultSetColumn("real_val", "REAL"), $columns[7]);
        $this->assertEquals(new ResultSetColumn("decimal_val", "DECIMAL", 1, 1), $columns[8]);
        $this->assertEquals(new ResultSetColumn("date_val", "DATE"), $columns[9]);
        $this->assertEquals(new ResultSetColumn("time_val", "TIME"), $columns[10]);
        $this->assertEquals(new ResultSetColumn("date_time", "DATETIME"), $columns[11]);
        $this->assertEquals(new ResultSetColumn("timestamp_val", "TIMESTAMP"), $columns[12]);
        $this->assertEquals(new ResultSetColumn("blob_val", "BLOB"), $columns[13]);
        $this->assertEquals(new ResultSetColumn("long_blob_val", "LONGBLOB"), $columns[14]);
        $this->assertEquals(new ResultSetColumn("text_val", "TEXT"), $columns[15]);
        $this->assertEquals(new ResultSetColumn("long_text_val", "LONGTEXT"), $columns[16]);

    }


    public function testCanGetColumnsFromSQLiteResultSetWhenDataReturned() {
        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $sqlite3Connection->execute("DROP TABLE IF EXISTS test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, small_int SMALLINT, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(1,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB, long_blob_val LONGBLOB,
            text_val TEXT, long_text_val LONGTEXT)";

        $sqlite3Connection->execute($query);

        $sqlite3Connection->execute("INSERT INTO test_types VALUES (25,'Hello',3,3,22,22,44,44,55.5,'2022-01-01',
                               '10:23:00','2022-05-01 10:30:00', '2022-05-01 10:30:00', 'BIG', 'BIG', 'BIG', 'BIG')");

        $query = $sqlite3Connection->query("SELECT * FROM test_types");

        $columns = $query->getColumns();
        $this->assertEquals(17, sizeof($columns));

        $this->assertEquals(new ResultSetColumn("id", "INTEGER", null), $columns[0]);
        $this->assertEquals(new ResultSetColumn("name", "VARCHAR", 500), $columns[1]);
        $this->assertEquals(new ResultSetColumn("tiny_int", "TINYINT"), $columns[2]);
        $this->assertEquals(new ResultSetColumn("small_int", "SMALLINT"), $columns[3]);
        $this->assertEquals(new ResultSetColumn("big_int", "BIGINT"), $columns[4]);
        $this->assertEquals(new ResultSetColumn("float_val", "FLOAT"), $columns[5]);
        $this->assertEquals(new ResultSetColumn("double_val", "DOUBLE"), $columns[6]);
        $this->assertEquals(new ResultSetColumn("real_val", "REAL"), $columns[7]);
        $this->assertEquals(new ResultSetColumn("decimal_val", "DECIMAL", 1, 1), $columns[8]);
        $this->assertEquals(new ResultSetColumn("date_val", "DATE"), $columns[9]);
        $this->assertEquals(new ResultSetColumn("time_val", "TIME"), $columns[10]);
        $this->assertEquals(new ResultSetColumn("date_time", "DATETIME"), $columns[11]);
        $this->assertEquals(new ResultSetColumn("timestamp_val", "TIMESTAMP"), $columns[12]);
        $this->assertEquals(new ResultSetColumn("blob_val", "BLOB"), $columns[13]);
        $this->assertEquals(new ResultSetColumn("long_blob_val", "LONGBLOB"), $columns[14]);
        $this->assertEquals(new ResultSetColumn("text_val", "TEXT"), $columns[15]);
        $this->assertEquals(new ResultSetColumn("long_text_val", "LONGTEXT"), $columns[16]);
    }


    public function testColumnsCorrectlyDerivedFromNativeTypeIfNoSQLTypeAvailable() {

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $sqlite3Connection->execute("DROP TABLE IF EXISTS test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, small_int SMALLINT, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(1,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB, long_blob_val LONGBLOB,
            text_val TEXT, long_text_val LONGTEXT)";

        $sqlite3Connection->execute($query);

        $sqlite3Connection->execute("INSERT INTO test_types VALUES (25,'Hello',3,3,22,22,44,44,55.5,'2022-01-01',
                               '10:23:00','2022-05-01 10:30:00', '2022-05-01 10:30:00', 'BIG', 'BIG', 'BIG', 'BIG')");

        $query = $sqlite3Connection->query("SELECT id, min(name) name, min(tiny_int) tiny_int,min(small_int) small_int, min(big_int) big_int,
            min(float_val) float_val, min(double_val) double_val, min(real_val) real_val, min(decimal_val) decimal_val, 
            min(date_val) date_val, min(time_val) time_val, min(date_time) date_time, min(timestamp_val) timestamp_val,
            min(blob_val) blob_val, min(long_blob_val) long_blob_val, min(text_val) text_val, min(long_text_val) long_text_val
            FROM test_types GROUP BY id");

        $columns = $query->getColumns();
        $this->assertEquals(17, sizeof($columns));

        $this->assertEquals(new ResultSetColumn("id", "INTEGER", null), $columns[0]);
        $this->assertEquals(new ResultSetColumn("name", "VARCHAR", 5000), $columns[1]);
        $this->assertEquals(new ResultSetColumn("tiny_int", "BIGINT"), $columns[2]);
        $this->assertEquals(new ResultSetColumn("small_int", "BIGINT"), $columns[3]);
        $this->assertEquals(new ResultSetColumn("big_int", "BIGINT"), $columns[4]);
        $this->assertEquals(new ResultSetColumn("float_val", "DOUBLE"), $columns[5]);
        $this->assertEquals(new ResultSetColumn("double_val", "DOUBLE"), $columns[6]);
        $this->assertEquals(new ResultSetColumn("real_val", "DOUBLE"), $columns[7]);
        $this->assertEquals(new ResultSetColumn("decimal_val", "DOUBLE"), $columns[8]);
        $this->assertEquals(new ResultSetColumn("date_val", "VARCHAR", 5000), $columns[9]);
        $this->assertEquals(new ResultSetColumn("time_val", "VARCHAR", 5000), $columns[10]);
        $this->assertEquals(new ResultSetColumn("date_time", "VARCHAR", 5000), $columns[11]);
        $this->assertEquals(new ResultSetColumn("timestamp_val", "VARCHAR", 5000), $columns[12]);
        $this->assertEquals(new ResultSetColumn("blob_val", "VARCHAR", 5000), $columns[13]);
        $this->assertEquals(new ResultSetColumn("long_blob_val", "VARCHAR", 5000), $columns[14]);
        $this->assertEquals(new ResultSetColumn("text_val", "VARCHAR", 5000), $columns[15]);
        $this->assertEquals(new ResultSetColumn("long_text_val", "VARCHAR", 5000), $columns[16]);

    }


    public function testExecuteScriptHandlesChangeAndModifyStatementsCorrectly() {

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $sqlite3Connection->execute("DROP TABLE IF EXISTS test_types");
        $sqlite3Connection->execute("DROP TABLE IF EXISTS __test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33)";
        $sqlite3Connection->execute($query);
        $sqlite3Connection->execute("INSERT INTO test_types (id, name, tiny_int) VALUES (1, 'Mark', 25), (2, 'John', 66)");

        $sqlite3Connection->executeScript("ALTER TABLE test_types MODIFY COLUMN name VARCHAR(200) NOT NULL;");

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $metaData = $sqlite3Connection->getTableMetaData("test_types");
        $columns = $metaData->getColumns();
        $this->assertEquals(3, sizeof($columns));
        $this->assertEquals(new TableColumn("id", "INTEGER", null, null, null, true, true, false), $columns["id"]);
        $this->assertEquals(new TableColumn("name", "VARCHAR", 200, null, null, false, false, true), $columns["name"]);
        $this->assertEquals(new TableColumn("tiny_int", "TINYINT", null, null, 33, false, false, true), $columns["tiny_int"]);

        // Check data intact
        $results = $sqlite3Connection->query("SELECT * FROM test_types WHERE 1 = 1")->fetchAll();
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "tiny_int" => 25],
            ["id" => 2, "name" => "John", "tiny_int" => 66],

        ], $results);


        $sqlite3Connection->executeScript("ALTER TABLE test_types CHANGE COLUMN name big_name VARCHAR(200) NOT NULL, MODIFY COLUMN tiny_int FLOAT;");

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $metaData = $sqlite3Connection->getTableMetaData("test_types");
        $columns = $metaData->getColumns();
        $this->assertEquals(3, sizeof($columns));
        $this->assertEquals(new TableColumn("id", "INTEGER", null, null, null, true, true, false), $columns["id"]);
        $this->assertEquals(new TableColumn("big_name", "VARCHAR", 200, null, null, false, false, true), $columns["big_name"]);
        $this->assertEquals(new TableColumn("tiny_int", "FLOAT", null, null, null, false, false, false), $columns["tiny_int"]);

        $results = $sqlite3Connection->query("SELECT * FROM test_types WHERE 1 = 1")->fetchAll();
        $this->assertEquals([
            ["id" => 1, "big_name" => "Mark", "tiny_int" => 25],
            ["id" => 2, "big_name" => "John", "tiny_int" => 66],

        ], $results);
    }


    public function testExecuteScriptHandlesAddColumnAndDropColumnStatementsCorrectly() {

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $sqlite3Connection->execute("DROP TABLE IF EXISTS test_types");
        $sqlite3Connection->execute("DROP TABLE IF EXISTS __test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33)";
        $sqlite3Connection->execute($query);
        $sqlite3Connection->execute("INSERT INTO test_types (id, name, tiny_int) VALUES (1, 'Mark', 25), (2, 'John', 66)");

        $sqlite3Connection->executeScript("ALTER TABLE test_types ADD COLUMN description VARCHAR(1000) NOT NULL DEFAULT 'BOB', DROP COLUMN tiny_int;");

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $metaData = $sqlite3Connection->getTableMetaData("test_types");
        $columns = $metaData->getColumns();
        $this->assertEquals(3, sizeof($columns));
        $this->assertEquals(new TableColumn("id", "INTEGER", null, null, null, true, true, false), $columns["id"]);
        $this->assertEquals(new TableColumn("name", "VARCHAR", 500, null, null, false, false, false), $columns["name"]);
        $this->assertEquals(new TableColumn("description", "VARCHAR", 1000, null, "'BOB'", false, false, true), $columns["description"]);

        // Check data intact
        $results = $sqlite3Connection->query("SELECT * FROM test_types WHERE 1 = 1")->fetchAll();
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "description" => "BOB"],
            ["id" => 2, "name" => "John", "description" => "BOB"],

        ], $results);

    }

    public function testExecuteScriptHandlesDropAndAddPrimaryKeyStatementsCorrectly() {

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $sqlite3Connection->execute("DROP TABLE IF EXISTS test_types");
        $sqlite3Connection->execute("DROP TABLE IF EXISTS __test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33)";
        $sqlite3Connection->execute($query);
        $sqlite3Connection->execute("INSERT INTO test_types (id, name, tiny_int) VALUES (1, 'Mark', 25), (2, 'John', 66)");

        $sqlite3Connection->executeScript("ALTER TABLE test_types DROP PRIMARY KEY, ADD PRIMARY KEY (name)");


        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $metaData = $sqlite3Connection->getTableMetaData("test_types");
        $pkColumns = $metaData->getPrimaryKeyColumns();
        $this->assertEquals("name", $pkColumns["name"]->getName());

        $results = $sqlite3Connection->query("SELECT * FROM test_types WHERE 1 = 1")->fetchAll();
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "tiny_int" => 25],
            ["id" => 2, "name" => "John", "tiny_int" => 66],

        ], $results);


    }


    public function testIfExceptionOccursChangesAreRolledBackAndExceptionRaised() {

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $sqlite3Connection->execute("DROP TABLE IF EXISTS test_types");
        $sqlite3Connection->execute("DROP TABLE IF EXISTS __test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33)";
        $sqlite3Connection->execute($query);
        $sqlite3Connection->execute("INSERT INTO test_types (id, name, tiny_int) VALUES (1, 'Mark', 25), (2, 'Mark', 66)");

        try {
            $sqlite3Connection->executeScript("ALTER TABLE test_types DROP PRIMARY KEY, ADD PRIMARY KEY (name)");
            $this->fail("Should have thrown an exception here due to non unique valus in pk");
        } catch (SQLException $e) {
        }

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);

        $metaData = $sqlite3Connection->getTableMetaData("test_types");
        $pkColumns = $metaData->getPrimaryKeyColumns();
        $this->assertEquals("id", $pkColumns["id"]->getName());

        $results = $sqlite3Connection->query("SELECT * FROM test_types WHERE 1 = 1")->fetchAll();
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "tiny_int" => 25],
            ["id" => 2, "name" => "Mark", "tiny_int" => 66],

        ], $results);


    }


    public function testCanAddCustomFunctionForSQLite() {

        SQLite3DatabaseConnection::addCustomFunction(new TestCustomFunction());

        $sqlite3Connection = new SQLite3DatabaseConnection (["filename" => $this->dbLocation]);
        $results = $sqlite3Connection->query("SELECT TESTCUSTOM(4) testcustom");
        $this->assertEquals(8, $results->fetchAll()[0]["testcustom"]);

        $results = $sqlite3Connection->query("SELECT TESTCUSTOM(5) testcustom");
        $this->assertEquals(10, $results->fetchAll()[0]["testcustom"]);


    }


    public function testCanParseFunctionRemappings() {

        $sqlite3Connection = new SQLite3DatabaseConnection(["filename" => $this->dbLocation]);

        $sql = "EPOCH_SECONDS(test)";
        $sql = $sqlite3Connection->parseSQL($sql);
        $this->assertEquals("STRFTIME('%s',test)", $sql);

    }


}

?>
