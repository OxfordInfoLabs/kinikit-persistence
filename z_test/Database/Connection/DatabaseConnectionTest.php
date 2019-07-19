<?php

namespace Kinikit\Persistence\Database\Connection;

use Kinikit\Core\Configuration;
use Kinikit\Persistence\Database\Connection\MySQL\MySQLDatabaseConnection;
use Kinikit\Persistence\Database\Connection\SQLite3\SQLite3DatabaseConnection;
use Kinikit\Persistence\UPF\Engines\ORM\ORMPersistenceEngine;

include_once "autoloader.php";

/**
 * Test cases for the base connection.
 */
class DatabaseConnectionTest extends \PHPUnit\Framework\TestCase {

    /**
     * Connection
     *
     * @var DatabaseConnection
     */
    private $connection;

    public function setUp():void {

        $this->connection = DefaultDB::instance();
        $this->connection->query("DROP TABLE IF EXISTS test_data",);
        $this->connection->query("CREATE TABLE test_data (id INTEGER PRIMARY KEY AUTOINCREMENT, data VARCHAR(1000))",);
    }

    public function testCallingRollbackRollsBackTheWholeRunningTransaction() {

        $this->connection->query("BEGIN",);
        $this->connection->query("INSERT INTO test_data VALUES (100, 'monkey man')",);
        $this->connection->query("INSERT INTO test_data VALUES (200, 'killer gorilla')",);

        $results = $this->connection->queryWithResults("SELECT * FROM test_data",);
        $row = $results->nextRow();
        $this->assertEquals(array(100, "monkey man"), array($row ["id"], $row ["data"]));
        $row = $results->nextRow();
        $this->assertEquals(array(200, "killer gorilla"), array($row ["id"], $row ["data"]));
        $results->close();

        // Now do a rollback to check we are in transaction
        $this->connection->rollback();

        // Now prove that we indeed rolled back the whole transaction
        $results = $this->connection->queryWithResults("SELECT * FROM test_data",);
        $this->assertNull($results->nextRow());

    }

    public function testCallingCommitWillCommitTheRunningTransaction() {
        $this->connection->query("BEGIN",);
        $this->connection->query("INSERT INTO test_data VALUES (100, 'monkey man')",);
        $this->connection->commit();

        // Now rollback to confirm that commit happened.
        $this->connection->rollback();

        $results = $this->connection->queryWithResults("SELECT * FROM test_data",);
        $row = $results->nextRow();
        $this->assertEquals(array(100, "monkey man"), array($row ["id"], $row ["data"]));

    }

    public function testUponCallingBeginTransactionOutsideOfTransactionWithAnyArgumentsTransactionIsStartedAndNothingReturned() {

        // TRY DEFAULT ARGUMENTS


        // Insert some test data
        $this->connection->beginTransaction();
        $this->connection->query("INSERT INTO test_data VALUES (100, 'monkey man')",);
        $results = $this->connection->queryWithResults("SELECT * FROM test_data",);
        $row = $results->nextRow();
        $this->assertEquals(array(100, "monkey man"), array($row ["id"], $row ["data"]));
        $results->close();

        // Now do a rollback to check we are in transaction
        $this->connection->rollback();

        // Now prove that we indeed rolled back
        $results = $this->connection->queryWithResults("SELECT * FROM test_data",);
        $this->assertNull($results->nextRow());

    }

    public function testIfBeginTransactionCalledWhilstInTransactionWithDefaultFlagSetTransactionSimplyContinues() {

        $this->connection->beginTransaction();
        $this->connection->query("INSERT INTO test_data VALUES (100, 'monkey man')",);
        $this->connection->beginTransaction();

        // Check previous transaction not yet committed
        $newConn = new SQLite3DatabaseConnection(Configuration::readParameter("db.filename"));
        $results = $newConn->queryWithResults("SELECT * FROM test_data",);
        $this->assertNull($results->nextRow());
        $results->close();

        $this->connection->query("INSERT INTO test_data VALUES (200, 'killer gorilla')",);
        $this->connection->commit();

        // Check whole transaction now committed.
        $results = $this->connection->queryWithResults("SELECT * FROM test_data",);
        $row = $results->nextRow();
        $this->assertEquals(array(100, "monkey man"), array($row ["id"], $row ["data"]));
        $row = $results->nextRow();
        $this->assertEquals(array(200, "killer gorilla"), array($row ["id"], $row ["data"]));
        $results->close();
    }

