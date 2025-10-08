<?php

namespace Kinikit\Persistence\Database\Vendors\PostgreSQL;

use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\DDL\ColumnAlterations;
use Kinikit\Persistence\Database\DDL\IndexAlterations;
use Kinikit\Persistence\Database\DDL\TableAlteration;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;
use Kinikit\Persistence\Database\ResultSet\ResultSet;
use mysql_xdevapi\Table;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class PostgreSQLDDLManagerTest extends TestCase {

    /**
     * @var PostgreSQLDDLManager
     */
    private PostgreSQLDDLManager $ddlManager;

    public function setUp(): void {
        $this->ddlManager = new PostgreSQLDDLManager();
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
"description" TEXT NOT NULL,
"start_date" DATE,
"last_modified" TIMESTAMP
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
"id" BIGSERIAL NOT NULL PRIMARY KEY,
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
"description" TEXT NOT NULL,
"start_date" DATE,
"last_modified" TIMESTAMP
);CREATE INDEX name_ind ON test ("name");CREATE INDEX score_ind ON test ("score","start_date");', $sql);

    }


    public function testIfSimpleTableAlterationCorrectlyConvertedToPostgreSQLSyntax() {

        $tableAlteration = new TableAlteration("test", null,
            new ColumnAlterations([
                new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000)
            ], [
                new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
                new TableColumn("score", TableColumn::SQL_INT),
            ], [
                "name"
            ]),
            new IndexAlterations(null, [], [], []));


        $sql = $this->ddlManager->generateModifyTableSQL($tableAlteration);

        $this->assertStringStartsWith("ALTER TABLE test", $sql);
        $this->assertStringContainsString('ALTER COLUMN "score" TYPE INT', $sql);
        $this->assertStringContainsString('ALTER COLUMN "start_date" TYPE TIMESTAMP', $sql);
        $this->assertStringContainsString('ADD COLUMN "notes" VARCHAR(2000)', $sql);
        $this->assertStringContainsString('DROP COLUMN "name"', $sql);

    }


    public function testIfTableAlterationWithColumnRenamingConvertedToPostgreSQLSyntaxCorrectly() {

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
            new IndexAlterations(null, [], [], []));

        $sql = $this->ddlManager->generateModifyTableSQL($tableAlteration);

        $this->assertStringContainsString("ALTER TABLE test", $sql);
        $this->assertStringContainsString('ADD COLUMN "notes" VARCHAR(2000)', $sql);
        $this->assertStringContainsString('RENAME COLUMN "description" TO "new_description"', $sql);
        $this->assertStringContainsString('ALTER COLUMN "new_description" TYPE TEXT', $sql);
        $this->assertStringContainsString('ALTER COLUMN "new_description" SET NOT NULL', $sql);
        $this->assertStringContainsString('RENAME COLUMN "score" TO "updated_score"', $sql);
        $this->assertStringContainsString('ALTER COLUMN "updated_score" TYPE INT', $sql);
        $this->assertStringContainsString('ALTER COLUMN "start_date" TYPE TIMESTAMP', $sql);
        $this->assertStringContainsString('DROP COLUMN "name"', $sql);

    }


    public function testIfTableAlterationWithPrimaryKeyChangesIsConvertedToPostgreSQLSyntaxCorrectly() {

        $connection = MockObjectProvider::instance()->getMockInstance(PostgreSQLDatabaseConnection::class);
        $resultSet = MockObjectProvider::instance()->getMockInstance(ResultSet::class);
        $connection->returnValue("query", $resultSet);
        $resultSet->returnValue("fetchAll", [0 => ["primary_key" => "test_pk"]]);

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
            new IndexAlterations([new TableColumn("id", TableColumn::SQL_INT), new TableColumn("new_description", TableColumn::SQL_BLOB)], [], [], []));

        $sql = $this->ddlManager->generateModifyTableSQL($tableAlteration, $connection);

        $this->assertStringContainsString("ALTER TABLE test", $sql);
        $this->assertStringContainsString("DROP CONSTRAINT test_pk", $sql);
        $this->assertStringContainsString('ADD PRIMARY KEY ("id","new_description")', $sql);

    }


    public function testIfTableAlterationWithIndexAlterationsIsConvertedToPostgreSQLSyntaxCorrectly() {

        $tableAlteration = new TableAlteration("test", null,
            new ColumnAlterations([], [], []),
            new IndexAlterations(null, [
                new TableIndex("new_ind", ["name"])
            ], [
                new TableIndex("score_ind", ["score", "start_date", "description"]),
                new TableIndex("date_ind", ["last_modified", "start_date"])
            ], [
                new TableIndex("name_ind", ["name"]),
            ]));

        $sql = $this->ddlManager->generateModifyTableSQL($tableAlteration);

        $this->assertEquals('CREATE INDEX new_ind ON test ("name");DROP INDEX score_ind;CREATE INDEX score_ind ON test ("score","start_date","description");DROP INDEX date_ind;CREATE INDEX date_ind ON test ("last_modified","start_date");DROP INDEX name_ind;', $sql);

    }


    public function testCanCreateTableDropSQL() {

        $sql = $this->ddlManager->generateTableDropSQL("test");
        $this->assertStringContainsString("DROP TABLE test", $sql);

    }
}