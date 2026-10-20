<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Date;
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
}
