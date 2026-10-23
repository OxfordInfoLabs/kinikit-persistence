<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Result;
use Google\Cloud\Spanner\Transaction;
use Kinikit\Persistence\Database\Exception\SpannerSQLException;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableIndexColumn;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\BindParameterType;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class SpannerDatabaseConnectionTest extends TestCase {

    public function testMapsPositionalParametersAndRunsQueries(): void {
        $result = $this->createMock(Result::class);
        $database = $this->createMock(Database::class);
        $database->expects($this->once())
            ->method("execute")
            ->with(
                "SELECT * FROM events WHERE id = @p1 AND enabled = @p2",
                ["parameters" => ["p1" => 12, "p2" => true]]
            )
            ->willReturn($result);

        $connection = $this->connectionWith($database);
        $this->assertInstanceOf(SpannerResultSet::class, $connection->doQuery(
            "SELECT * FROM events WHERE id = ? AND enabled = ?",
            [
                new BindParameter("12", BindParameterType::TYPE_INT),
                new BindParameter("TRUE", BindParameterType::TYPE_STRING)
            ]
        ));
    }

    public function testUsesNamedParametersWithoutChangingSqlAndOmitsEmptyParameterOptions(): void {
        $result = $this->createMock(Result::class);
        $database = $this->createMock(Database::class);
        $database->expects($this->once())
            ->method("execute")
            ->with("SELECT * FROM events WHERE id = @eventId", [
                "parameters" => ["eventId" => 34]
            ])
            ->willReturn($result);

        $connection = $this->connectionWith($database);
        $connection->doQuery("SELECT * FROM events WHERE id = @eventId", [
            "eventId" => new BindParameter(34, BindParameterType::TYPE_INT)
        ]);

        $databaseWithoutParameters = $this->createMock(Database::class);
        $databaseWithoutParameters->expects($this->once())
            ->method("execute")
            ->with("SELECT 1", [])
            ->willReturn($result);
        $this->connectionWith($databaseWithoutParameters)->doQuery("SELECT 1", []);
    }

    public function testExecuteRunsUpdateAndCommitsTransaction(): void {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())
            ->method("executeUpdate")
            ->with("UPDATE events SET name = @p1", [
                "parameters" => ["p1" => "launch"]
            ]);
        $transaction->expects($this->once())->method("commit");

        $database = $this->createMock(Database::class);
        $database->expects($this->once())
            ->method("runTransaction")
            ->with($this->callback(function (callable $operation) use ($transaction): bool {
                $operation($transaction);
                return true;
            }));

        $this->assertTrue($this->connectionWith($database)->execute("UPDATE events SET name = ?", "launch"));
    }

    public function testWrapsSpannerFailuresAsSqlExceptions(): void {
        $database = $this->createMock(Database::class);
        $database->expects($this->once())
            ->method("execute")
            ->willThrowException(new \RuntimeException("query failed", 17));

        try {
            $this->connectionWith($database)->doQuery("SELECT 1", []);
            $this->fail("Expected a SpannerSQLException");
        } catch (SpannerSQLException $exception) {
            $this->assertSame("query failed", $exception->getMessage());
            $this->assertSame(17, $exception->getSqlStateCode());
        }
    }

    public function testBuildsColumnMetadataFromSpannerInformationSchema(): void {
        $columnResults = $this->createMock(\Kinikit\Persistence\Database\ResultSet\ResultSet::class);
        $columnResults->method("fetchAll")->willReturn([
            [
                "COLUMN_NAME" => "event_id",
                "SPANNER_TYPE" => "INT64",
                "IS_NULLABLE" => "NO",
                "COLUMN_DEFAULT" => null
            ],
            [
                "COLUMN_NAME" => "description",
                "SPANNER_TYPE" => "STRING(MAX)",
                "IS_NULLABLE" => "YES",
                "COLUMN_DEFAULT" => "'unknown'"
            ],
            [
                "COLUMN_NAME" => "created_at",
                "SPANNER_TYPE" => "TIMESTAMP",
                "IS_NULLABLE" => "YES",
                "COLUMN_DEFAULT" => null
            ]
        ]);
        $primaryKeyResults = $this->createMock(\Kinikit\Persistence\Database\ResultSet\ResultSet::class);
        $primaryKeyResults->method("fetchAll")->willReturn([["COLUMN_NAME" => "event_id"]]);

        $connection = $this->getMockBuilder(SpannerDatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["query"])
            ->getMock();
        $connection->expects($this->exactly(2))
            ->method("query")
            ->withConsecutive(
                [$this->stringContains("INFORMATION_SCHEMA.COLUMNS"), ["tableName" => "events"]],
                [$this->stringContains("INFORMATION_SCHEMA.KEY_COLUMN_USAGE"), ["tableName" => "events"]]
            )
            ->willReturnOnConsecutiveCalls($columnResults, $primaryKeyResults);

        $columns = $connection->getTableColumnMetaData("events");
        $this->assertSame(TableColumn::SQL_INTEGER, $columns["event_id"]->getType());
        $this->assertTrue($columns["event_id"]->isPrimaryKey());
        $this->assertTrue($columns["event_id"]->isNotNull());
        $this->assertSame(SpannerDatabaseConnection::DEFAULT_STRING_LENGTH, $columns["description"]->getLength());
        $this->assertSame("'unknown'", $columns["description"]->getDefaultValue());
        $this->assertSame(TableColumn::SQL_DATE_TIME, $columns["created_at"]->getType());
    }

    public function testGroupsIndexColumnsAndReturnsEmptyWhenMetadataQueryFails(): void {
        $result = $this->createMock(\Kinikit\Persistence\Database\ResultSet\ResultSet::class);
        $result->method("nextRow")->willReturnOnConsecutiveCalls(
            ["INDEX_NAME" => "by_status", "COLUMN_NAME" => "status"],
            ["INDEX_NAME" => "by_status", "COLUMN_NAME" => "created_at"],
            ["INDEX_NAME" => "by_owner", "COLUMN_NAME" => "owner_id"],
            false
        );
        $connection = $this->getMockBuilder(SpannerDatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["query"])
            ->getMock();
        $connection->method("query")->willReturn($result);

        $indexes = $connection->getTableIndexMetaData("events");
        $this->assertCount(2, $indexes);
        $this->assertEquals(new TableIndex("by_status", [
            new TableIndexColumn("status"),
            new TableIndexColumn("created_at")
        ]), $indexes[0]);
        $this->assertEquals(new TableIndex("by_owner", [new TableIndexColumn("owner_id")]), $indexes[1]);

        $failingConnection = $this->getMockBuilder(SpannerDatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["query"])
            ->getMock();
        $failingConnection->method("query")->willThrowException(new \RuntimeException("metadata unavailable"));
        $this->assertSame([], $failingConnection->getTableIndexMetaData("events"));
    }

    public function testEscapesIdentifiersAndProvidesVendorManagers(): void {
        $connection = $this->connectionWith($this->createMock(Database::class));

        $this->assertSame("O\\'Brien", $connection->escapeString("O'Brien"));
        $this->assertSame("`events`.`odd``name`", $connection->escapeColumn("events.odd`name"));
        $this->assertInstanceOf(SpannerBulkDataManager::class, $connection->getBulkDataManager());
        $this->assertInstanceOf(SpannerDDLManager::class, $connection->getDDLManager());
        $this->assertSame(
            "SELECT * FROM events WHERE id = @p1",
            $connection->doCreatePreparedStatement("SELECT * FROM events WHERE id = ?")->getStatementSQL()
        );
    }

    private function connectionWith(Database $database): SpannerDatabaseConnection {
        $connection = (new \ReflectionClass(SpannerDatabaseConnection::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(SpannerDatabaseConnection::class, "spannerConnection");
        $property->setAccessible(true);
        $property->setValue($connection, $database);
        return $connection;
    }
}
