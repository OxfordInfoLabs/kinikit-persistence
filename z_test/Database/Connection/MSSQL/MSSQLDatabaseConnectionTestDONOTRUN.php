<?php

namespace Kinikit\Persistence\Database\Connection\MSSQL;

use Kinikit\Core\Configuration;
use Kinikit\Persistence\Database\Exception\SQLException;

include_once "autoloader.php";


/**
 * Test cases for the MSSQL Database connection object
 *
 * @group dev
 */
class MSSQLDatabaseConnectionTest extends \PHPUnit\Framework\TestCase {

    private $connection;

    public function setUp():void {

        parent::setUp();

        $this->connection = new MSSQLDatabaseConnection (Configuration::readParameter("sqlserver.servername"), Configuration::readParameter("sqlserver.username"), Configuration::readParameter("sqlserver.password"), Configuration::readParameter("sqlserver.database"));


        try {
            $this->connection->query("DROP TABLE test_parent");
        } catch (SQLException $e) {
        }

        $this->connection->query("CREATE TABLE test_parent(id INTEGER IDENTITY(1,1) PRIMARY KEY, name VARCHAR(20))") or die ("Couldn't create table");

    }

    public function testCanExecuteValidUpdateQueryUsingMSSQLConnection() {

        // Make an MSSQL call and check data got in
        $this->connection->query("INSERT INTO test_parent (name) VALUES ('sugar dumpling')");

        $results = $this->connection->queryWithResults("SELECT * from test_parent WHERE name = 'sugar dumpling'");
        $row = $results->nextRow();
        $this->assertEquals('sugar dumpling', $row ["name"]);

    }

    public function testCanGetQueryResultsFromEmptyTable() {

        $result = $this->connection->queryWithResults("SELECT * from test_parent");
        $this->assertNotNull($result);

    }

    public function testApostrophesAreEscapedByCallingEscapeString() {

        $this->assertEquals("marko is intact", $this->connection->escapeString("marko is intact"));
        $this->assertEquals("''marko'' gets ''changed''", $this->connection->escapeString("'marko' gets 'changed'"));

    }

    public function testCanCloseMSSQLConnection() {

        $this->connection->close();

    }

    public function testCanGetAutoIncrementIdIfApplicable() {

        // Make an MSSQL call and check data got in
        $this->connection->query("INSERT INTO test_parent (name) VALUES ('sugar dumpling')");
        $this->assertNotNull($this->connection->getLastAutoIncrementId());
        $this->assertEquals(1, $this->connection->getLastAutoIncrementId());


        // Make a few more and confirm
        $this->connection->query("INSERT INTO test_parent (name) VALUES ('peanut butter')");
        $this->assertNotNull($this->connection->getLastAutoIncrementId());
        $this->assertEquals(2, $this->connection->getLastAutoIncrementId());

        $this->connection->query("INSERT INTO test_parent (name) VALUES ('jelly and ice cream')");
        $this->assertNotNull($this->connection->getLastAutoIncrementId());
        $this->assertEquals(3, $this->connection->getLastAutoIncrementId());



    }

    public function testCanGetTableMetaDataFromConnectionObject() {

        $metaData = $this->connection->getTableMetaData("test_parent");
        $this->assertEquals("test_parent", $metaData->getTableName());

        // Get the columns
        $columns = $metaData->getColumns();

        // Check we have 3 and enumerate
        $this->assertEquals(2, sizeof($columns));
        $this->assertEquals(new MSSQLTableColumn ("id", TableColumn::SQL_INT, 10), $columns ["id"]);
        $this->assertEquals(new MSSQLTableColumn ("name", TableColumn::SQL_VARCHAR, 20), $columns ["name"]);

    }

    //	public function testCanGetTableMetaDataFromConnectionObjectForAllTypesOfData() {
    //
    //		$this->fail ( " " );
    //
    //	}


