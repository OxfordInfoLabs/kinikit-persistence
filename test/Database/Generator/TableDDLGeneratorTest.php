<?php

namespace Kinikit\Persistence\Database\Generator;

use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;
use Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3DatabaseConnection;

include_once "autoloader.php";

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

        $this->assertEquals('CREATE TABLE test (
"id" INT,
"name" VARCHAR(255),
"score" FLOAT(5,5),
"description" BLOB NOT NULL,
"start_date" DATE,
"last_modified" DATETIME
);', $sql);

    }


    public function testCanGenerateCreateStatementFromMetaDataWithPrimaryKeysIncludingAutoIncrement() {

        $databaseConnection = new SQLite3DatabaseConnection();


        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
        ]);


        $sql = $this->generator->generateTableCreateSQL($metaData, $databaseConnection);

        $this->assertEquals('CREATE TABLE test (
"id" INT NOT NULL,
"name" VARCHAR(255),
PRIMARY KEY ("id")
);', $sql);


        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true, true),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
        ]);

        $sql = $this->generator->generateTableCreateSQL($metaData, $databaseConnection);

        $this->assertEquals('CREATE TABLE test (
"id" INT NOT NULL PRIMARY KEY AUTOINCREMENT,
"name" VARCHAR(255)
);', $sql);


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
id INT NOT NULL,
name''s VARCHAR(255),
PRIMARY KEY (id)
);", $sql);

    }


    public function testCreateIndexStatementsIncludedIfSuppliedAsMetaData() {

        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
            new TableColumn("score", TableColumn::SQL_FLOAT, 5, 5),
            new TableColumn("description", TableColumn::SQL_BLOB, null, null, null, null, false, true),
            new TableColumn("start_date", TableColumn::SQL_DATE),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME)
        ], [
            new TableIndex("name_ind", ["name"]),
            new TableIndex("score_ind", ["score", "start_date"])
        ]);

        $databaseConnection = new SQLite3DatabaseConnection();

        $sql = $this->generator->generateTableCreateSQL($metaData, $databaseConnection);

        $this->assertEquals('CREATE TABLE test (
"id" INT,
"name" VARCHAR(255),
"score" FLOAT(5,5),
"description" BLOB NOT NULL,
"start_date" DATE,
"last_modified" DATETIME
);CREATE INDEX name_ind ON test (name);CREATE INDEX score_ind ON test (score,start_date);', $sql);

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

        $this->assertStringStartsWith("ALTER TABLE test", $sql);
        $this->assertStringContainsString("DROP COLUMN \"name\"", $sql);
        $this->assertStringContainsString('MODIFY COLUMN "score" INT', $sql);
        $this->assertStringContainsString('MODIFY COLUMN "start_date" DATE', $sql);
        $this->assertStringContainsString('ADD COLUMN "notes" VARCHAR(2000)', $sql);

    }


    public function testIfUpdatableTableColumnSuppliedWithPreviousNameFieldColumnIsRenamed() {

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
            new UpdatableTableColumn("new_description", TableColumn::SQL_BLOB, null, null, null, null, false, true, "description"),
            new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME),
            new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000),
            new UpdatableTableColumn("updated_score", TableColumn::SQL_INT, null, null, null, false, false, false, "score")
        ]);


        $sql = $this->generator->generateTableModifySQL($previousMetaData, $newMetaData, $databaseConnection);

        $this->assertStringContainsString("ALTER TABLE test", $sql);
        $this->assertStringContainsString("DROP COLUMN \"name\"", $sql);
        $this->assertStringContainsString('CHANGE COLUMN "description" "new_description" BLOB NOT NULL', $sql);
        $this->assertStringContainsString('CHANGE COLUMN "score" "updated_score" INT', $sql);
        $this->assertStringContainsString('MODIFY COLUMN "start_date" DATE', $sql);
        $this->assertStringContainsString('ADD COLUMN "notes" VARCHAR(2000)', $sql);
    }


    public function testIfPrimaryKeyHasChangedItIsRegeneratedAsPartOfModifySQL() {

        $databaseConnection = new SQLite3DatabaseConnection();


        $previousMetaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
            new TableColumn("score", TableColumn::SQL_FLOAT, 5, 5),
            new TableColumn("description", TableColumn::SQL_BLOB, null, null, null, null, false, true),
            new TableColumn("start_date", TableColumn::SQL_DATE),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME)
        ]);

        $newMetaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            new UpdatableTableColumn("new_description", TableColumn::SQL_BLOB, null, null, null, true, false, true, "description"),
            new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME),
            new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000),
            new UpdatableTableColumn("updated_score", TableColumn::SQL_INT, null, null, null, false, false, false, "score")
        ]);


        $sql = $this->generator->generateTableModifySQL($previousMetaData, $newMetaData, $databaseConnection);

        $this->assertStringContainsString("ALTER TABLE test", $sql);
        $this->assertStringContainsString("DROP PRIMARY KEY", $sql);
        $this->assertStringContainsString("ADD PRIMARY KEY (\"id\", \"new_description\")", $sql);

    }


    public function testModifySQLIncludesDropAndCreateStatementsForModifiedNewAndRemovedIndexes() {

        $previousMetaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
            new TableColumn("score", TableColumn::SQL_FLOAT, 5, 5),
            new TableColumn("description", TableColumn::SQL_BLOB, null, null, null, null, false, true),
            new TableColumn("start_date", TableColumn::SQL_DATE),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME)
        ], [
            new TableIndex("name_ind", ["name"]),
            new TableIndex("score_ind", ["score", "start_date"]),
            new TableIndex("date_ind", ["start_date", "last_modified"]),
            new TableIndex("description_ind", ["description"])
        ]);

        $newMetaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
            new TableColumn("score", TableColumn::SQL_FLOAT, 5, 5),
            new TableColumn("description", TableColumn::SQL_BLOB, null, null, null, null, false, true),
            new TableColumn("start_date", TableColumn::SQL_DATE),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME)
        ], [
            new TableIndex("new_ind", ["name"]),
            new TableIndex("score_ind", ["score", "start_date", "description"]),
            new TableIndex("date_ind", ["last_modified", "start_date"]),
            new TableIndex("description_ind", ["description"])
        ]);

        $databaseConnection = new SQLite3DatabaseConnection();

        $sql = $this->generator->generateTableModifySQL($previousMetaData, $newMetaData, $databaseConnection);
        $this->assertEquals("CREATE INDEX new_ind ON test (name);DROP INDEX score_ind ON test;CREATE INDEX score_ind ON test (score,start_date,description);DROP INDEX date_ind ON test;CREATE INDEX date_ind ON test (last_modified,start_date);DROP INDEX name_ind ON test;", $sql);


    }


    public function testCanCreateTableDropSQL() {

        $sql = $this->generator->generateTableDropSQL("test");
        $this->assertStringContainsString("DROP TABLE test", $sql);

    }

}