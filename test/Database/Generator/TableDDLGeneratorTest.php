<?php

namespace Kinikit\Persistence\Database\Generator;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\DDL\ColumnAlterations;
use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\DDL\IndexAlterations;
use Kinikit\Persistence\Database\DDL\TableAlteration;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

/**
 * Test cases for the Table DDL generator
 *
 *
 * Class TableDDLGeneratorTest
 */
class TableDDLGeneratorTest extends TestCase {

    /**
     * @var TableDDLGenerator
     */
    private $generator;


    public function setUp(): void {
        $this->generator = new TableDDLGenerator();
    }

    public function testDDLManagerIsCalledWithTableMetaDataOnGenerateTableCreateSQL() {

        $tableMetaData = new TableMetaData("test", [], []);
        $connection = MockObjectProvider::instance()->getMockInstance(DatabaseConnection::class);
        $ddlManager = MockObjectProvider::instance()->getMockInstance(DDLManager::class);

        $connection->returnValue("getDDLManager", $ddlManager, []);
        $ddlManager->returnValue("generateTableCreateSQL", "");

        $this->generator->generateTableCreateSQL($tableMetaData, $connection);

        $this->assertTrue($ddlManager->methodWasCalled("generateTableCreateSQL", [$tableMetaData]));

    }

    public function testCanGenerateModifyTableObjectFromPreviousAndNewMetaData() {

        $databaseConnection = MockObjectProvider::instance()->getMockInstance(DatabaseConnection::class);
        $ddlManager = MockObjectProvider::instance()->getMockInstance(DDLManager::class);

        $databaseConnection->returnValue("getDDLManager", $ddlManager, []);
        $ddlManager->returnValue("generateModifyTableSQL", "");

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

        $expectedObject = new TableAlteration("test", null,
            new ColumnAlterations([
                new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000)
            ], [
                new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
                new TableColumn("score", TableColumn::SQL_INT),
            ], [
                "name"
            ]),
            new IndexAlterations(null, [], [], []));

        $this->assertEquals($expectedObject, $ddlManager->getMethodCallHistory("generateModifyTableSQL")[0][0]);

    }


    public function testIfUpdatableTableColumnSuppliedWithPreviousNameFieldColumnIsRenamed() {

        $databaseConnection = MockObjectProvider::instance()->getMockInstance(DatabaseConnection::class);
        $ddlManager = MockObjectProvider::instance()->getMockInstance(DDLManager::class);

        $databaseConnection->returnValue("getDDLManager", $ddlManager, []);
        $ddlManager->returnValue("generateModifyTableSQL", "");

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

        $expectedObject = new TableAlteration("test", null,
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

        $this->assertEquals($expectedObject, $ddlManager->getMethodCallHistory("generateModifyTableSQL")[0][0]);

    }


    public function testIfPrimaryKeyHasChangedItIsRegeneratedAsPartOfModifySQL() {

        $databaseConnection = MockObjectProvider::instance()->getMockInstance(DatabaseConnection::class);
        $ddlManager = MockObjectProvider::instance()->getMockInstance(DDLManager::class);

        $databaseConnection->returnValue("getDDLManager", $ddlManager, []);
        $ddlManager->returnValue("generateModifyTableSQL", "");

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

        $expectedObject = new TableAlteration("test", null,
            new ColumnAlterations([
                new TableColumn("notes", TableColumn::SQL_VARCHAR, 2000)
            ], [
                new UpdatableTableColumn("new_description", TableColumn::SQL_BLOB, null, null, null, true, false, true, "description"),
                new TableColumn("start_date", TableColumn::SQL_DATE_TIME),
                new UpdatableTableColumn("updated_score", TableColumn::SQL_INT, null, null, null, false, false, false, "score")
            ], [
                "name"
            ]),
            new IndexAlterations([new TableColumn("id", TableColumn::SQL_INT,null,null,null,true,false,true),  new UpdatableTableColumn("new_description", TableColumn::SQL_BLOB, null, null, null, true, false, true, "description")], [], [], []));

        $this->assertEquals($expectedObject, $ddlManager->getMethodCallHistory("generateModifyTableSQL")[0][0]);

    }


    public function testModifySQLIncludesDropAndCreateStatementsForModifiedNewAndRemovedIndexes() {

        $databaseConnection = MockObjectProvider::instance()->getMockInstance(DatabaseConnection::class);
        $ddlManager = MockObjectProvider::instance()->getMockInstance(DDLManager::class);

        $databaseConnection->returnValue("getDDLManager", $ddlManager, []);
        $ddlManager->returnValue("generateModifyTableSQL", "");

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

        $sql = $this->generator->generateTableModifySQL($previousMetaData, $newMetaData, $databaseConnection);

        $expectedObject = new TableAlteration("test", null,
            new ColumnAlterations([], [], []),
            new IndexAlterations(null, [
                new TableIndex("new_ind", ["name"])
            ], [
                new TableIndex("score_ind", ["score", "start_date", "description"]),
                new TableIndex("date_ind", ["last_modified", "start_date"])
            ], [
                new TableIndex("name_ind", ["name"]),
            ]));

        $this->assertEquals($expectedObject, $ddlManager->getMethodCallHistory("generateModifyTableSQL")[0][0]);

    }

    public function testCanCreateTableDropSQL() {

        $sql = $this->generator->generateTableDropSQL("test");
        $this->assertStringContainsString("DROP TABLE test", $sql);

    }

}