<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use DateTimeImmutable;
use DateTimeZone;
use Google\Cloud\Spanner\Date;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Timestamp;
use Google\Cloud\Spanner\Transaction;
use Kinikit\Persistence\Database\Exception\SpannerSQLException;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\BindParameterType;
use Kinikit\Persistence\Database\PreparedStatement\WrongNumberOfPreparedStatementParametersException;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class SpannerPreparedStatementTest extends TestCase {

    public function testMapsDateAndDateTimeParametersToSpannerValues(): void {
        $date = SpannerPreparedStatement::bindParameter(
            new BindParameter("2026-10-01 09:00:00", BindParameterType::TYPE_DATE)
        );
        $dateTime = SpannerPreparedStatement::bindParameter(
            new BindParameter("2026-10-01 09:00:00", BindParameterType::TYPE_DATETIME)
        );

        $this->assertInstanceOf(Date::class, $date);
        $this->assertSame("2026-10-01", $date->formatAsString());
        $this->assertInstanceOf(Timestamp::class, $dateTime);
        $this->assertSame("2026-10-01T09:00:00.000000Z", $dateTime->formatAsString());
    }

    public function testAcceptsDateTimeValuesAndPreservesTypedNulls(): void {
        $dateTimeValue = new DateTimeImmutable("2026-10-01 09:00:00", new DateTimeZone("UTC"));

        $date = SpannerPreparedStatement::bindParameter(
            new BindParameter($dateTimeValue, BindParameterType::TYPE_DATE)
        );
        $dateTime = SpannerPreparedStatement::bindParameter(
            new BindParameter($dateTimeValue, BindParameterType::TYPE_DATETIME)
        );

        $this->assertInstanceOf(Date::class, $date);
        $this->assertSame("2026-10-01", $date->formatAsString());
        $this->assertInstanceOf(Timestamp::class, $dateTime);
        $this->assertSame("2026-10-01T09:00:00.000000Z", $dateTime->formatAsString());
        $this->assertNull(SpannerPreparedStatement::bindParameter(
            new BindParameter(null, BindParameterType::TYPE_DATE)
        ));
        $this->assertNull(SpannerPreparedStatement::bindParameter(
            new BindParameter(null, BindParameterType::TYPE_DATETIME)
        ));
    }

    public function testRewritesPlaceholdersAndExecutesTypedValuesInATransaction(): void {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())
            ->method("executeUpdate")
            ->with("INSERT INTO events (id, ratio, active, name) VALUES (@p1, @p2, @p3, @p4)", [
                "parameters" => ["p1" => 7, "p2" => 1.25, "p3" => true, "p4" => "launch"]
            ]);
        $transaction->expects($this->once())->method("commit");

        $database = $this->createMock(Database::class);
        $database->expects($this->once())
            ->method("runTransaction")
            ->with($this->callback(function (callable $operation) use ($transaction): bool {
                $operation($transaction);
                return true;
            }));

        $statement = new SpannerPreparedStatement(
            "INSERT INTO events (id, ratio, active, name) VALUES (?, ?, ?, ?)",
            $database
        );
        $this->assertSame("INSERT INTO events (id, ratio, active, name) VALUES (@p1, @p2, @p3, @p4)", $statement->getStatementSQL());
        $statement->execute([
            new BindParameter("7", BindParameterType::TYPE_INT),
            new BindParameter("1.25", BindParameterType::TYPE_FLOAT),
            new BindParameter(1, BindParameterType::TYPE_BOOL),
            new BindParameter("launch", BindParameterType::TYPE_STRING)
        ]);
    }

    public function testChecksParameterCountAndWrapsTransactionFailures(): void {
        $statement = new SpannerPreparedStatement("UPDATE events SET name = ?", $this->createMock(Database::class));

        try {
            $statement->execute([]);
            $this->fail("Expected the parameter count to be checked");
        } catch (WrongNumberOfPreparedStatementParametersException $exception) {
            $this->assertStringContainsString("0 parameters when 1 were expected", $exception->getMessage());
        }

        $database = $this->createMock(Database::class);
        $database->expects($this->once())
            ->method("runTransaction")
            ->willThrowException(new \RuntimeException("write failed", 23));
        $statement = new SpannerPreparedStatement("UPDATE events SET name = ?", $database);

        try {
            $statement->execute([new BindParameter("launch", BindParameterType::TYPE_STRING)]);
            $this->fail("Expected the Spanner failure to be wrapped");
        } catch (SpannerSQLException $exception) {
            $this->assertSame("write failed", $exception->getMessage());
            $this->assertSame(23, $exception->getSqlStateCode());
        }
    }

    public function testExecutesStatementsWithoutParameters(): void {
        $database = $this->createMock(Database::class);
        $database->expects($this->once())
            ->method("runTransaction")
            ->with($this->callback(function (callable $operation): bool {
                $transaction = $this->createMock(Transaction::class);
                $transaction->expects($this->once())->method("executeUpdate")->with("DELETE FROM events", ["parameters" => []]);
                $transaction->expects($this->once())->method("commit");
                $operation($transaction);
                return true;
            }));

        $statement = new SpannerPreparedStatement("DELETE FROM events", $database);
        $statement->execute([]);
    }
}
