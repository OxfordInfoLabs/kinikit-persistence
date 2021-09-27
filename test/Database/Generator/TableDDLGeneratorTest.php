<?php

namespace Kinikit\Persistence\Database\Generator;

use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3DatabaseConnection;
use mysql_xdevapi\Table;

/**
 * Test cases for the Table DDL generator
 *
 *
 * Class TableDDLGeneratorTest
 */
class TableDDLGeneratorTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var TableDDLGenerator
     */
    private $generator;


    public function setUp(): void {
        $this->generator = new TableDDLGenerator();
    }


    public function testCanGenerateCreateStatementFromSimpleMetaDataForStandardSQLTypes() {

        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
            new TableColumn("score", TableColumn::SQL_FLOAT, 5, 5),
            new TableColumn("description", TableColumn::SQL_BLOB, null, null, null, null, false, true),
            new TableColumn("start_date", TableColumn::SQL_DATE),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME)
        ]);

        $databaseConnection = new SQLite3DatabaseConnection();

        $sql = $this->generator->generateTableCreateSQL($metaData, $databaseConnection);

        $this->assertEquals("CREATE TABLE test (
id INT,
name VARCHAR(255),
score FLOAT(5,5),
description BLOB NOT NULL,
start_date DATE,
last_modified DATETIME
);", $sql);

    }


    public function testCanGenerateCreateStatementFromMetaDataWithPrimaryKeysIncludingAutoIncrement() {

        $databaseConnection = new SQLite3DatabaseConnection();


        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
        ]);


        $sql = $this->generator->generateTableCreateSQL($metaData, $databaseConnection);

        $this->assertEquals("CREATE TABLE test (
id INT,
name VARCHAR(255),
PRIMARY KEY (id)
);", $sql);


        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true, true),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
        ]);

        $sql = $this->generator->generateTableCreateSQL($metaData, $databaseConnection);

        $this->assertEquals("CREATE TABLE test (
id INT PRIMARY KEY AUTOINCREMENT,
name VARCHAR(255)
);", $sql);


    }


    public function testColumnNamesCorrectlyEscapedInCreateDDL() {
        $databaseConnection = MockObjectProvider::instance()->getMockInstance(DatabaseConnection::class);
        $databaseConnection->returnValue("escapeColumn", "id", ["id"]);
        $databaseConnection->returnValue("escapeColumn", "name''s", ["name's"]);


        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            new TableColumn("name's", TableColumn::SQL_VARCHAR, 255, null),
        ]);


        $sql = $this->generator->generateTableCreateSQL($metaData, $databaseConnection);

        $this->assertEquals("CREATE TABLE test (
id INT,
name''s VARCHAR(255),
PRIMARY KEY (id)
);", $sql);

    }


    public function testCanGenerateModifyDDLFromPreviousAndNewMetaData() {

        $databaseConnection = new SQLite3DatabaseConnection();


        $previousMetaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
            new TableColumn("score", TableColumn::SQL_FLOAT, 5, 5),
            new TableColumn("description", TableColumn::SQL_BLOB, null, null, null, null, false, true),
            new TableColumn("start_date", TableColumn::SQL_DATE),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME)
        ]);

        $newMetaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT),
            new TableColumn("description", TableColumn::SQL_BLOB, null, null, null, null, false, true),
            new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME),
            new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000),
            new TableColumn("score", TableColumn::SQL_INT)
        ]);


        $sql = $this->generator->generateTableModifySQL($previousMetaData, $newMetaData, $databaseConnection);

        $this->assertStringContainsString("ALTER TABLE test DROP COLUMN name;", $sql);
        $this->assertStringContainsString("ALTER TABLE test MODIFY COLUMN score INT;", $sql);
        $this->assertStringContainsString("ALTER TABLE test MODIFY COLUMN start_date DATE", $sql);
        $this->assertStringContainsString("ALTER TABLE test ADD COLUMN notes VARCHAR(2000)", $sql);

    }


}