<?php

namespace Kinikit\Persistence\Database\Vendors\MySQL;

use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class MySQLDDLManagerTest extends TestCase {

    /**
     * @var MySQLDDLManager
     */
    private MySQLDDLManager $ddlManager;

    public function setUp(): void {
        $this->ddlManager = new MySQLDDLManager();
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
`id` INT,
`name` VARCHAR(255),
`score` FLOAT(5,5),
`description` BLOB NOT NULL,
`start_date` DATE,
`last_modified` DATETIME
);', $sql);

    }

    public function testCanGenerateCreateStatementFromMetaDataWithPrimaryKeysIncludingAutoIncrement() {

        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
        ]);

        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

        $this->assertEquals('CREATE TABLE test (
`id` INT NOT NULL,
`name` VARCHAR(255),
PRIMARY KEY (`id`)
);', $sql);


        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true, true),
            new TableColumn("name", TableColumn::SQL_VARCHAR, 255, null),
        ]);

        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

        $this->assertEquals('CREATE TABLE test (
`id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
`name` VARCHAR(255)
);', $sql);

    }

    public function testPreviousColumnNamesAreIgnoredInUpdatableColumnMetaDataIfSuppliedForCreate() {

        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT),
            new UpdatableTableColumn("name", TableColumn::SQL_VARCHAR, 255, null, null, false, false, false, "other_name")
        ]);

        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

        $this->assertEquals('CREATE TABLE test (
`id` INT,
`name` VARCHAR(255)
);', $sql);

    }

    public function testColumnNamesCorrectlyEscapedInCreateDDL() {

        $metaData = new TableMetaData("test", [
            new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            new TableColumn("name's", TableColumn::SQL_VARCHAR, 255, null),
        ]);


        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

        $this->assertEquals("CREATE TABLE test (
`id` INT NOT NULL,
`name's` VARCHAR(255),
PRIMARY KEY (`id`)
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

        $sql = $this->ddlManager->generateTableCreateSQL($metaData);

        $this->assertEquals("CREATE TABLE test (
`id` INT,
`name` VARCHAR(255),
`score` FLOAT(5,5),
`description` BLOB NOT NULL,
`start_date` DATE,
`last_modified` DATETIME
);CREATE INDEX name_ind ON test (name);CREATE INDEX score_ind ON test (score,start_date);", $sql);

    }

    public function testCanCreateTableDropSQL() {

        $sql = $this->ddlManager->generateTableDropSQL("test");
        $this->assertStringContainsString("DROP TABLE test", $sql);

    }
}