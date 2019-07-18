<?php

namespace Kinikit\Persistence\Database\Connection\ODBC;

use Kinikit\Persistence\Database\Connection\BlobWrapper;
use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\Database\Connection\PreparedStatement;
use Kinikit\Persistence\Database\Connection\TableColumn;

include_once "autoloader.php";


/**
 * Test cases for the ODBC Database Connection.
 *
 * @group dev
 */
class ODBCDatabaseConnectionTest extends \PHPUnit\Framework\TestCase {

    public function setUp():void {
        DefaultDB::getConnection()->query("DROP TABLE IF EXISTS test_parent") or die ("Couldn't drop table");
        DefaultDB::getConnection()->query("CREATE TABLE test_parent(id INTEGER AUTO_INCREMENT, name VARCHAR(20), PRIMARY KEY (id))") or die ("Couldn't create table");

    }

    public function testUponConstructionAValidConnectionIsEstablishedWithTheSuppliedODBCSource() {

        $odbc = new ODBCDatabaseConnection ("ooatest", "ooatest", "ooatest");
        $this->assertTrue($odbc->getUnderlyingConnection() > 0);

    }

    public function testCanExecuteValidUpdateQueryUsingODBCConnection() {

        // Make an odbc call and check data got in
        $odbc = new ODBCDatabaseConnection ("ooatest", "ooatest", "ooatest");
        $odbc->query("INSERT INTO test_parent (name) VALUES ('sugar dumpling')");

        $results = DefaultDatabaseConnection::instance()->queryWithResults("SELECT * from test_parent WHERE name = 'sugar dumpling'");
        $row = $results->nextRow();
        $this->assertEquals('sugar dumpling', $row ["name"]);

    }

    public function testCanGetQueryResultsIfQueryWithResultsExecuted() {

        $odbc = new ODBCDatabaseConnection ("ooatest", "ooatest", "ooatest");
        $result = $odbc->queryWithResults("SELECT * from group_role_based_user");

        $this->assertIsA($result, "ODBCResultSet");

        $row1 = $result->nextRow();
        $this->assertEquals("Administrator", $row1 ["full_name"]);
        $this->assertEquals(1, $row1 ["id"]);

    }

    public function testApostrophesAreEscapedByCallingEscapeString() {

        $odbc = new ODBCDatabaseConnection ("ooatest", "ooatest", "ooatest");
        $this->assertEquals("marko is intact", $odbc->escapeString("marko is intact"));
        $this->assertEquals("''marko'' gets ''changed''", $odbc->escapeString("'marko' gets 'changed'"));

    }

    public function testCanCloseODBCConnection() {

        //$odbc = new ODBCDatabaseConnection ( "ooatest", "ooatest", "ooatest" );
        //$odbc->close ();


    }

    public function testCanGetAutoIncrementIdIfApplicable() {

        // Make an odbc call and check data got in
        $odbc = new ODBCDatabaseConnection ("ooatest", "ooatest", "ooatest");
        $odbc->query("INSERT INTO test_parent (name) VALUES ('sugar dumpling')");

        $this->assertNotNull($odbc->getLastAutoIncrementId());

    }

    public function testCanGetTableMetaDataFromConnectionObject() {

        $odbcConnection = new ODBCDatabaseConnection ("ooatest", "ooatest", "ooatest");

        $metaData = $odbcConnection->getTableMetaData("test_child");
        $this->assertEquals("test_child", $metaData->getTableName());

        // Get the columns
        $columns = $metaData->getColumns();

        // Check we have 3 and enumerate
        $this->assertEquals(3, sizeof($columns));
        $this->assertEquals(new TableColumn ("id", SQL_INTEGER, 10), $columns ["id"]);
        $this->assertEquals(new TableColumn ("note", SQL_VARCHAR, 20), $columns ["note"]);
        $this->assertEquals(new TableColumn ("parent_id", SQL_INTEGER, 10), $columns ["parent_id"]);

        // Try another
        $metaData = $odbcConnection->getTableMetaData("test_child_multi_key");
        $this->assertEquals("test_child_multi_key", $metaData->getTableName());

        // Get the columns
        $columns = $metaData->getColumns();

        // Check we have 3 and enumerate
        $this->assertEquals(5, sizeof($columns));
        $this->assertEquals(new TableColumn ("id", SQL_INTEGER, 10), $columns ["id"]);
        $this->assertEquals(new TableColumn ("description", SQL_VARCHAR, 20), $columns ["description"]);
        $this->assertEquals(new TableColumn ("parent_field1", SQL_INTEGER, 10), $columns ["parent_field1"]);
        $this->assertEquals(new TableColumn ("parent_field2", SQL_VARCHAR, 10), $columns ["parent_field2"]);
        $this->assertEquals(new TableColumn ("parent_field3", SQL_INTEGER, 10), $columns ["parent_field3"]);

    }

    public function testCanExecuteAPreparedStatement() {

        $database = new ODBCDatabaseConnection ("ooatest", "ooatest", "ooatest");

        $statement = new PreparedStatement ("INSERT INTO test_parent (name) VALUES (?)");
        $statement->addBindParameter(TableColumn::SQL_VARCHAR, "Testing Testing 1,2");

        $database->executePreparedStatement($statement);

        $results = $database->queryWithResults("SELECT * FROM test_parent WHERE id = " . $database->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals("Testing Testing 1,2", $row ["name"]);
        $results->close();
    }

    public function testPreparedStatementsWithBlobObjectsAreHandledCorrectly() {

        // Get the mysql connection object
        $database = new ODBCDatabaseConnection ("ooatest", "ooatest", "ooatest");

        $database->query("DROP TABLE IF EXISTS test_with_blob") or die ("Couldn't drop table");
        $database->query("CREATE TABLE test_with_blob (id INTEGER AUTO_INCREMENT, blob_data LONGBLOB, PRIMARY KEY(id))") or die ("Couldn't create table");

        $preparedStatement = new PreparedStatement ("INSERT INTO test_with_blob (blob_data) VALUES (?)");
        $preparedStatement->addBindParameter(TableColumn::SQL_BLOB, new BlobWrapper ("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE"));

        // Execute an explicit one.
        $database->executePreparedStatement($preparedStatement);

        // Check it made it in
        $results = $database->queryWithResults("SELECT * from test_with_blob WHERE id = " . $database->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals("SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE", $row ["blob_data"]);
        $results->close();

        // Now do one via filename
        $preparedStatement = new PreparedStatement ("INSERT INTO test_with_blob (blob_data) VALUES (?)");
        $preparedStatement->addBindParameter(TableColumn::SQL_BLOB, new BlobWrapper (null, "persistence/database/connection/testlargeobject.pdf"));

        // Execute a filename based one
        $database->executePreparedStatement($preparedStatement);

        // Now check it made it in.
        $results = $database->queryWithResults("SELECT * from test_with_blob WHERE id = " . $database->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals(file_get_contents("persistence/database/connection/testlargeobject.pdf"), $row ["blob_data"]);

        $results->close();

    }

}

?>
