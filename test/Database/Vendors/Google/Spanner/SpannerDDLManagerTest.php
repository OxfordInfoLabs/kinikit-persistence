<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Kinikit\Persistence\Database\Generator\TableDDLGenerator;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\ResultSet\ResultSet;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class SpannerDDLManagerTest extends TestCase {

    public function testStringColumnsWithDefaultLengthAreGeneratedAsUnbounded(): void {
        $ddlManager = new SpannerDDLManager();
        $sql = $ddlManager->generateTableCreateSQL(new TableMetaData("events", [
            new TableColumn("event_name", TableColumn::SQL_VARCHAR, 255, null, null, true, false, true),
            new TableColumn("description", TableColumn::SQL_VARCHAR, 32767)
        ]));

        $this->assertStringContainsString("`event_name` STRING(MAX) NOT NULL", $sql);
        $this->assertStringContainsString("`description` STRING(32767)", $sql);
    }


    public function testStringMaxMetadataDoesNotCauseAColumnLengthAlteration(): void {
        $metadataResult = $this->createMock(ResultSet::class);
        $metadataResult->method("fetchAll")->willReturn([[
            "COLUMN_NAME" => "event_name",
            "SPANNER_TYPE" => "STRING(MAX)",
            "IS_NULLABLE" => "NO",
            "COLUMN_DEFAULT" => null
        ]]);

        $primaryKeyResult = $this->createMock(ResultSet::class);
        $primaryKeyResult->method("fetchAll")->willReturn([["COLUMN_NAME" => "event_name"]]);

        $connection = $this->getMockBuilder(SpannerDatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["query", "getDDLManager"])
            ->getMock();
        $connection->method("query")->willReturnOnConsecutiveCalls($metadataResult, $primaryKeyResult);
        $connection->method("getDDLManager")->willReturn(new SpannerDDLManager());

        $existingColumns = $connection->getTableColumnMetaData("events");
        $this->assertSame(255, $existingColumns["event_name"]->getLength());

        $original = new TableMetaData("events", $existingColumns);
        $desired = new TableMetaData("events", [
            new TableColumn("event_name", TableColumn::SQL_VARCHAR, 255, null, null, true, false, true)
        ]);

        $this->assertSame("", (new TableDDLGenerator())->generateTableModifySQL($original, $desired, $connection));
    }
}
