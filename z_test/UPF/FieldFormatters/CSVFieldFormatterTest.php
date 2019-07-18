<?php


namespace Kinikit\Persistence\UPF\FieldFormatters;

include_once "autoloader.php";


class CSVFieldFormatterTest extends \PHPUnit\Framework\TestCase {


    public function testCanFormatCSVStringValueToArrayOfValuesWithDefaultFormatterConfig() {

        $csvFormatter = new CSVFieldFormatter();
        $formatted = $csvFormatter->format("mark,john,dave,pete");

        $this->assertEquals(array("mark", "john", "dave", "pete"), $formatted);

    }


    public function testCanFormatCSVStringValueWithCustomDelimiter() {

        $csvFormatter = new CSVFieldFormatter();
        $csvFormatter->setDelimiter("\t");

        $formatted = $csvFormatter->format("1\t2\t3\t4");

        $this->assertEquals(array("1", "2", "3", "4"), $formatted);

        $csvFormatter->setDelimiter("||");

        $formatted = $csvFormatter->format("james||andy||dave||mike");

        $this->assertEquals(array("james", "andy", "dave", "mike"), $formatted);
    }


    public function testCanFormatCSVStringValueWithKeyValues() {

        $csvFormatter = new CSVFieldFormatter();
        $csvFormatter->setIsKeyValue(true);

        $formatted = $csvFormatter->format("john:1,mark:2,dave:3,bob:4");
        $this->assertEquals(array("john" => 1, "mark" => 2, "dave" => 3, "bob" => 4), $formatted);

    }


    public function testCanUnformatArrayUsingDefaultFormatter() {

        $csvFormatter = new CSVFieldFormatter();

        $unformatted = $csvFormatter->unformat(array("mark", "luke", "john"));
        $this->assertEquals("mark,luke,john", $unformatted);

        $unformatted = $csvFormatter->unformat(array("mark" => 1, "luke" => 2, "john" => 3));
        $this->assertEquals("1,2,3", $unformatted);

    }

    public function testCanUnformatArrayUsingCustomDelimiter() {
        $csvFormatter = new CSVFieldFormatter();
        $csvFormatter->setDelimiter("\t");

        $unformatted = $csvFormatter->unformat(array("mark", "luke", "john"));
        $this->assertEquals("mark\tluke\tjohn", $unformatted);

        $csvFormatter->setDelimiter("||");

        $unformatted = $csvFormatter->unformat(array("mark", "luke", "john"));
        $this->assertEquals("mark||luke||john", $unformatted);

    }


    public function testCanUnformatArrayToKVPString() {

        $csvFormatter = new CSVFieldFormatter();
        $csvFormatter->setIsKeyValue(true);

        $unformatted = $csvFormatter->unformat(array("mark" => 1, "luke" => 3, "john" => 5));
        $this->assertEquals("mark:1,luke:3,john:5", $unformatted);

    }


} 