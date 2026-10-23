<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use DateTimeImmutable;
use Google\Cloud\Spanner\Result;
use Google\Cloud\Spanner\V1\TypeCode;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class SpannerResultSetTest extends TestCase {

    public function testReadsRowsAndMapsAvailableColumnMetadata(): void {
        $result = $this->resultWith(
            [
                ["id" => 9, "name" => "Ada", "enabled" => true],
                ["id" => 10, "name" => "Lin", "enabled" => false]
            ],
            ["rowType" => ["fields" => [
                ["name" => "id", "type" => ["code" => TypeCode::INT64]],
                ["name" => "name", "type" => ["code" => TypeCode::STRING]],
                ["name" => "enabled", "type" => ["code" => TypeCode::BOOL]],
                ["name" => "unknown", "type" => ["code" => "UNRECOGNIZED"]]
            ]]]
        );

        $resultSet = new SpannerResultSet($result);
        $this->assertSame(["id", "name", "enabled", "unknown"], $resultSet->getColumnNames());
        $this->assertEquals([
            new ResultSetColumn("id", TableColumn::SQL_INTEGER),
            new ResultSetColumn("name", TableColumn::SQL_VARCHAR),
            new ResultSetColumn("enabled", TableColumn::SQL_TINYINT),
            new ResultSetColumn("unknown", TableColumn::SQL_VARCHAR)
        ], $resultSet->getColumns());
        $this->assertSame([
            ["id" => 9, "name" => "Ada", "enabled" => true],
            ["id" => 10, "name" => "Lin", "enabled" => false]
        ], $resultSet->fetchAll());
        $this->assertSame(false, $resultSet->nextRow());
    }

    public function testCanFetchRowsBeforeRequestingMetadata(): void {
        $result = $this->resultWith(
            [["id" => 1]],
            ["rowType" => ["fields" => [["name" => "id", "type" => ["code" => TypeCode::INT64]]]]]
        );

        $resultSet = new SpannerResultSet($result);
        $this->assertSame(["id" => 1], $resultSet->nextRow());
        $this->assertSame(["id"], $resultSet->getColumnNames());
        $this->assertSame(false, $resultSet->nextRow());
    }

    public function testMapsEverySupportedSpannerTypeToItsSqlType(): void {
        $expectedTypes = [
            TypeCode::BOOL => TableColumn::SQL_TINYINT,
            TypeCode::INT64 => TableColumn::SQL_INTEGER,
            TypeCode::FLOAT64 => TableColumn::SQL_FLOAT,
            TypeCode::FLOAT32 => TableColumn::SQL_FLOAT,
            TypeCode::TIMESTAMP => TableColumn::SQL_DATE_TIME,
            TypeCode::DATE => TableColumn::SQL_DATE,
            TypeCode::STRING => TableColumn::SQL_VARCHAR,
            TypeCode::BYTES => TableColumn::SQL_BLOB,
            TypeCode::PBARRAY => TableColumn::SQL_VECTOR,
            TypeCode::STRUCT => TableColumn::SQL_VARCHAR,
            TypeCode::NUMERIC => TableColumn::SQL_DECIMAL,
            TypeCode::JSON => TableColumn::SQL_JSON,
            TypeCode::PROTO => TableColumn::SQL_VARCHAR,
            TypeCode::ENUM => TableColumn::SQL_VARCHAR,
            TypeCode::INTERVAL => TableColumn::SQL_VARCHAR,
            TypeCode::UUID => TableColumn::SQL_VARCHAR
        ];
        $fields = [];
        foreach (array_keys($expectedTypes) as $typeCode) {
            $fields[] = ["name" => "column_" . count($fields), "type" => ["code" => $typeCode]];
        }
        $resultSet = new SpannerResultSet($this->resultWith([[]], ["rowType" => ["fields" => $fields]]));

        $this->assertSame(array_values($expectedTypes), array_map(
            fn(ResultSetColumn $column) => $column->getType(),
            $resultSet->getColumns()
        ));
    }

    public function testNormalisesSpannerAndDateTimeValuesInRows(): void {
        $timestamp = new class {
            public function formatAsString(): string {
                return "2026-10-23T08:00:00Z";
            }
        };
        $stringable = new class {
            public function __toString(): string {
                return "bytes";
            }
        };
        $result = $this->resultWith(
            [[
                "timestamp" => $timestamp,
                "date" => new DateTimeImmutable("2026-10-23 08:00:00"),
                "value" => $stringable,
                "plain" => 1
            ]],
            ["rowType" => ["fields" => []]]
        );

        $this->assertSame([
            "timestamp" => "2026-10-23T08:00:00Z",
            "date" => (new DateTimeImmutable("2026-10-23 08:00:00"))->format("Y-m-d H:i:s"),
            "value" => "bytes",
            "plain" => 1
        ], (new SpannerResultSet($result))->nextRow());
    }

    public function testReturnsNoMetadataWhenResultHasNoRowsOrFieldsAndSupportsClose(): void {
        $emptyResult = $this->resultWith([], null);
        $emptySet = new SpannerResultSet($emptyResult);
        $this->assertSame([], $emptySet->getColumns());
        $this->assertSame([], $emptySet->getColumnNames());
        $this->assertSame(false, $emptySet->nextRow());

        $noFieldsResult = $this->resultWith([["value" => "x"]], []);
        $noFieldsSet = new SpannerResultSet($noFieldsResult);
        $this->assertSame(["value" => "x"], $noFieldsSet->nextRow());
        $this->assertSame([], $noFieldsSet->getColumns());

        $noFieldsSet->close();
        $this->assertSame([], $noFieldsSet->getColumns());
    }

    private function resultWith(array $rows, ?array $metadata): Result {
        $result = $this->createMock(Result::class);
        $result->method("rows")->willReturn((static function () use ($rows): \Generator {
            yield from $rows;
        })());
        $result->method("metadata")->willReturn($metadata);
        return $result;
    }
}
