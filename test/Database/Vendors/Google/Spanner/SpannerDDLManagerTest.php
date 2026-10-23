<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Kinikit\Persistence\Database\Generator\TableDDLGenerator;
use Kinikit\Persistence\Database\DDL\ColumnAlterations;
use Kinikit\Persistence\Database\DDL\IndexAlterations;
use Kinikit\Persistence\Database\DDL\TableAlteration;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
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

    public function testGeneratesMappedColumnTypesDefaultsKeysAndIndexes(): void {
        $ddlManager = new SpannerDDLManager();
        $sql = $ddlManager->generateTableCreateSQL(new TableMetaData("events", [
            new TableColumn("event_id", TableColumn::SQL_BIGINT, null, null, null, true),
            new TableColumn("label", TableColumn::SQL_VARCHAR, 80, null, "O'Brien"),
            new TableColumn("payload", TableColumn::SQL_JSON),
            new TableColumn("created_at", TableColumn::SQL_TIMESTAMP)
        ], [
            new TableIndex("by_label", ["label"])
        ]));

        $this->assertStringContainsString("`event_id` INT64 NOT NULL", $sql);
        $this->assertStringContainsString("`label` STRING(80) DEFAULT ('O\\'Brien')", $sql);
        $this->assertStringContainsString("`payload` JSON", $sql);
        $this->assertStringContainsString("`created_at` TIMESTAMP", $sql);
        $this->assertStringContainsString("PRIMARY KEY (`event_id`)", $sql);
        $this->assertStringContainsString("CREATE INDEX `by_label` ON `events` (`label`)", $sql);

        $indexFallbackSql = $ddlManager->generateTableCreateSQL(new TableMetaData("fallback_events", [
            new TableColumn("event_id", TableColumn::SQL_INTEGER),
            new TableColumn("owner_id", TableColumn::SQL_INTEGER)
        ], [new TableIndex("by_owner", ["owner_id"])]));
        $this->assertStringContainsString("PRIMARY KEY (`owner_id`)", $indexFallbackSql);

        $firstColumnFallbackSql = $ddlManager->generateTableCreateSQL(new TableMetaData("simple_events", [
            new TableColumn("event_id", TableColumn::SQL_INTEGER)
        ]));
        $this->assertStringContainsString("PRIMARY KEY (`event_id`)", $firstColumnFallbackSql);
    }

    public function testGeneratesAddModifyDropAndIndexAlterationStatements(): void {
        $ddlManager = new SpannerDDLManager();
        $alteration = new TableAlteration(
            "events",
            null,
            new ColumnAlterations(
                [new TableColumn("description", TableColumn::SQL_VARCHAR, 120)],
                [
                    new TableColumn("status", TableColumn::SQL_VARCHAR, 20),
                    new \Kinikit\Persistence\Database\MetaData\UpdatableTableColumn(
                        "renamed_status",
                        TableColumn::SQL_VARCHAR,
                        20,
                        null,
                        null,
                        false,
                        false,
                        false,
                        "status"
                    )
                ],
                ["legacy"]
            ),
            new IndexAlterations(
                null,
                [new TableIndex("by_description", ["description"])],
                [new TableIndex("by_status", ["status"])],
                [new TableIndex("by_legacy", ["legacy"])]
            )
        );

        $sql = $ddlManager->generateModifyTableSQL($alteration);
        $this->assertStringContainsString("ALTER TABLE `events` ADD COLUMN `description` STRING(120)", $sql);
        $this->assertStringContainsString("ALTER TABLE `events` ALTER COLUMN `status` STRING(20)", $sql);
        $this->assertStringContainsString("ALTER TABLE `events` DROP COLUMN `legacy`", $sql);
        $this->assertStringContainsString("CREATE INDEX `by_description` ON `events` (`description`)", $sql);
        $this->assertStringContainsString("DROP INDEX `by_status`", $sql);
        $this->assertSame(1, substr_count($sql, "CREATE INDEX `by_status`"));
        $this->assertStringContainsString("DROP INDEX `by_legacy`", $sql);
        $this->assertStringNotContainsString("renamed_status", $sql);
        $this->assertSame("DROP TABLE IF EXISTS events", $ddlManager->generateTableDropSQL("events"));

        $this->assertSame("", $ddlManager->generateModifyTableSQL(new TableAlteration(
            "events",
            null,
            new ColumnAlterations([], [], []),
            new IndexAlterations(null, [], [], [])
        )));
    }
}
