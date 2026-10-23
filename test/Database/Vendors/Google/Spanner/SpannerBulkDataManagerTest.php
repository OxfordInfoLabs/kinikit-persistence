<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Date;
use Google\Cloud\Spanner\KeySet;
use Google\Cloud\Spanner\Timestamp;
use Google\Cloud\Spanner\Transaction;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\BindParameterType;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class SpannerBulkDataManagerTest extends TestCase {

    public function testDateAndTimestampColumnsAreBoundForInsertMutations(): void {
        $tableMetaData = new TableMetaData("events", [
            new TableColumn("event_date", TableColumn::SQL_DATE),
            new TableColumn("created_at", TableColumn::SQL_TIMESTAMP),
            new TableColumn("description", TableColumn::SQL_VARCHAR)
        ]);

        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())
            ->method("insertBatch")
            ->with("events", $this->callback(function (array $rows): bool {
                $this->assertInstanceOf(Date::class, $rows[0]["event_date"]);
                $this->assertSame("2026-10-19", $rows[0]["event_date"]->formatAsString());
                $this->assertInstanceOf(Timestamp::class, $rows[0]["created_at"]);
                $this->assertSame("2026-10-19T10:15:30.000000Z", $rows[0]["created_at"]->formatAsString());
                $this->assertSame("launch", $rows[0]["description"]);
                return true;
            }));
        $transaction->expects($this->once())->method("commit");

        $spannerDatabase = $this->createMock(Database::class);
        $spannerDatabase->expects($this->once())
            ->method("runTransaction")
            ->with($this->callback(function (callable $operation) use ($transaction): bool {
                $operation($transaction);
                return true;
            }));

        $connection = $this->getMockBuilder(SpannerDatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["getTableMetaData", "getSpannerConnection"])
            ->getMock();
        $connection->method("getTableMetaData")->with("events")->willReturn($tableMetaData);
        $connection->method("getSpannerConnection")->willReturn($spannerDatabase);

        $bulkDataManager = new SpannerBulkDataManager($connection);
        $bulkDataManager->insert("events", [[
            "event_date" => "2026-10-19",
            "created_at" => "2026-10-19T10:15:30Z",
            "description" => "launch"
        ]], ["event_date", "created_at", "description"]);
    }


    public function testExplicitBindParametersAreConvertedForSpannerMutations(): void {
        $tableMetaData = new TableMetaData("events", [
            new TableColumn("event_date", TableColumn::SQL_DATE)
        ]);

        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())
            ->method("insertBatch")
            ->with("events", $this->callback(function (array $rows): bool {
                $this->assertInstanceOf(Date::class, $rows[0]["event_date"]);
                $this->assertSame("2026-10-19", $rows[0]["event_date"]->formatAsString());
                return true;
            }));
        $transaction->expects($this->once())->method("commit");

        $spannerDatabase = $this->createMock(Database::class);
        $spannerDatabase->expects($this->once())
            ->method("runTransaction")
            ->with($this->callback(function (callable $operation) use ($transaction): bool {
                $operation($transaction);
                return true;
            }));

        $connection = $this->getMockBuilder(SpannerDatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["getTableMetaData", "getSpannerConnection"])
            ->getMock();
        $connection->method("getTableMetaData")->with("events")->willReturn($tableMetaData);
        $connection->method("getSpannerConnection")->willReturn($spannerDatabase);

        $bulkDataManager = new SpannerBulkDataManager($connection);
        $bulkDataManager->insert("events", [[
            "event_date" => new BindParameter("2026-10-19", BindParameterType::TYPE_DATE)
        ]], ["event_date"]);
    }

    public function testInsertUsesUpsertWhenIgnoringDuplicatesAndProcessesEachBatch(): void {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->exactly(2))
            ->method("insertOrUpdateBatch")
            ->withConsecutive(
                ["events", [["id" => 1, "description" => "first"]]],
                ["events", [["id" => 2, "description" => "second"]]]
            );
        $transaction->expects($this->exactly(2))->method("commit");
        $database = $this->createMock(Database::class);
        $database->expects($this->exactly(2))
            ->method("runTransaction")
            ->willReturnCallback(function (callable $operation) use ($transaction) {
                $operation($transaction);
            });

        $manager = new SpannerBulkDataManager($this->connection($database, new TableMetaData("events", [])));
        $manager->setBatchSize(1);
        $manager->insert("events", [
            ["id" => 1, "description" => "first"],
            ["id" => 2, "description" => "second"]
        ], ["id", "description"], true);
    }

    public function testUpdateIncludesMatchColumnsAndCommitsTheMutation(): void {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())
            ->method("updateBatch")
            ->with("events", [["id" => 3, "description" => "updated"]]);
        $transaction->expects($this->once())->method("commit");
        $database = $this->databaseForTransaction($transaction);

        $manager = new SpannerBulkDataManager($this->connection($database, new TableMetaData("events", [])));
        $manager->update("events", [["id" => 3, "description" => "updated"]], ["description"], ["id"]);
    }

    public function testReplaceUsesInsertOrUpdateAndConvertsDateColumns(): void {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())
            ->method("insertOrUpdateBatch")
            ->with("events", $this->callback(function (array $rows): bool {
                $this->assertInstanceOf(Date::class, $rows[0]["event_date"]);
                $this->assertSame("2026-10-19", $rows[0]["event_date"]->formatAsString());
                return true;
            }));
        $transaction->expects($this->once())->method("commit");
        $database = $this->databaseForTransaction($transaction);
        $metadata = new TableMetaData("events", [new TableColumn("event_date", TableColumn::SQL_DATE)]);

        $manager = new SpannerBulkDataManager($this->connection($database, $metadata));
        $manager->replace("events", [["event_date" => "2026-10-19"]], ["event_date"]);
    }

    public function testDeleteBuildsKeySetsForSimpleAndCompositeKeys(): void {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->exactly(2))
            ->method("delete")
            ->withConsecutive(
                ["events", $this->callback(function (KeySet $keySet): bool {
                    $this->assertSame([11, 12], $keySet->keys());
                    return true;
                })],
                ["event_links", $this->callback(function (KeySet $keySet): bool {
                    $this->assertSame([[21, "x"]], $keySet->keys());
                    return true;
                })]
            );
        $transaction->expects($this->exactly(2))->method("commit");
        $database = $this->databaseForTransaction($transaction, 2);

        $manager = new SpannerBulkDataManager($this->connection($database, new TableMetaData("events", [])));
        $manager->delete("events", [11, 12], ["event_id"]);

        $linksMetadata = new TableMetaData("event_links", []);
        $manager = new SpannerBulkDataManager($this->connection($database, $linksMetadata));
        $manager->delete("event_links", [["left_id" => 21, "right_id" => "x"]], ["left_id", "right_id"]);
    }

    private function connection(Database $database, TableMetaData $metadata): SpannerDatabaseConnection {
        $connection = $this->getMockBuilder(SpannerDatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["getTableMetaData", "getSpannerConnection"])
            ->getMock();
        $connection->method("getTableMetaData")->willReturn($metadata);
        $connection->method("getSpannerConnection")->willReturn($database);
        return $connection;
    }

    private function databaseForTransaction(Transaction $transaction, int $calls = 1): Database {
        $database = $this->createMock(Database::class);
        $database->expects($this->exactly($calls))
            ->method("runTransaction")
            ->willReturnCallback(function (callable $operation) use ($transaction) {
                $operation($transaction);
            });
        return $database;
    }
}