    public function testCanGetResultOfSingleValuedQuery() {

        $this->connection->query("INSERT INTO test_data VALUES (100, 'monkey man')",);
        $this->connection->query("INSERT INTO test_data VALUES (200, 'plant man')",);

        $this->assertEquals(2, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));

    }

    public function testCanBulkInsertANumberOfRowsAtOnce() {

        $columns = array("id", "data");
        $values = array(array(1, "Mark"), array(2, "Luke"), array(3, "Tim"), array(4, "John"));

        $this->connection->query("DELETE FROM test_data",);

        $this->connection->bulkInsert("test_data", $columns, $values);

        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Mark", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 1"));
        $this->assertEquals("Luke", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 2"));
        $this->assertEquals("Tim", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 3"));
        $this->assertEquals("John", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 4"));

        // Now test a large amount of data (500 rows)
        $values = array();
        for ($i = 0; $i < 500; $i++) {
            $values [] = array($i + 5, "Test Data " . $i);
        }

        $this->connection->bulkInsert("test_data", $columns, $values);

        $this->assertEquals(504, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));

    }

    public function testCanBulkDeleteANumberOfRowsAtOnceUsingSingleColumn() {

        $this->connection->query("DELETE FROM test_data",);

        // Firstly bulk insert a few rows
        $columns = array("id", "data");
        $values = array(array(1, "Mark"), array(2, "Luke"), array(3, "Tim"), array(4, "John"));
        $this->connection->bulkInsert("test_data", $columns, $values);

        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Mark", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 1"));
        $this->assertEquals("Luke", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 2"));
        $this->assertEquals("Tim", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 3"));
        $this->assertEquals("John", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 4"));

        // Now bulk delete some rows
        $deleteColumns = "id";
        $values = array(2, 4);
        $this->connection->bulkDelete("test_data", $deleteColumns, $values);

        $this->assertEquals(2, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Mark", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 1"));
        $this->assertEquals("Tim", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 3"));

        $values = array(3);
        $this->connection->bulkDelete("test_data", $deleteColumns, $values);

        $this->assertEquals(1, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Mark", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 1"));

    }

    public function testCanBulkDeleteANumberOfRowsAtOnceUsingCompoundColumnKey() {

        $this->connection->query("DELETE FROM test_data",);

        // Firstly bulk insert a few rows
        $columns = array("id", "data");
        $values = array(array(1, "Mark"), array(2, "Luke"), array(3, "Tim"), array(4, "John"));
        $this->connection->bulkInsert("test_data", $columns, $values);

        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Mark", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 1"));
        $this->assertEquals("Luke", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 2"));
        $this->assertEquals("Tim", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 3"));
        $this->assertEquals("John", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 4"));

        // Now bulk delete some rows
        $deleteColumns = array("id", "data");
        $values = array(array(2, "Luke"), array(4, "John"));
        $this->connection->bulkDelete("test_data", $deleteColumns, $values);

        $this->assertEquals(2, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Mark", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 1"));
        $this->assertEquals("Tim", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 3"));

        $values = array(array(3, "Tim"));
        $this->connection->bulkDelete("test_data", $deleteColumns, $values);

        $this->assertEquals(1, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Mark", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 1"));

    }


    public function testCanBulkReplaceANumberOfRowsUsingPrimaryKey() {
        $this->connection->query("DELETE FROM test_data",);


        $columns = array("id", "data");
        $primaryKeyIndexes = array(0);
        $values = array(array(1, "Mark"), array(2, "Luke"), array(3, "Tim"), array(4, "John"));

        $this->connection->bulkReplace("test_data", $columns, $primaryKeyIndexes, $values);

        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Mark", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 1"));
        $this->assertEquals("Luke", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 2"));
        $this->assertEquals("Tim", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 3"));
        $this->assertEquals("John", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 4"));


        $replacedValues = array(array(1, "Peter"), array(2, "Paul"), array(5, "Mary"));

        $this->connection->bulkReplace("test_data", $columns, $primaryKeyIndexes, $replacedValues);

        $this->assertEquals(5, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Peter", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 1"));
        $this->assertEquals("Paul", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 2"));
        $this->assertEquals("Tim", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 3"));
        $this->assertEquals("John", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 4"));
        $this->assertEquals("Mary", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 5"));

        $newValues = array(array(6, "Robin"), array(7, "Batman"));
        $this->connection->bulkReplace("test_data", $columns, $primaryKeyIndexes, $newValues);


        $this->assertEquals(7, $this->connection->queryForSingleValue("SELECT count(*) from test_data"));
        $this->assertEquals("Peter", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 1"));
        $this->assertEquals("Paul", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 2"));
        $this->assertEquals("Tim", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 3"));
        $this->assertEquals("John", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 4"));
        $this->assertEquals("Mary", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 5"));
        $this->assertEquals("Robin", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 6"));
        $this->assertEquals("Batman", $this->connection->queryForSingleValue("SELECT data from test_data WHERE id = 7"));


    }


    public function testCanExecuteScriptOfMultipleStatements() {

        $this->connection->query("DROP TABLE IF EXISTS nathan",);

        $this->connection->executeScript(file_get_contents("Database/Connection/testscript.sql"));

        $this->assertEquals(3, $this->connection->queryForSingleValue("SELECT count(*) from nathan"));

        $this->assertEquals(25, $this->connection->queryForSingleValue("SELECT age from nathan WHERE name = 'Nathan'"));
        $this->assertEquals(40, $this->connection->queryForSingleValue("SELECT age from nathan WHERE name = 'Mark'"));
        $this->assertEquals(58, $this->connection->queryForSingleValue("SELECT age from nathan WHERE name = 'Philip'"));


    }


    public function testCanCreateGenericTableUsingTableMetaData() {

        $this->connection->query("DROP TABLE IF EXISTS test_create_table",);

        $columns = array();
        $columns[] = new TableColumn("id", TableColumn::SQL_INT, null, null, true, true, true);
        $columns[] = new TableColumn("name", "VARCHAR", 1000);
        $columns[] = new TableColumn("last_modified", "DATETIME", null);
        $tableMetaData = new TableMetaData("test_create_table", $columns);

        $this->connection->createTable($tableMetaData);

        $reMetaData = $this->connection->getTableMetaData("test_create_table");
        $this->assertEquals("test_create_table", $reMetaData->getTableName());


        // Now try one with compound primary key
        $this->connection->query("DROP TABLE IF EXISTS test_create_table",);

        $columns = array();
        $columns[] = new TableColumn("id", TableColumn::SQL_INT, null, null, true, false, true);
        $columns[] = new TableColumn("name", "VARCHAR", 1000, null, true, false, true);
        $columns[] = new TableColumn("last_modified", "DATETIME", null);
        $tableMetaData = new TableMetaData("test_create_table", $columns);

        $this->connection->createTable($tableMetaData);

        $reMetaData = $this->connection->getTableMetaData("test_create_table");
        $this->assertEquals("test_create_table", $reMetaData->getTableName());


    }


}

?>