    public function testCanExecuteAPreparedStatementWithASingleBindingParameterOfTypeVarchar() {

        $statement = new PreparedStatement ("INSERT INTO test_parent (name) VALUES (?)");
        $statement->addBindParameter(TableColumn::SQL_VARCHAR, "Testing Testing 1,2");

        $this->connection->executePreparedStatement($statement);
        $this->assertNotNull($this->connection->getLastAutoIncrementId());
        $this->assertEquals(1, $this->connection->getLastAutoIncrementId());
        $results = $this->connection->queryWithResults("SELECT * FROM test_parent WHERE id = " . $this->connection->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals("Testing Testing 1,2", $row ["name"]);
        $results->close();

    }

    public function testCanExecuteAPreparedStatementWithASingleBindingParameterOfTypeInt() {

        try {
            $this->connection->query("DROP TABLE test_parent");
        } catch (SQLException $e) {
        }

        $this->connection->query("CREATE TABLE test_parent(id INTEGER IDENTITY(1,1) PRIMARY KEY, name INTEGER)");

        $statement = new PreparedStatement ("INSERT INTO test_parent (name) VALUES (?)");
        $statement->addBindParameter(TableColumn::SQL_INT, 57);

        $this->connection->executePreparedStatement($statement);
        $this->assertNotNull($this->connection->getLastAutoIncrementId());
        $this->assertEquals(1, $this->connection->getLastAutoIncrementId());
        $results = $this->connection->queryWithResults("SELECT * FROM test_parent WHERE id = " . $this->connection->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals(57, $row ["name"]);
        $results->close();

    }

    public function testCanExecuteAPreparedStatementWithASingleBindingParameterOfTypeDate() {

        try {
            $this->connection->query("DROP TABLE test_parent");
        } catch (SQLException $e) {
        }

        $this->connection->query("CREATE TABLE test_parent(id INTEGER IDENTITY(1,1) PRIMARY KEY, name DATE)");

        $statement = new PreparedStatement ("INSERT INTO test_parent (name) VALUES (?)");
        $statement->addBindParameter(TableColumn::SQL_DATE, "2010-10-26");

        $this->connection->executePreparedStatement($statement);
        $this->assertNotNull($this->connection->getLastAutoIncrementId());
        $this->assertEquals(1, $this->connection->getLastAutoIncrementId());
        $results = $this->connection->queryWithResults("SELECT * FROM test_parent WHERE id = " . $this->connection->getLastAutoIncrementId());
        $row = $results->nextRow();
        $this->assertEquals("2010-10-26", $row ["name"]);
        $results->close();

    }

    public function testCanUseTransactionFunctionality() {

        //Check begin transaction and commit
        $this->connection->beginTransaction();
        $this->connection->query("INSERT INTO test_parent (name) VALUES ('sugar dumpling')");
        $this->connection->commit();

        $results = $this->connection->queryWithResults("SELECT * from test_parent WHERE name = 'sugar dumpling'");
        $row = $results->nextRow();
        $this->assertEquals('sugar dumpling', $row ["name"]);

        //Clear & create new
        $this->connection->query("DROP TABLE test_parent");
        $this->connection->query("CREATE TABLE test_parent(id INTEGER IDENTITY(1,1) PRIMARY KEY, name VARCHAR(255))");

        //Check begin and rollback
        $this->connection->beginTransaction();
        $this->connection->query("INSERT INTO test_parent (name) VALUES ('sugar dumpling')");
        $this->connection->rollback();

        $results = $this->connection->queryWithResults("SELECT * from test_parent WHERE name = 'sugar dumpling'");
        $row = $results->nextRow();
        $this->assertEquals(null, $row ["name"]);

        //Clear & create new
        $this->connection->query("DROP TABLE test_parent");
        $this->connection->query("CREATE TABLE test_parent(id INTEGER IDENTITY(1,1) PRIMARY KEY, name VARCHAR(255))");

        //Check nested transactions
        $this->connection->beginTransaction();
        $this->connection->query("INSERT INTO test_parent (name) VALUES ('sugar dumpling')");

        $this->connection->beginTransaction();
        $this->connection->query("INSERT INTO test_parent (name) VALUES ('salt dumps')");
        $this->connection->rollback();
        $this->connection->commit();

        $results = $this->connection->queryWithResults("SELECT * from test_parent WHERE name = 'sugar dumpling'");
        $row = $results->nextRow();
        $this->assertEquals('sugar dumpling', $row ["name"]);
        $results = $this->connection->queryWithResults("SELECT * from test_parent WHERE name = 'salt dumps'");
        $row = $results->nextRow();
        $this->assertEquals(null, $row ["name"]);

    }

    public function testCanBindNullCorrectly() {

        $this->connection = new MSSQLDatabaseConnection (Configuration::readParameter("sqlserver.servername"), Configuration::readParameter("sqlserver.username"), Configuration::readParameter("sqlserver.password"), Configuration::readParameter("sqlserver.database"));

        try {
            $this->connection->query("DROP TABLE test_parent");
        } catch (SQLException $e) {
        }

        $this->connection->query("CREATE TABLE test_parent(id INTEGER IDENTITY(1,1) PRIMARY KEY, name VARCHAR(20) NULL)") or die ("Couldn't create table");

        $statement = new PreparedStatement ("INSERT INTO test_parent (name) VALUES (?)");
        $statement->addBindParameter(TableColumn::SQL_UNKNOWN, NULL);

    }


    public function testQueryParserIsCorrectlyUsedForQueriesToMSSQL() {

        $this->connection->query("INSERT INTO test_parent VALUES ('bigboy')");
        $this->connection->query("INSERT INTO test_parent VALUES ('biggirl')");
        $this->connection->query("INSERT INTO test_parent VALUES ('biglad')");
        $this->connection->query("INSERT INTO test_parent VALUES ('biglass')");
        $this->connection->query("INSERT INTO test_parent VALUES ( 'bigchild')");
        $this->connection->query("INSERT INTO test_parent VALUES ( 'bigkid')");
        $this->connection->query("INSERT INTO test_parent VALUES ( 'bigbaby')");

        $results = $this->connection->queryWithResults("SELECT * FROM test_parent ORDER BY name LIMIT 3 OFFSET 2");
        $ids = array();
        while ($row = $results->nextRow()) {
            $ids[] = $row["id"];
        }

        $this->assertEquals(array(5, 2, 6), $ids);

    }


    /*	public function testPreparedStatementsWithBlobObjectsAreHandledCorrectly() {


        $this->connection->query ( "CREATE TABLE test_with_blob (id INTEGER IDENTITY(1,1) PRIMARY KEY, blob_data LONGBLOB)" );
        $preparedStatement = new PreparedStatement ( "INSERT INTO test_with_blob (blob_data) VALUES (?)" );
        $preparedStatement->addBindParameter ( TableColumn::SQL_BLOB, new BlobWrapper ( "SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE" ) );

        // Execute an explicit one.
        $this->connection->executePreparedStatement ( $preparedStatement );

        // Check it made it in
        $results = $this->connection->queryWithResults ( "SELECT * from test_with_blob WHERE id = " . $this->connection->getLastAutoIncrementId () );
        $row = $results->nextRow ();
        $this->assertEquals ( "SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE", $row ["blob_data"] );

        // Now do one via filename
        $preparedStatement = new PreparedStatement ( "INSERT INTO test_with_blob (blob_data) VALUES (?)" );
        $preparedStatement->addBindParameter ( TableColumn::SQL_BLOB, new BlobWrapper ( null, "persistence/database/connection/testlargeobject.pdf" ) );

        // Execute a filename based one
        $this->connection->executePreparedStatement ( $preparedStatement );

        // Now check it made it in.
        $results = $this->connection->queryWithResults ( "SELECT * from test_with_blob WHERE id = " . $this->connection->getLastAutoIncrementId () );
        $row = $results->nextRow ();
        $this->assertEquals ( file_get_contents ( "persistence/database/connection/testlargeobject.pdf" ), $row ["blob_data"] );

        $results->close ();

        }
        */
    /*	public function testSQLExceptionThrownCorrectlyIfBadPreparedStatementExecuted() {

        // Get the mysql connection object
        $mysqlConnection = DefaultDatabaseConnection::instance ();

        $preparedStatement = new PreparedStatement ( "INSERT INTO test_with_badtable (blob_data) VALUES (?)" );
        $preparedStatement->addBindParameter ( TableColumn::SQL_BLOB, new BlobWrapper ( "SOMETHING EXPLICIT AND LONG AND VERY MUCH WORTH ALL THE EFFORT INVOLVED IN SENDING IT AS APPROPRIATE" ) );

        // Execute and expect exception
        try {
        $mysqlConnection->executePreparedStatement ( $preparedStatement );
        $this->fail ( "Should have thrown here" );
        } catch ( SQLException $e ) {
        // Success
        }

        // Now try one with unbound value.
        $preparedStatement = new PreparedStatement ( "INSERT INTO test_with_blob (blob_data) VALUES (?)" );

        try {
        ob_start ();
        $mysqlConnection->executePreparedStatement ( $preparedStatement );
        ob_end_clean ();
        $this->fail ( "Should have thrown here" );
        } catch ( SQLException $e ) {
        // Success
        }

        }

        */

    /*

        public function testIfImpropperInformationIsProvidedForAConnectionAnExceptionIsThrown() {

            $MSSQLDatabseConnection = new MSSQLDatabaseConnection ( "localhost", "ooacoretest", "ooacoretest" );

            try {
                $connection = $MSSQLDatabseConnection->getUnderlyingConnection ();
                $this->fail ( "Should have thrown here" );
            } catch ( DatabaseConnectionException $e ) {
                // Success
            }

        } */

}

?>
