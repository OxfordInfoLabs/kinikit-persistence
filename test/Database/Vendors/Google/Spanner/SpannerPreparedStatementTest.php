<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use DateTimeImmutable;
use DateTimeZone;
use Google\Cloud\Spanner\Date;
use Google\Cloud\Spanner\Timestamp;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\BindParameterType;
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
}
