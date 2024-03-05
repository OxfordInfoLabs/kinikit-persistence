<?php

namespace Kinikit\Persistence\Database\Vendors\PostgreSQL;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableIndexColumn;
use Kinikit\Persistence\Database\PreparedStatement\BlobWrapper;
use Kinikit\Persistence\Database\PreparedStatement\WrongNumberOfPreparedStatementParametersException;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class PostgreSQLDatabaseConnectionTest extends TestCase {

    /**
     * @var PostgreSQLDatabaseConnection
     */
    private $postgreSQLDatabaseConnection;
    private $postgresqlDatabaseConnection1;

    /**
     * @throws \Kinikit\Persistence\Database\Connection\DatabaseConnectionException
     * @throws \Kinikit\Persistence\Database\Exception\SQLException
     */
    public function setUp(): void {

        $configParams = Configuration::instance()->getParametersMatchingPrefix("postgresql.db.", true);

        if (!$this->postgreSQLDatabaseConnection)
            $this->postgreSQLDatabaseConnection = new PostgreSQLDatabaseConnection($configParams);


        $this->postgreSQLDatabaseConnection->execute("DROP TABLE IF EXISTS test_child");
        $this->postgreSQLDatabaseConnection->execute("CREATE TABLE test_child(id SERIAL, note VARCHAR(20), parent_id INTEGER, PRIMARY KEY (id))");
        $this->postgreSQLDatabaseConnection->query("DROP TABLE IF EXISTS test_child_multi_key");
        $this->postgreSQLDatabaseConnection->query("CREATE TABLE test_child_multi_key (id SERIAL, description VARCHAR(20), parent_field1 INTEGER, parent_field2 VARCHAR(10), parent_field3 INTEGER, PRIMARY KEY (id))");

    }

    public function testCanExecuteCommandsAndGetDataWithResults() {

        $result = $this->postgreSQLDatabaseConnection->execute("INSERT INTO test_child(note, parent_id) VALUES (?,?)", "Hello Boss", 12);
        $this->assertTrue($result);

        $result = $this->postgreSQLDatabaseConnection->execute("INSERT INTO test_child(note, parent_id) VALUES ('Hello Dude', 13)");
        $this->assertTrue($result);


        $results = $this->postgreSQLDatabaseConnection->query("SELECT * FROM test_child");
        $row1 = $results->nextRow();
        $this->assertEquals(["id" => 1, "note" => "Hello Boss", "parent_id" => 12], $row1);

    }

    public function testCanExecuteAPreparedStatementInMysql() {

        // Get the mysql connection object
        $postgresqlDatabaseConnection = $this->postgreSQLDatabaseConnection;

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
        $postgresqlDatabaseConnection = $this->postgreSQLDatabaseConnection;

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
        $this->postgresqlDatabaseConnection1 = $this->postgreSQLDatabaseConnection;
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

        $this->postgreSQLDatabaseConnection->execute("DROP TABLE IF EXISTS test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, 
            small_int SMALLINT, normal_int INTEGER, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(2,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB)";

        $this->postgreSQLDatabaseConnection->execute($query);

        $tableColumns = $this->postgreSQLDatabaseConnection->getTableColumnMetaData("test_types");

        $this->assertEquals(15, sizeof($tableColumns));
        $this->assertEquals(new TableColumn("id", ResultSetColumn::SQL_BIGINT, null, 64, null, true, true, true), $tableColumns["id"]);
        $this->assertEquals(new TableColumn("name", ResultSetColumn::SQL_VARCHAR, 500), $tableColumns["name"]);
        $this->assertEquals(new TableColumn("tiny_int", ResultSetColumn::SQL_SMALLINT, null, 16, 33, false, false, true), $tableColumns["tiny_int"]);
        $this->assertEquals(new TableColumn("small_int", ResultSetColumn::SQL_SMALLINT, null, 16), $tableColumns["small_int"]);
        $this->assertEquals(new TableColumn("normal_int", ResultSetColumn::SQL_INTEGER, null, 32), $tableColumns["normal_int"]);
        $this->assertEquals(new TableColumn("big_int", ResultSetColumn::SQL_BIGINT, null, 64), $tableColumns["big_int"]);
        $this->assertEquals(new TableColumn("float_val", ResultSetColumn::SQL_DOUBLE), $tableColumns["float_val"]);
        $this->assertEquals(new TableColumn("double_val", ResultSetColumn::SQL_DOUBLE), $tableColumns["double_val"]);
        $this->assertEquals(new TableColumn("real_val", ResultSetColumn::SQL_REAL), $tableColumns["real_val"]);
        $this->assertEquals(new TableColumn("decimal_val", ResultSetColumn::SQL_DECIMAL, null, 2), $tableColumns["decimal_val"]);
        $this->assertEquals(new TableColumn("date_val", ResultSetColumn::SQL_DATE), $tableColumns["date_val"]);
        $this->assertEquals(new TableColumn("time_val", ResultSetColumn::SQL_TIME), $tableColumns["time_val"]);
        $this->assertEquals(new TableColumn("date_time", ResultSetColumn::SQL_DATE_TIME), $tableColumns["date_time"]);
        $this->assertEquals(new TableColumn("blob_val", TableColumn::SQL_LONGBLOB), $tableColumns["blob_val"]);

        // No id this time
        $this->postgreSQLDatabaseConnection->execute("DROP TABLE IF EXISTS test_types");

        $query = "CREATE TABLE test_types (name VARCHAR(500))";

        $this->postgreSQLDatabaseConnection->execute($query);

        $tableColumns = $this->postgreSQLDatabaseConnection->getTableColumnMetaData("test_types");

        $this->assertEquals(1, sizeof($tableColumns));
        $this->assertEquals(new TableColumn("name", ResultSetColumn::SQL_VARCHAR, 500), $tableColumns["name"]);
    }


    public function testCanGetTableIndexMetaData() {

        $this->postgreSQLDatabaseConnection->execute("DROP TABLE IF EXISTS test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, 
            small_int SMALLINT, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(1,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB)";

        $this->postgreSQLDatabaseConnection->execute($query);


        $this->postgreSQLDatabaseConnection->execute("CREATE INDEX test_index ON test_types (name, tiny_int, float_val)");
        $this->postgreSQLDatabaseConnection->execute("CREATE INDEX test_index_2 ON test_types (name, double_val)");


        $indexes = $this->postgreSQLDatabaseConnection->getTableIndexMetaData("test_types");

        $this->assertEquals(2, sizeof($indexes));

        $this->assertEquals(new TableIndex("test_index", [
            new TableIndexColumn("name"),
            new TableIndexColumn("tiny_int"),
            new TableIndexColumn("float_val")
        ]), $indexes[0]);

        $this->assertEquals(new TableIndex("test_index_2", [
            new TableIndexColumn("name"),
            new TableIndexColumn("double_val")
        ]), $indexes[1]);

    }

    public function testExecuteScriptConvertsSQLLiteSyntaxToPostgreSQL() {

        $this->postgreSQLDatabaseConnection->execute("DROP TABLE IF EXISTS test_create");

        $script = "
            CREATE TABLE test_create (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                number INTEGER,
                value VARCHAR,
                last_modifed DATETIME
            ) ;
        ";


        $this->postgreSQLDatabaseConnection->executeScript($script);

        $metaData = $this->postgreSQLDatabaseConnection->getTableMetaData("test_create");
        $this->assertEquals(4, sizeof($metaData->getColumns()));
        $this->assertEquals(1, sizeof($metaData->getPrimaryKeyColumns()));

    }

    public function testExecuteScriptHandlesChangeAndModifyStatementsCorrectly() {

        $configParams = Configuration::instance()->getParametersMatchingPrefix("postgresql.db.", true);
        $postgreSQLConnection = new PostgreSQLDatabaseConnection($configParams);

        $postgreSQLConnection->execute("DROP TABLE IF EXISTS test_types");
        $postgreSQLConnection->execute("DROP TABLE IF EXISTS __test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), small_int SMALLINT NOT NULL DEFAULT 33)";
        $postgreSQLConnection->execute($query);
        $postgreSQLConnection->execute("INSERT INTO test_types (id, name, small_int) VALUES (1, 'Mark', 25), (2, 'John', 66)");

        $postgreSQLConnection->executeScript("ALTER TABLE test_types MODIFY COLUMN name VARCHAR(200) NOT NULL;");

        $postgreSQLConnection = new PostgreSQLDatabaseConnection($configParams);

        $metaData = $postgreSQLConnection->getTableMetaData("test_types");
        $columns = $metaData->getColumns();
        $this->assertEquals(3, sizeof($columns));
        $this->assertEquals(new TableColumn("id", "BIGINT", null, 64, null, true, true, false), $columns["id"]);
        $this->assertEquals(new TableColumn("name", "VARCHAR", 200, null, null, false, false, true), $columns["name"]);
        $this->assertEquals(new TableColumn("small_int", "SMALLINT", null, 16, 33, false, false, true), $columns["small_int"]);

        // Check data intact
        $results = $postgreSQLConnection->query("SELECT * FROM test_types WHERE 1 = 1")->fetchAll();
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "small_int" => 25],
            ["id" => 2, "name" => "John", "small_int" => 66],

        ], $results);


        $postgreSQLConnection->executeScript("ALTER TABLE test_types CHANGE COLUMN name big_name VARCHAR(200) NOT NULL, MODIFY COLUMN small_int FLOAT;");

        $postgreSQLConnection = new PostgreSQLDatabaseConnection($configParams);

        $metaData = $postgreSQLConnection->getTableMetaData("test_types");
        $columns = $metaData->getColumns();
        $this->assertEquals(3, sizeof($columns));
        $this->assertEquals(new TableColumn("id", "BIGINT", null, 64, null, true, true, false), $columns["id"]);
        $this->assertEquals(new TableColumn("big_name", "VARCHAR", 200, null, null, false, false, true), $columns["big_name"]);
        $this->assertEquals(new TableColumn("small_int", "DOUBLE", 0, null, 33, false, false, false), $columns["small_int"]);

        $results = $postgreSQLConnection->query("SELECT * FROM test_types WHERE 1 = 1")->fetchAll();
        $this->assertEquals([
            ["id" => 1, "big_name" => "Mark", "small_int" => 25],
            ["id" => 2, "big_name" => "John", "small_int" => 66],

        ], $results);
    }

    public function testExecuteScriptHandlesAddColumnAndDropColumnStatementsCorrectly() {

        $configParams = Configuration::instance()->getParametersMatchingPrefix("postgresql.db.", true);
        $postgreSQLConnection = new PostgreSQLDatabaseConnection($configParams);

        $postgreSQLConnection->execute("DROP TABLE IF EXISTS test_types");
        $postgreSQLConnection->execute("DROP TABLE IF EXISTS __test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500), small_int SMALLINT NOT NULL DEFAULT 33)";
        $postgreSQLConnection->execute($query);
        $postgreSQLConnection->execute("INSERT INTO test_types (id, name, small_int) VALUES (1, 'Mark', 25), (2, 'John', 66)");

        $postgreSQLConnection->executeScript("ALTER TABLE test_types ADD COLUMN description VARCHAR(1000) NOT NULL DEFAULT 'BOB', DROP COLUMN small_int;");

        $postgreSQLConnection = new PostgreSQLDatabaseConnection($configParams);

        $metaData = $postgreSQLConnection->getTableMetaData("test_types");
        $columns = $metaData->getColumns();
        $this->assertEquals(3, sizeof($columns));
        $this->assertEquals(new TableColumn("id", "BIGINT", null, 64, null, true, true, false), $columns["id"]);
        $this->assertEquals(new TableColumn("name", "VARCHAR", 500, null, null, false, false, false), $columns["name"]);
        $this->assertEquals(new TableColumn("description", "VARCHAR", 1000, null, "'BOB'::character varying", false, false, true), $columns["description"]);

        // Check data intact
        $results = $postgreSQLConnection->query("SELECT * FROM test_types WHERE 1 = 1")->fetchAll();
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "description" => "BOB"],
            ["id" => 2, "name" => "John", "description" => "BOB"],

        ], $results);

    }

    public function testExecuteScriptHandlesDropAndAddPrimaryKeyStatementsCorrectly() {

        $configParams = Configuration::instance()->getParametersMatchingPrefix("postgresql.db.", true);
        $postgreSQLConnection = new PostgreSQLDatabaseConnection($configParams);

        $postgreSQLConnection->execute("DROP TABLE IF EXISTS test_types");
        $postgreSQLConnection->execute("DROP TABLE IF EXISTS __test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY, name VARCHAR(500), small_int SMALLINT NOT NULL DEFAULT 33)";
        $postgreSQLConnection->execute($query);
        $postgreSQLConnection->execute("INSERT INTO test_types (id, name, small_int) VALUES (1, 'Mark', 25), (2, 'John', 66)");

        $postgreSQLConnection->executeScript("ALTER TABLE test_types DROP PRIMARY KEY, ADD PRIMARY KEY (name)");


        $configParams = Configuration::instance()->getParametersMatchingPrefix("postgresql.db.", true);
        $postgreSQLConnection = new PostgreSQLDatabaseConnection($configParams);

        $metaData = $postgreSQLConnection->getTableMetaData("test_types");
        $pkColumns = $metaData->getPrimaryKeyColumns();
        $this->assertEquals("name", $pkColumns["name"]->getName());

        $results = $postgreSQLConnection->query("SELECT * FROM test_types WHERE 1 = 1")->fetchAll();
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "small_int" => 25],
            ["id" => 2, "name" => "John", "small_int" => 66],

        ], $results);

    }


    public function testIfExceptionOccursChangesAreRolledBackAndExceptionRaised() {

        $configParams = Configuration::instance()->getParametersMatchingPrefix("postgresql.db.", true);
        $postgreSQLConnection = new PostgreSQLDatabaseConnection($configParams);

        $postgreSQLConnection->execute("DROP TABLE IF EXISTS test_types");
        $postgreSQLConnection->execute("DROP TABLE IF EXISTS __test_types");

        $query = "CREATE TABLE test_types (id INTEGER PRIMARY KEY, name VARCHAR(500), small_int SMALLINT NOT NULL DEFAULT 33)";
        $postgreSQLConnection->execute($query);
        $postgreSQLConnection->execute("INSERT INTO test_types (id, name, small_int) VALUES (1, 'Mark', 25), (2, 'Mark', 66)");

        try {
            $postgreSQLConnection->executeScript("ALTER TABLE test_types DROP PRIMARY KEY, ADD PRIMARY KEY (name)");
            $this->fail("Should have thrown an exception here due to non unique valus in pk");
        } catch (SQLException $e) {
        }

        $configParams = Configuration::instance()->getParametersMatchingPrefix("postgresql.db.", true);
        $postgreSQLConnection = new PostgreSQLDatabaseConnection($configParams);

        $metaData = $postgreSQLConnection->getTableMetaData("test_types");
        $pkColumns = $metaData->getPrimaryKeyColumns();
        $this->assertEquals("id", $pkColumns["id"]->getName());

        $results = $postgreSQLConnection->query("SELECT * FROM test_types WHERE 1 = 1")->fetchAll();
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "small_int" => 25],
            ["id" => 2, "name" => "Mark", "small_int" => 66],

        ], $results);


    }

    public function testResultSetForQueriedMySQLReturnsValidResultSetColumnObjects() {


        $query = "DROP TABLE IF EXISTS test_all_types; CREATE TABLE test_all_types (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(500), tiny_int TINYINT NOT NULL DEFAULT 33, 
            small_int SMALLINT, big_int BIGINT, 
            float_val FLOAT, double_val DOUBLE, real_val REAL, decimal_val DECIMAL(1,1), date_val DATE,
            time_val TIME, date_time DATETIME, timestamp_val TIMESTAMP, blob_val BLOB, long_blob_val LONGBLOB, text_val TEXT, long_text_val LONGTEXT
            )";

        $this->postgreSQLDatabaseConnection->executeScript($query);

        $resultSet = $this->postgreSQLDatabaseConnection->query("SELECT * FROM test_all_types");
        $columns = $resultSet->getColumns();


        $this->assertEquals(17, sizeof($columns));
        $this->assertEquals(new ResultSetColumn("id", TableColumn::SQL_BIGINT, null, null), $columns[0]);
        $this->assertEquals(new ResultSetColumn("name", TableColumn::SQL_VARCHAR, 500), $columns[1]);
        $this->assertEquals(new ResultSetColumn("tiny_int", TableColumn::SQL_SMALLINT), $columns[2]);
        $this->assertEquals(new ResultSetColumn("small_int", TableColumn::SQL_SMALLINT), $columns[3]);
        $this->assertEquals(new ResultSetColumn("big_int", TableColumn::SQL_BIGINT), $columns[4]);
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
        $resultSet = $this->postgreSQLDatabaseConnection->query("SELECT * FROM test_all_types WHERE (id = ? AND name = ?) 
                                OR (id = ? AND name = ?)", 33, "Mark", 44, "Luke");
        $columns = $resultSet->getColumns();

        $this->assertEquals(17, sizeof($columns));

        $this->assertEquals(new ResultSetColumn("id", TableColumn::SQL_BIGINT), $columns[0]);
        $this->assertEquals(new ResultSetColumn("name", TableColumn::SQL_VARCHAR, 500), $columns[1]);


    }
    public function testCanMapFunctionsCorrectlyWhenParsingSQL() {

        $sql = "IFNULL(condition)";
        $result = $this->postgreSQLDatabaseConnection->parseSQL($sql);
        $this->assertEquals("COALESCE(condition)", $result);

        $sql = "GROUP_CONCAT(first,second)";
        $result = $this->postgreSQLDatabaseConnection->parseSQL($sql);
        $this->assertEquals("STRING_AGG(first,second)", $result);

        $sql = "INSTR(a,b)";
        $result = $this->postgreSQLDatabaseConnection->parseSQL($sql);
        $this->assertEquals("POSITION(a IN b)", $result);

        $sql = "EPOCH_SECONDS(test)";
        $result = $this->postgreSQLDatabaseConnection->parseSQL($sql);
        $this->assertEquals("EXTRACT(EPOCH FROM test)", $result);

    }

    public function testCanSanitiseAutoIncrementStrings() {

        $this->assertEquals("'id' BIGSERIAL", $this->postgreSQLDatabaseConnection->sanitiseAutoIncrementString("'id' INTEGER AUTOINCREMENT"));
        $this->assertEquals("'col' BIGSERIAL", $this->postgreSQLDatabaseConnection->sanitiseAutoIncrementString("'col' BIGINT AUTOINCREMENT"));
        $this->assertEquals("'this' BIGSERIAL", $this->postgreSQLDatabaseConnection->sanitiseAutoIncrementString("'this' SMALLINT AUTOINCREMENT"));
        $this->assertEquals("'id' BIGSERIAL PRIMARY KEY", $this->postgreSQLDatabaseConnection->sanitiseAutoIncrementString("'id' INTEGER PRIMARY KEY AUTOINCREMENT"));
        $this->assertEquals("'id' BIGSERIAL PRIMARY KEY", $this->postgreSQLDatabaseConnection->sanitiseAutoIncrementString("'id' INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT"));

        $this->assertEquals("CREATE TABLE test_all_types (id BIGSERIAL PRIMARY KEY, name VARCHAR(500))",
            $this->postgreSQLDatabaseConnection->sanitiseAutoIncrementString("CREATE TABLE test_all_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(500))"));

    }

}