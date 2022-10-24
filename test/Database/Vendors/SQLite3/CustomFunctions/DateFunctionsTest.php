<?php


namespace Kinikit\Persistence\Database\Vendors\SQLite3\CustomFunctions;

include_once "autoloader.php";

class DateFunctionsTest extends \PHPUnit\Framework\TestCase {

    public function testCanExtractDayFromDate() {
        $day = new Day();
        $this->assertEquals(1, $day->execute("2022-01-01"));
        $this->assertEquals(31, $day->execute("2022-05-31"));
        $this->assertEquals(1, $day->execute("2022-01-01 11:15:00"));
    }


    public function testCanExtractMonthFromDate() {
        $month = new Month();
        $this->assertEquals(1, $month->execute("2022-01-01"));
        $this->assertEquals(5, $month->execute("2022-05-31"));
        $this->assertEquals(1, $month->execute("2022-01-01 11:15:00"));
    }

    public function testCanExtractYearFromDate() {
        $year = new Year();
        $this->assertEquals(2022, $year->execute("2022-01-01"));
        $this->assertEquals(2018, $year->execute("2018-05-31"));
        $this->assertEquals(2022, $year->execute("2022-01-01 11:15:00"));
    }


    public function testCanGetCurrentDateUsingNowFunction() {
        $now = new Now();
        $this->assertEquals(date('Y-m-d H:i:s'), $now->execute());
    }

}