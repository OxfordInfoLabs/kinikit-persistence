<?php

namespace Kinikit\Persistence\Database\Vendors\MySQL;


use Kinikit\Core\Configuration\Configuration;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableIndexColumn;
use Kinikit\Persistence\Database\PreparedStatement\BlobWrapper;
use Kinikit\Persistence\Database\PreparedStatement\ColumnType;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\PreparedStatement\WrongNumberOfPreparedStatementParametersException;
use mysql_xdevapi\Table;


include_once "autoloader.php";

/**
 * Test cases for the MySQL Database connection object
 *
 */
class MySQLDatabaseConnectionTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var MySQLDatabaseConnection
     */
    private $mysqlDatabaseConnection;


    /**
     * @throws \Kinikit\Persistence\Database\Connection\DatabaseConnectionException
     * @throws \Kinikit\Persistence\Database\Exception\SQLException
     */
    public function setUp(): void {

        $configParams = Configuration::instance()->getParametersMatchingPrefix("mysql.db.", true);

        if (!$this->mysqlDatabaseConnection)
            $this->mysqlDatabaseConnection = new MySQLDatabaseConnection($configParams);


        $this->mysqlDatabaseConnection->execute("DROP TABLE IF EXISTS test_child");
        $this->mysqlDatabaseConnection->execute("CREATE TABLE test_child(id INTEGER AUTO_INCREMENT, note VARCHAR(20), parent_id INTEGER, PRIMARY KEY (id))");
        $this->mysqlDatabaseConnection->query("DROP TABLE IF EXISTS test_child_multi_key");
        $this->mysqlDatabaseConnection->query("CREATE TABLE test_child_multi_key (id INTEGER AUTO_INCREMENT, description VARCHAR(20), parent_field1 INTEGER, parent_field2 VARCHAR(10), parent_field3 INTEGER, PRIMARY KEY (id))");


    }


    public function testCanExecuteCommandsAndGetDataWithResults() {

        $result = $this->mysqlDatabaseConnection->execute("INSERT INTO test_child(note, parent_id) VALUES (?,?)", "Hello Boss", 12);
        $this->assertTrue($result);

        $result = $this->mysqlDatabaseConnection->execute("INSERT INTO test_child(note, parent_id) VALUES ('Hello Dude', 13)");
        $this->assertTrue($result);


        $results = $this->mysqlDatabaseConnection->query("SELECT * FROM test_child");
        $row1 = $results->nextRow();
        $this->assertEquals(["id" => 1, "note" => "Hello Boss", "parent_id" => 12], $row1);


    }


    public function testIfExceptionRaisedWithoutARetryStatusExecutionsAreNotRetried() {
        if (file_exists("application.log"))
            unlink("application.log");

        try {
            $this->mysqlDatabaseConnection->execute("INSERT BAD QUERY");
            $this->fail("Should have thrown here");
        } catch (SQLException $e) {
        }

        $this->assertFalse(file_exists("application.log"));

    }

    /**
     * @return void
     * @throws SQLException
     * @throws \Kinikit\Persistence\Database\Connection\DatabaseConnectionException
     *
     * @group nontravis
     */
    public function testIfExceptionRaisedWithARetryStatusExecutionsAreRetriedAccordingToNumberOfTimes() {

        if (file_exists("application.log"))
            unlink("application.log");

        $configParams = Configuration::instance()->getParametersMatchingPrefix("mysql.db.", true);
        $connection2 = new MySQLDatabaseConnection($configParams);
        $connection2->execute("SET SESSION innodb_lock_wait_timeout = 1");
        $this->mysqlDatabaseConnection->execute("SET SESSION innodb_lock_wait_timeout = 1");

        $this->mysqlDatabaseConnection->execute("INSERT INTO test_child (id) VALUES (1)");

        $connection2->beginTransaction();
        $connection2->execute("SELECT * FROM test_child WHERE id = 1 LOCK IN SHARE MODE");


        try {
            $this->mysqlDatabaseConnection->beginTransaction();
            $this->mysqlDatabaseConnection->execute("UPDATE test_child SET id = 2 WHERE id = 1");
        } catch (SQLException $e) {
            // We expect this but with a retry according to configured retry times
        }

        $log = file_get_contents("application.log");
        $this->assertStringContainsString("MySQL Statement Retry 1", $log);
        $this->assertStringContainsString("MySQL Statement Retry 2", $log);
        $this->assertStringNotContainsString("MySQL Statement Retry 3", $log);

        // Test query function as well.
        unlink("application.log");

        try {
            $this->mysqlDatabaseConnection->query("UPDATE test_child SET id = 2 WHERE id = 1");
        } catch (SQLException $e) {
            // We expect this but with a retry according to configured retry times
        }
        $log = file_get_contents("application.log");
        $this->assertStringContainsString("MySQL Query Retry 1", $log);
        $this->assertStringContainsString("MySQL Query Retry 2", $log);
        $this->assertStringNotContainsString("MySQL Query Retry 3", $log);

        $this->mysqlDatabaseConnection->rollback();

    }


    public function testCanExecuteAPreparedStatementInMysql() {

        // Get the mysql connection object
        $mysqlConnection = $this->mysqlDatabaseConnection;

        // Now create a prepared statement to execute
        $preparedStatement = $mysqlConnection->createPreparedStatement("INSERT INTO test_child (note, parent_id) VALUES (?,?)");

        $preparedStatement->execute(["Interesting One for", 567]);


        // Check that the execution succeeded
        $results = $mysqlConnection->query("SELECT * from test_child WHERE id = " . $mysqlConnection->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals("Interesting One for", $row ["note"]);
        $this->assertEquals(567, $row ["parent_id"]);

    }

    public function testPreparedStatementsWithBlobObjectsAreHandledCorrectlyAndStreamedToMysqlUsingSendLargeData() {

        // Get the mysql connection object
        $mysqlConnection = $this->mysqlDatabaseConnection;

        $mysqlConnection->execute("DROP TABLE IF EXISTS test_with_blob");
        $mysqlConnection->execute("CREATE TABLE test_with_blob (id INTEGER AUTO_INCREMENT, blob_data LONGBLOB, PRIMARY KEY (id))");

        $preparedStatement = $mysqlConnection->createPreparedStatement("INSERT INTO test_with_blob (blob_data) VALUES (?)");
        $preparedStatement->execute([new BlobWrapper ("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE")]);

        // Check it made it in
        $results = $mysqlConnection->query("SELECT * from test_with_blob WHERE id = " . $mysqlConnection->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE", $row ["blob_data"]);

        // Now do one via filename
        $preparedStatement = $mysqlConnection->createPreparedStatement("INSERT INTO test_with_blob (blob_data) VALUES (?)");
        $preparedStatement->execute([new BlobWrapper (null, "Database/Connection/testlargeobject.txt")]);

        // Now check it made it in.
        $results = $mysqlConnection->query("SELECT * from test_with_blob WHERE id = " . $mysqlConnection->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals(file_get_contents("Database/Connection/testlargeobject.txt"), $row ["blob_data"]);

    }

    public function testSQLExceptionThrownCorrectlyIfBadPreparedStatementExecuted() {

        // Get the mysql connection object
        $mysqlConnection = $this->mysqlDatabaseConnection;

        try {
            $preparedStatement = $mysqlConnection->createPreparedStatement("INSERT INTO test_with_badtable (blob_data) VALUES (?)");
            $this->fail("Should have thrown here");
        } catch (SQLException $e) {
            // Success
        }

        // Now try one with unbound value.
        $preparedStatement = $mysqlConnection->createPreparedStatement("INSERT INTO test_with_blob (blob_data) VALUES (?)");


        try {
            $preparedStatement->execute([]);
            $this->fail("Should have thrown here");
        } catch (WrongNumberOfPreparedStatementParametersException $e) {
            // Success
        }

        $this->assertTrue(true);

    }


    public function testCanGetTableColumnMetaData() {

        $this->mysqlDatabaseConnection->execute("DROP TABLE IF EXISTS test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, 
            small_int SMALLINT, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(1,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB, json_val JSON)";

        $this->mysqlDatabaseConnection->execute($query);

        $tableColumns = $this->mysqlDatabaseConnection->getTableColumnMetaData("test_types");

        $this->assertEquals(15, sizeof($tableColumns));
        $this->assertEquals(new TableColumn("id", "INT", 11, null, null, true, true, true), $tableColumns["id"]);
        $this->assertEquals(new TableColumn("name", "VARCHAR", 500), $tableColumns["name"]);
        $this->assertEquals(new TableColumn("tiny_int", "TINYINT", 4, null, 33, false, false, true), $tableColumns["tiny_int"]);
        $this->assertEquals(new TableColumn("small_int", "SMALLINT", 6), $tableColumns["small_int"]);
        $this->assertEquals(new TableColumn("big_int", "BIGINT", 20), $tableColumns["big_int"]);
        $this->assertEquals(new TableColumn("float_val", "FLOAT"), $tableColumns["float_val"]);
        $this->assertEquals(new TableColumn("double_val", "DOUBLE"), $tableColumns["double_val"]);
        $this->assertEquals(new TableColumn("real_val", "DOUBLE"), $tableColumns["real_val"]);
        $this->assertEquals(new TableColumn("decimal_val", "DECIMAL", 1, 1), $tableColumns["decimal_val"]);
        $this->assertEquals(new TableColumn("date_val", "DATE"), $tableColumns["date_val"]);
        $this->assertEquals(new TableColumn("time_val", "TIME"), $tableColumns["time_val"]);
        $this->assertEquals(new TableColumn("date_time", "DATETIME"), $tableColumns["date_time"]);
//        $this->assertEquals(new TableColumn("timestamp_val", "TIMESTAMP", null, null, "current_timestamp()", false, false, true), $tableColumns["timestamp_val"]);
        $this->assertEquals(new TableColumn("blob_val", "BLOB"), $tableColumns["blob_val"]);
        $this->assertEquals(new TableColumn("json_val", "JSON"), $tableColumns["json_val"]);
    }


    public function testCanGetTableIndexMetaData() {

        $this->mysqlDatabaseConnection->execute("DROP TABLE IF EXISTS test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, 
            small_int SMALLINT, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(1,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB)";

        $this->mysqlDatabaseConnection->execute($query);


        $this->mysqlDatabaseConnection->execute("CREATE INDEX test_index ON test_types (name, tiny_int, float_val)");
        $this->mysqlDatabaseConnection->execute("CREATE INDEX test_index_2 ON test_types (name(50), double_val)");


        $indexes = $this->mysqlDatabaseConnection->getTableIndexMetaData("test_types");

        $this->assertEquals(2, sizeof($indexes));

        $this->assertEquals(new TableIndex("test_index", [
            new TableIndexColumn("name"),
            new TableIndexColumn("tiny_int"),
            new TableIndexColumn("float_val")
        ]), $indexes[0]);

        $this->assertEquals(new TableIndex("test_index_2", [
            new TableIndexColumn("name", 50),
            new TableIndexColumn("double_val")
        ]), $indexes[1]);

    }


    public function testResultSetForQueriedMySQLReturnsValidResultSetColumnObjects() {


        $query = "DROP TABLE IF EXISTS test_all_types; CREATE TABLE test_all_types (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, 
            small_int SMALLINT, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(1,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB, 
            long_blob_val LONGBLOB, text_val TEXT, long_text_val LONGTEXT, json_val JSON
            )";

        $this->mysqlDatabaseConnection->executeScript($query);

        $resultSet = $this->mysqlDatabaseConnection->query("SELECT * FROM test_all_types");
        $columns = $resultSet->getColumns();

        $this->assertEquals(18, sizeof($columns));
        $this->assertEquals(new ResultSetColumn("id", TableColumn::SQL_INTEGER, 11, null), $columns[0]);
        $this->assertGreaterThan(200, $columns[1]->getLength());
        $this->assertEquals(new ResultSetColumn("tiny_int", TableColumn::SQL_TINYINT, 4), $columns[2]);
        $this->assertEquals(new ResultSetColumn("small_int", TableColumn::SQL_SMALLINT, 6), $columns[3]);
        $this->assertEquals(new ResultSetColumn("big_int", TableColumn::SQL_BIGINT, 20), $columns[4]);
        $this->assertEquals(new ResultSetColumn("float_val", TableColumn::SQL_FLOAT, 12, 31), $columns[5]);
        $this->assertEquals(new ResultSetColumn("double_val", TableColumn::SQL_DOUBLE, 22, 31), $columns[6]);
        $this->assertEquals(new ResultSetColumn("real_val", TableColumn::SQL_DOUBLE, 22, 31), $columns[7]);
        $this->assertEquals(new ResultSetColumn("decimal_val", TableColumn::SQL_DECIMAL, 3, 1), $columns[8]);
        $this->assertEquals(new ResultSetColumn("date_val", TableColumn::SQL_DATE), $columns[9]);
        $this->assertEquals(new ResultSetColumn("time_val", TableColumn::SQL_TIME), $columns[10]);
        $this->assertEquals(new ResultSetColumn("date_time", TableColumn::SQL_DATE_TIME), $columns[11]);
        $this->assertEquals(new ResultSetColumn("timestamp_val", TableColumn::SQL_TIMESTAMP), $columns[12]);
        $this->assertEquals(new ResultSetColumn("blob_val", TableColumn::SQL_BLOB), $columns[13]);
        $this->assertEquals(new ResultSetColumn("long_blob_val", TableColumn::SQL_LONGBLOB), $columns[14]);
        $this->assertEquals(new ResultSetColumn("text_val", TableColumn::SQL_BLOB), $columns[15]);
        $this->assertEquals(new ResultSetColumn("long_text_val", TableColumn::SQL_LONGBLOB), $columns[16]);
        $this->assertEquals(new ResultSetColumn("json_val", TableColumn::SQL_JSON), $columns[17]);


        // Try another query
        $resultSet = $this->mysqlDatabaseConnection->query("SELECT * FROM test_all_types WHERE (id = ? AND name = ?) 
                                OR (id = ? AND name = ?)", 33, "Mark", 44, "Luke");
        $columns = $resultSet->getColumns();

        $this->assertEquals(18, sizeof($columns));

        $this->assertEquals(new ResultSetColumn("id", TableColumn::SQL_INTEGER, 11, null), $columns[0]);
        $this->assertGreaterThan(200, $columns[1]->getLength());


    }


    public function testDecimalValuesAreCorrectlyMappedToNumericTypesWhenReturned() {
        $query = "DROP TABLE IF EXISTS test_decimal; CREATE TABLE test_decimal ( int_val INTEGER )";
        $this->mysqlDatabaseConnection->executeScript($query);

        $this->mysqlDatabaseConnection->execute("INSERT INTO test_decimal VALUES (9)");

        $results = $this->mysqlDatabaseConnection->query("SELECT ROUND(?/int_val, ?) val FROM test_decimal", 1, 2);
        $row = $results->nextRow();

        $this->assertSame(0.11, $row["val"]);

    }

    public function testSQLModeIsModifiedToRemoveUndesirableClauses() {

        $sqlMode = $this->mysqlDatabaseConnection->query("SELECT @@sql_mode")->fetchAll()[0]["@@sql_mode"];
        $this->assertStringNotContainsString("ONLY_FULL_GROUP_BY", $sqlMode);
        $this->assertStringNotContainsString("STRICT_TRANS_TABLES", $sqlMode);


    }


}

?>
