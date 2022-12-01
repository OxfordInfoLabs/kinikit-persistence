<?php

namespace Kinikit\Persistence\Database\Vendors\PostgreSQL;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\PreparedStatement\BlobWrapper;
use Kinikit\Persistence\Database\PreparedStatement\WrongNumberOfPreparedStatementParametersException;

include_once "autoloader.php";

class PostgreSQLDatabaseConnectionTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var PostgreSQLDatabaseConnection
     */
    private $postgresqlDatabaseConnection;
    private $postgresqlDatabaseConnection1;

    /**
     * @throws \Kinikit\Persistence\Database\Connection\DatabaseConnectionException
     * @throws \Kinikit\Persistence\Database\Exception\SQLException
     */
    public function setUp(): void {

        $configParams = Configuration::instance()->getParametersMatchingPrefix("postgresql.db.", true);

        if (!$this->postgresqlDatabaseConnection)
            $this->postgresqlDatabaseConnection = new PostgreSQLDatabaseConnection($configParams);


        $this->postgresqlDatabaseConnection->execute("DROP TABLE IF EXISTS test_child");
        $this->postgresqlDatabaseConnection->execute("CREATE TABLE test_child(id SERIAL, note VARCHAR(20), parent_id INTEGER, PRIMARY KEY (id))");
        $this->postgresqlDatabaseConnection->query("DROP TABLE IF EXISTS test_child_multi_key");
        $this->postgresqlDatabaseConnection->query("CREATE TABLE test_child_multi_key (id SERIAL, description VARCHAR(20), parent_field1 INTEGER, parent_field2 VARCHAR(10), parent_field3 INTEGER, PRIMARY KEY (id))");

    }

    public function testCanExecuteCommandsAndGetDataWithResults() {

        $result = $this->postgresqlDatabaseConnection->execute("INSERT INTO test_child(note, parent_id) VALUES (?,?)", "Hello Boss", 12);
        $this->assertTrue($result);

        $result = $this->postgresqlDatabaseConnection->execute("INSERT INTO test_child(note, parent_id) VALUES ('Hello Dude', 13)");
        $this->assertTrue($result);


        $results = $this->postgresqlDatabaseConnection->query("SELECT * FROM test_child");
        $row1 = $results->nextRow();
        $this->assertEquals(["id" => 1, "note" => "Hello Boss", "parent_id" => 12], $row1);

    }

    public function testCanExecuteAPreparedStatementInMysql() {

        // Get the mysql connection object
        $postgresqlDatabaseConnection = $this->postgresqlDatabaseConnection;

        // Now create a prepared statement to execute
        $preparedStatement = $postgresqlDatabaseConnection->createPreparedStatement("INSERT INTO test_child (note, parent_id) VALUES (?,?)");

        $preparedStatement->execute(["Interesting One for", 567]);


        // Check that the execution succeeded
        $results = $postgresqlDatabaseConnection->query("SELECT * from test_child WHERE id = " . $postgresqlDatabaseConnection->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals("Interesting One for", $row ["note"]);
        $this->assertEquals(567, $row ["parent_id"]);

    }

    public function testPreparedStatementsWithBlobObjectsAreHandledCorrectlyAndStreamedToMysqlUsingSendLargeData() {

        // Get the mysql connection object
        $postgresqlDatabaseConnection = $this->postgresqlDatabaseConnection;

        $postgresqlDatabaseConnection->execute("DROP TABLE IF EXISTS test_with_blob");
        $postgresqlDatabaseConnection->execute("CREATE TABLE test_with_blob (id SERIAL, blob_data BYTEA, PRIMARY KEY (id))");

        $preparedStatement = $postgresqlDatabaseConnection->createPreparedStatement("INSERT INTO test_with_blob (blob_data) VALUES (?)");
        $preparedStatement->execute([new BlobWrapper ("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE")]);

        // Check it made it in
        $results = $postgresqlDatabaseConnection->query("SELECT * from test_with_blob WHERE id = " . $postgresqlDatabaseConnection->getLastAutoIncrementId());
        $row = $results->nextRow();

        $this->assertEquals("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE", $row ["blob_data"]);

        // Now do one via filename
        $preparedStatement = $postgresqlDatabaseConnection->createPreparedStatement("INSERT INTO test_with_blob (blob_data) VALUES (?)");
        $preparedStatement->execute([new BlobWrapper (null, "Database/Connection/testlargeobject.txt")]);

        // Now check it made it in.
        $results = $postgresqlDatabaseConnection->query("SELECT * from test_with_blob WHERE id = " . $postgresqlDatabaseConnection->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals(file_get_contents("Database/Connection/testlargeobject.txt"), $row ["blob_data"]);

    }

    public function testSQLExceptionThrownCorrectlyIfBadPreparedStatementExecuted() {

        // Get the mysql connection object
        $this->postgresqlDatabaseConnection1 = $this->postgresqlDatabaseConnection;
        $postgresqlDatabaseConnection = $this->postgresqlDatabaseConnection1;

        // Now try valid with unbound value
        $preparedStatement = $postgresqlDatabaseConnection->createPreparedStatement("INSERT INTO test_with_blob (blob_data) VALUES (?)");


        try {
            $preparedStatement->execute([]);
            $this->fail("Should have thrown here");
        } catch (WrongNumberOfPreparedStatementParametersException $e) {
            // Success
        }


        // Try invalid query
        $preparedStatement = $postgresqlDatabaseConnection->createPreparedStatement("INSERT INTO test_i_am_bad (blob_data) VALUES (?)");


        try {
            $preparedStatement->execute(["EXAMPLE"]);
            $this->fail("Should have thrown here");
        } catch (SQLException $e) {
            // Success
        }

        $this->assertTrue(true);

    }


    public function testCanGetTableColumnMetaData() {

        $this->postgresqlDatabaseConnection->execute("DROP TABLE IF EXISTS test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, 
            small_int SMALLINT, normal_int INTEGER, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(2,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB)";

        $this->postgresqlDatabaseConnection->execute($query);

        $tableColumns = $this->postgresqlDatabaseConnection->getTableColumnMetaData("test_types");

        print_r($tableColumns);


        $this->assertEquals(15, sizeof($tableColumns));
        $this->assertEquals(new TableColumn("id", ResultSetColumn::SQL_BIGINT, 20, null, "nextval('test_types_id_seq'::regclass)", true, true, true), $tableColumns["id"]);
        $this->assertEquals(new TableColumn("name", ResultSetColumn::SQL_VARCHAR, 500), $tableColumns["name"]);
        $this->assertEquals(new TableColumn("tiny_int", ResultSetColumn::SQL_SMALLINT, 6, null, 33, false, false, true), $tableColumns["tiny_int"]);
        $this->assertEquals(new TableColumn("small_int", ResultSetColumn::SQL_SMALLINT, 6), $tableColumns["small_int"]);
        $this->assertEquals(new TableColumn("normal_int", ResultSetColumn::SQL_INTEGER, 11), $tableColumns["normal_int"]);
        $this->assertEquals(new TableColumn("big_int", ResultSetColumn::SQL_BIGINT, 20), $tableColumns["big_int"]);
        $this->assertEquals(new TableColumn("float_val", ResultSetColumn::SQL_DOUBLE), $tableColumns["float_val"]);
        $this->assertEquals(new TableColumn("double_val", ResultSetColumn::SQL_DOUBLE), $tableColumns["double_val"]);
        $this->assertEquals(new TableColumn("real_val", ResultSetColumn::SQL_REAL), $tableColumns["real_val"]);
        $this->assertEquals(new TableColumn("decimal_val", ResultSetColumn::SQL_DECIMAL, 1, 2), $tableColumns["decimal_val"]);
        $this->assertEquals(new TableColumn("date_val", ResultSetColumn::SQL_DATE), $tableColumns["date_val"]);
        $this->assertEquals(new TableColumn("time_val", ResultSetColumn::SQL_TIME), $tableColumns["time_val"]);
        $this->assertEquals(new TableColumn("date_time", ResultSetColumn::SQL_DATE_TIME), $tableColumns["date_time"]);
        $this->assertEquals(new TableColumn("blob_val", TableColumn::SQL_LONGBLOB), $tableColumns["blob_val"]);
    }


    public function testExecuteScriptConvertsSQLLiteSyntaxToPostgreSQL() {

        $this->postgresqlDatabaseConnection->execute("DROP TABLE IF EXISTS test_create");

        $script = "
            CREATE TABLE test_create (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                number INTEGER,
                value VARCHAR,
                last_modifed DATETIME
            ) ;
        ";


        $this->postgresqlDatabaseConnection->executeScript($script);

        $metaData = $this->postgresqlDatabaseConnection->getTableMetaData("test_create");
        $this->assertEquals(4, sizeof($metaData->getColumns()));
        $this->assertEquals(1, sizeof($metaData->getPrimaryKeyColumns()));

    }


    public function testResultSetForQueriedMySQLReturnsValidResultSetColumnObjects() {


        $query = "DROP TABLE IF EXISTS test_all_types; CREATE TABLE test_all_types (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, 
            small_int SMALLINT, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(1,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB, long_blob_val LONGBLOB, text_val TEXT, long_text_val LONGTEXT
            )";

        $this->postgresqlDatabaseConnection->executeScript($query);

        $resultSet = $this->postgresqlDatabaseConnection->query("SELECT * FROM test_all_types");
        $columns = $resultSet->getColumns();



        $this->assertEquals(17, sizeof($columns));
        $this->assertEquals(new ResultSetColumn("id", TableColumn::SQL_BIGINT, 20, null), $columns[0]);
        $this->assertEquals(new ResultSetColumn("name", TableColumn::SQL_VARCHAR, 500), $columns[1]);
        $this->assertEquals(new ResultSetColumn("tiny_int", TableColumn::SQL_SMALLINT, 6), $columns[2]);
        $this->assertEquals(new ResultSetColumn("small_int", TableColumn::SQL_SMALLINT, 6), $columns[3]);
        $this->assertEquals(new ResultSetColumn("big_int", TableColumn::SQL_BIGINT, 20), $columns[4]);
        $this->assertEquals(new ResultSetColumn("float_val", TableColumn::SQL_DOUBLE), $columns[5]);
        $this->assertEquals(new ResultSetColumn("double_val", TableColumn::SQL_DOUBLE), $columns[6]);
        $this->assertEquals(new ResultSetColumn("real_val", TableColumn::SQL_REAL), $columns[7]);
        $this->assertEquals(new ResultSetColumn("decimal_val", TableColumn::SQL_DECIMAL), $columns[8]);
        $this->assertEquals(new ResultSetColumn("date_val", TableColumn::SQL_DATE), $columns[9]);
        $this->assertEquals(new ResultSetColumn("time_val", TableColumn::SQL_TIME), $columns[10]);
        $this->assertEquals(new ResultSetColumn("date_time", TableColumn::SQL_DATE_TIME), $columns[11]);
        $this->assertEquals(new ResultSetColumn("timestamp_val", TableColumn::SQL_DATE_TIME), $columns[12]);
        $this->assertEquals(new ResultSetColumn("blob_val", TableColumn::SQL_LONGBLOB), $columns[13]);
        $this->assertEquals(new ResultSetColumn("long_blob_val", TableColumn::SQL_LONGBLOB), $columns[14]);
        $this->assertEquals(new ResultSetColumn("text_val", TableColumn::SQL_BLOB), $columns[15]);
        $this->assertEquals(new ResultSetColumn("long_text_val", TableColumn::SQL_BLOB), $columns[16]);


        // Try another query
        $resultSet = $this->postgresqlDatabaseConnection->query("SELECT * FROM test_all_types WHERE (id = ? AND name = ?) 
                                OR (id = ? AND name = ?)", 33, "Mark", 44, "Luke");
        $columns = $resultSet->getColumns();

        $this->assertEquals(17, sizeof($columns));

        $this->assertEquals(new ResultSetColumn("id", TableColumn::SQL_BIGINT, 20, null), $columns[0]);
        $this->assertEquals(new ResultSetColumn("name", TableColumn::SQL_VARCHAR, 500), $columns[1]);


    }

}