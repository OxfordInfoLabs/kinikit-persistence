<?php

namespace Kinikit\Persistence\Database\Vendors\SQLite3;

use Kinikit\Core\Testing\MockObject;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\DDL\ColumnAlterations;
use Kinikit\Persistence\Database\DDL\IndexAlterations;
use Kinikit\Persistence\Database\DDL\TableAlteration;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class SQLite3DDLManagerTest extends TestCase {

    /**
     * @var MockObject
     */
    private $connection;

    /**
     * @var SQLite3DDLManager
     */
    private SQLite3DDLManager $ddlManager;

    public function setUp(): void {
        $this->connection = MockObjectProvider::instance()->getMockInstance(SQLite3DatabaseConnection::class);
        $this->ddlManager = new SQLite3DDLManager();
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

        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

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

        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
        ]);

        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

        $this->assertEquals('CREATE TABLE test (
"id" INT NOT NULL,
"name" VARCHAR(255),
PRIMARY KEY ("id")
);', $sql);


        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true, true),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
        ]);

        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

        $this->assertEquals('CREATE TABLE test (
"id" INT NOT NULL PRIMARY KEY AUTOINCREMENT,
"name" VARCHAR(255)
);', $sql);

    }

    public function testPreviousColumnNamesAreIgnoredInUpdatableColumnMetaDataIfSuppliedForCreate() {

        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT),
            new UpdatableTableColumn("name", TableColumn::SQL_VARCHAR, 255, null, null, false, false, false, "other_name")
        ]);

        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

        $this->assertEquals('CREATE TABLE test (
"id" INT,
"name" VARCHAR(255)
);', $sql);

    }

    public function testColumnNamesCorrectlyEscapedInCreateDDL() {

        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            new TableColumn("name's", TableColumn::SQL_VARCHAR, 255, null),
        ]);


        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

        $this->assertEquals('CREATE TABLE test (
"id" INT NOT NULL,
"name\'s" VARCHAR(255),
PRIMARY KEY ("id")
);', $sql);

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

        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

        $this->assertEquals('CREATE TABLE test (
"id" INT,
"name" VARCHAR(255),
"score" FLOAT(5,5),
"description" BLOB NOT NULL,
"start_date" DATE,
"last_modified" DATETIME
);CREATE INDEX name_ind ON test (name);CREATE INDEX score_ind ON test (score,start_date);', $sql);

    }


    public function testIfSimpleTableAlterationCorrectlyConvertedToSQLiteSyntax() {

        $newMetaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT),
            new TableColumn("description", TableColumn::SQL_BLOB, null, null, null, null, false, true),
            new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME),
            new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000),
            new TableColumn("score", TableColumn::SQL_INT)
        ]);

        $tableAlteration = new TableAlteration("test", null,
            new ColumnAlterations([
                new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000)
            ], [
                new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
                new TableColumn("score", TableColumn::SQL_INT),
            ], [
                "name"
            ]),
            new IndexAlterations(null, [], [], []), $newMetaData);


        $sql = $this->ddlManager->generateModifyTableSQL($tableAlteration, $this->connection);

        $this->assertTrue($this->connection->methodWasCalled("executeScript", ['CREATE TABLE test (
"id" INT,
"description" BLOB NOT NULL,
"start_date" DATETIME,
"last_modified" DATETIME,
"notes" VARCHAR(2000),
"score" INT
);']));
        $this->assertTrue($this->connection->methodWasCalled("execute", ['INSERT INTO  (id,description,start_date,last_modified,notes,score) SELECT id,description,start_date,last_modified,notes,score FROM __test;']));
        $this->assertTrue($this->connection->methodWasCalled("execute", ['DROP TABLE __test;']));

        $this->assertEquals("DROP TABLE __test", $sql);

    }


    public function testIfTableAlterationWithColumnRenamingConvertedToSQLiteSyntaxCorrectly() {

        $newMetaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT),
            new UpdatableTableColumn("new_description", TableColumn::SQL_BLOB, null, null, null, null, false, true, "description"),
            new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME),
            new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000),
            new UpdatableTableColumn("updated_score", TableColumn::SQL_INT, null, null, null, false, false, false, "score")
        ]);

        $tableAlteration = new TableAlteration("test", null,
            new ColumnAlterations([
                new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000)
            ], [
                new UpdatableTableColumn("new_description", TableColumn::SQL_BLOB, null, null, null, null, false, true, "description"),
                new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
                new UpdatableTableColumn("updated_score", TableColumn::SQL_INT, null, null, null, false, false, false, "score")
            ], [
                "name"
            ]),
            new IndexAlterations(null, [], [], []), $newMetaData);

        $sql = $this->ddlManager->generateModifyTableSQL($tableAlteration, $this->connection);

        $this->assertTrue($this->connection->methodWasCalled("executeScript", ['CREATE TABLE test (
"id" INT,
"new_description" BLOB NOT NULL,
"start_date" DATETIME,
"last_modified" DATETIME,
"notes" VARCHAR(2000),
"updated_score" INT
);']));
        $this->assertTrue($this->connection->methodWasCalled("execute", ['INSERT INTO  (id,new_description,start_date,last_modified,notes,updated_score) SELECT id,description,start_date,last_modified,notes,score FROM __test;']));
        $this->assertTrue($this->connection->methodWasCalled("execute", ['DROP TABLE __test;']));

        $this->assertEquals("DROP TABLE __test", $sql);

    }


    public function testIfTableAlterationWithPrimaryKeyChangesIsConvertedToSQLiteSyntaxCorrectly() {

        $newMetaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            new UpdatableTableColumn("new_description", TableColumn::SQL_BLOB, null, null, null, true, false, true, "description"),
            new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
            new TableColumn("last_modified", TableColumn::SQL_DATE_TIME),
            new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000),
            new UpdatableTableColumn("updated_score", TableColumn::SQL_INT, null, null, null, false, false, false, "score")
        ]);

        $tableAlteration = new TableAlteration("test", null,
            new ColumnAlterations([
                new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000)
            ], [
                new UpdatableTableColumn("new_description", TableColumn::SQL_BLOB, null, null, null, true, false, true, "description"),
                new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
                new UpdatableTableColumn("updated_score", TableColumn::SQL_INT, null, null, null, false, false, false, "score")
            ], [
                "name"
            ]),
            new IndexAlterations(["new_description"], [], [], []), $newMetaData);

        $sql = $this->ddlManager->generateModifyTableSQL($tableAlteration, $this->connection);

        $this->assertTrue($this->connection->methodWasCalled("executeScript", ['CREATE TABLE test (
"id" INT NOT NULL,
"new_description" BLOB NOT NULL,
"start_date" DATETIME,
"last_modified" DATETIME,
"notes" VARCHAR(2000),
"updated_score" INT,
PRIMARY KEY ("id","new_description")
);']));
        $this->assertTrue($this->connection->methodWasCalled("execute", ['INSERT INTO  (id,new_description,start_date,last_modified,notes,updated_score) SELECT id,description,start_date,last_modified,notes,score FROM __test;']));
        $this->assertTrue($this->connection->methodWasCalled("execute", ['DROP TABLE __test;']));

    }


    public function testIfTableAlterationWithIndexAlterationsIsConvertedToSQLiteSyntaxCorrectly() {

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

        $tableAlteration = new TableAlteration("test", null,
            new ColumnAlterations([
                new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000)
            ], [], [
                "description"
            ]),
            new IndexAlterations(null, [
                new TableIndex("new_ind", ["name"])
            ], [
                new TableIndex("score_ind", ["score", "start_date", "description"]),
                new TableIndex("date_ind", ["last_modified", "start_date"])
            ], [
                new TableIndex("name_ind", ["name"]),
            ]), $newMetaData);

        $sql = $this->ddlManager->generateModifyTableSQL($tableAlteration);

        $this->assertStringContainsString('ALTER TABLE test ADD COLUMN "notes" VARCHAR(2000);', $sql);
        $this->assertStringContainsString('ALTER TABLE test DROP COLUMN description;', $sql);
        $this->assertStringContainsString('CREATE INDEX new_ind ON test (name);', $sql);
        $this->assertStringContainsString('DROP INDEX score_ind;', $sql);
        $this->assertStringContainsString('CREATE INDEX score_ind ON test (score,start_date,description);', $sql);
        $this->assertStringContainsString('DROP INDEX date_ind;', $sql);
        $this->assertStringContainsString('CREATE INDEX date_ind ON test (last_modified,start_date);', $sql);
        $this->assertStringContainsString('DROP INDEX name_ind;', $sql);

    }


    public function testCanCreateTableDropSQL() {

        $sql = $this->ddlManager->generateTableDropSQL("test");
        $this->assertStringContainsString("DROP TABLE test", $sql);

    }
}