<?php


namespace Kinikit\Persistence\Database\Connection\MSAccess;

include_once "autoloader.php";

/**
 * Test cases for MS Access Database Connection.
 *
 * Class MSAccessDatabaseConnectionTest
 *
 * @group dev
 */
class MSAccessDatabaseConnectionTest extends \PHPUnit\Framework\TestCase {


    public function testCanGetTableMetaData() {

        $databaseConnection = new MSAccessDatabaseConnection("OOATest");

        $tableMetaData = $databaseConnection->getTableMetaData("Code_Lists");

        $this->assertEquals("Code_Lists", $tableMetaData->getTableName());


        $columns = $tableMetaData->getColumns();
        $this->assertEquals(6, sizeof($columns));
        $this->assertEquals(new TableColumn("Code_List_No", TableColumn::SQL_BIGINT, 30), $columns["Code_List_No"]);
        $this->assertEquals(new TableColumn("Name", TableColumn::SQL_VARCHAR, 100), $columns["Name"]);
        $this->assertEquals(new TableColumn("Rule_Desc", TableColumn::SQL_VARCHAR, 200), $columns["Rule_Desc"]);
        $this->assertEquals(new TableColumn("Used_In", TableColumn::SQL_VARCHAR, 200), $columns["Used_In"]);
        $this->assertEquals(new TableColumn("Guidance_Notes", TableColumn::SQL_VARCHAR, 1000000000), $columns["Guidance_Notes"]);
        $this->assertEquals(new TableColumn("EDI_Standard", TableColumn::SQL_VARCHAR, 1), $columns["EDI_Standard"]);

    }


}