<?php

namespace Kinikit\Persistence\Database\Connection\MySQL;

use Kinikit\Core\Configuration;
use Kinikit\Persistence\Database\Connection\BlobWrapper;
use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\Database\Connection\PreparedStatement;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Connection\TableMetaData;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\Exception\WrongNumberOfPreparedStatementParametersException;

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


    public function setUp():void {
        $this->mysqlDatabaseConnection = new MySQLDatabaseConnection(Configuration::readParameter("mysql.host"),
            Configuration::readParameter("mysql.database"), Configuration::readParameter("mysql.username"), Configuration::readParameter("mysql.password"));

        $this->mysqlDatabaseConnection->executeScript("CREATE DATABASE IF NOT EXISTS kinikittest");

    }


    public function testCanGetTableMetaDataFromConnectionObject() {

        $this->mysqlDatabaseConnection->query("DROP TABLE IF EXISTS test_child",) or die ("Couldn't drop table");
        $this->mysqlDatabaseConnection->query("CREATE TABLE test_child(id INTEGER AUTO_INCREMENT, note VARCHAR(20), parent_id INTEGER, PRIMARY KEY (id))",) or die ("Couldn't create table");
        $this->mysqlDatabaseConnection->query("DROP TABLE IF EXISTS test_child_multi_key",) or die ("Couldn't drop table");
        $this->mysqlDatabaseConnection->query("CREATE TABLE test_child_multi_key (id INTEGER AUTO_INCREMENT, description VARCHAR(20), parent_field1 INTEGER, parent_field2 VARCHAR(10), parent_field3 INTEGER, PRIMARY KEY (id))",) or die ("Couldn't create table");

        $mysqlConnection = $this->mysqlDatabaseConnection;

        $metaData = $mysqlConnection->getTableMetaData("test_child");
        $this->assertEquals("test_child", $metaData->getTableName());

        // Get the columns
        $columns = $metaData->getColumns();

        // Check we have 3 and enumerate
        $this->assertEquals(3, sizeof($columns));
        $this->assertEquals(new MySQLTableColumn($mysqlConnection, "id", TableColumn::SQL_INT, 11), $columns ["id"]);
        $this->assertEquals(new MySQLTableColumn ($mysqlConnection, "note", TableColumn::SQL_VARCHAR, 20), $columns ["note"]);
        $this->assertEquals(new MySQLTableColumn ($mysqlConnection, "parent_id", TableColumn::SQL_INT, 11), $columns ["parent_id"]);

        // Try another
        $metaData = $mysqlConnection->getTableMetaData("test_child_multi_key");
        $this->assertEquals("test_child_multi_key", $metaData->getTableName());

        // Get the columns
        $columns = $metaData->getColumns();

        // Check we have 3 and enumerate
        $this->assertEquals(5, sizeof($columns));
        $this->assertEquals(new MySQLTableColumn ($mysqlConnection, "id", TableColumn::SQL_INT, 11), $columns ["id"]);
        $this->assertEquals(new MySQLTableColumn ($mysqlConnection, "description", TableColumn::SQL_VARCHAR, 20), $columns ["description"]);
        $this->assertEquals(new MySQLTableColumn ($mysqlConnection, "parent_field1", TableColumn::SQL_INT, 11), $columns ["parent_field1"]);
        $this->assertEquals(new MySQLTableColumn ($mysqlConnection, "parent_field2", TableColumn::SQL_VARCHAR, 10), $columns ["parent_field2"]);
        $this->assertEquals(new MySQLTableColumn ($mysqlConnection, "parent_field3", TableColumn::SQL_INT, 11), $columns ["parent_field3"]);

    }

    public function testCanExecuteAPreparedStatementInMysql() {

        // Get the mysql connection object
        $mysqlConnection = $this->mysqlDatabaseConnection;

        // Now create a prepared statement to execute
        $preparedStatement = new PreparedStatement ("INSERT INTO test_child (note, parent_id) VALUES (?,?)");
        $preparedStatement->addBindParameter(TableColumn::SQL_VARCHAR, "Interesting One for");
        $preparedStatement->addBindParameter(TableColumn::SQL_INT, 567);

        $mysqlConnection->createPreparedStatement($preparedStatement);

        // Check that the execution succeeded
        $results = $mysqlConnection->queryWithResults("SELECT * from test_child WHERE id = " . $mysqlConnection->getLastAutoIncrementId(),);
        $row = $results->nextRow();
        $this->assertEquals("Interesting One for", $row ["note"]);
        $this->assertEquals(567, $row ["parent_id"]);

    }

    public function testPreparedStatementsWithBlobObjectsAreHandledCorrectlyAndStreamedToMysqlUsingSendLargeData() {

        // Get the mysql connection object
        $mysqlConnection = $this->mysqlDatabaseConnection;

        $mysqlConnection->query("DROP TABLE IF EXISTS test_with_blob",) or die ("Couldn't drop table");
        $mysqlConnection->query("CREATE TABLE test_with_blob (id INTEGER AUTO_INCREMENT, blob_data LONGBLOB, PRIMARY KEY (id))",) or die ("Couldn't create table");

        $preparedStatement = new PreparedStatement ("INSERT INTO test_with_blob (blob_data) VALUES (?)");
        $preparedStatement->addBindParameter(TableColumn::SQL_BLOB, new BlobWrapper ("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE"));

        // Execute an explicit one.
        $mysqlConnection->createPreparedStatement($preparedStatement);

        // Check it made it in
        $results = $mysqlConnection->queryWithResults("SELECT * from test_with_blob WHERE id = " . $mysqlConnection->getLastAutoIncrementId(),);
        $row = $results->nextRow();
        $this->assertEquals("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE", $row ["blob_data"]);

        // Now do one via filename
        $preparedStatement = new PreparedStatement ("INSERT INTO test_with_blob (blob_data) VALUES (?)");
        $preparedStatement->addBindParameter(TableColumn::SQL_BLOB, new BlobWrapper (null, "Database/Connection/testlargeobject.pdf"));

        // Execute a filename based one
        $mysqlConnection->createPreparedStatement($preparedStatement);

        // Now check it made it in.
        $results = $mysqlConnection->queryWithResults("SELECT * from test_with_blob WHERE id = " . $mysqlConnection->getLastAutoIncrementId(),);
        $row = $results->nextRow();
        $this->assertEquals(file_get_contents("Database/Connection/testlargeobject.pdf"), $row ["blob_data"]);

    }

    public function testSQLExceptionThrownCorrectlyIfBadPreparedStatementExecuted() {

        // Get the mysql connection object
        $mysqlConnection = $this->mysqlDatabaseConnection;

        $preparedStatement = new PreparedStatement ("INSERT INTO test_with_badtable (blob_data) VALUES (?)");
        $preparedStatement->addBindParameter(TableColumn::SQL_BLOB, new BlobWrapper ("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE"));

        // Execute and expect exception
        try {
            $mysqlConnection->createPreparedStatement($preparedStatement);
            $this->fail("Should have thrown here");
        } catch (SQLException $e) {
            // Success
        }

        // Now try one with unbound value.
        $preparedStatement = new PreparedStatement ("INSERT INTO test_with_blob (blob_data) VALUES (?)");

        ob_start();

        try {
            $mysqlConnection->createPreparedStatement($preparedStatement);
            ob_end_clean();
            $this->fail("Should have thrown here");
        } catch (WrongNumberOfPreparedStatementParametersException $e) {
            // Success
            ob_end_clean();
        }

        $this->assertTrue(true);

    }

    public function testCreateTableImplementedCorrectlyForMySQL() {

        $this->mysqlDatabaseConnection->query("DROP TABLE IF EXISTS test_create_table",);

        $columns = array();
        $columns[] = new TableColumn("id", TableColumn::SQL_INT, null, null, true, true, true);
        $columns[] = new TableColumn("name", "VARCHAR", 1000);
        $columns[] = new TableColumn("last_modified", "DATETIME", null);
        $tableMetaData = new TableMetaData("test_create_table", $columns);

        $this->mysqlDatabaseConnection->createTable($tableMetaData);

        $reMetaData = $this->mysqlDatabaseConnection->getTableMetaData("test_create_table");
        $this->assertEquals("test_create_table", $reMetaData->getTableName());
    }


}

?>
