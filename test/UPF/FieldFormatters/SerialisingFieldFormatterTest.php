<?php
namespace Kinikit\Persistence\UPF\FieldFormatters;

use Kinikit\Core\Util\Serialisation\JSON\ObjectToJSONConverter;
use Kinikit\Core\Util\Serialisation\XML\ObjectToXMLConverter;
use Kinikit\Persistence\UPF\Framework\ObjectWithId;

include_once "autoloader.php";


/**
 * Created by JetBrains PhpStorm.
 * User: Mark
 * Date: 21/05/13
 * Time: 10:24
 * To change this template use File | Settings | File Templates.
 */
class SerialisingFieldFormatterTest extends \PHPUnit\Framework\TestCase {

    public function testJSONStringsGetConvertedCorrectlyOnFormat() {

        $formatter = new SerialisingFieldFormatter();
        $formatter->setFormat(SerialisingFieldFormatter::FORMAT_JSON);

        $converter = new ObjectToJSONConverter();

        $sampleJSON = $converter->convert(new ObjectWithId("Bernard", 38, 15, 3));

        // Format sample JSON, check it returns correctly.
        $formatted = $formatter->format($sampleJSON);

        $this->assertEquals(array("name" => "Bernard", "age" => 38, "shoeSize" => 15, "id" => 3), $formatted);


    }


    public function testXMLStringsGetConvertedCorrectlyOnFormat() {
        $formatter = new SerialisingFieldFormatter();
        $formatter->setFormat(SerialisingFieldFormatter::FORMAT_XML);

        $converter = new ObjectToXMLConverter();

        $sampleXML = $converter->convert(new ObjectWithId("Bernard", 38, 15, 3));

        // Format sample JSON, check it returns correctly.
        $formatted = $formatter->format($sampleXML);

        $this->assertEquals(new ObjectWithId("Bernard", 38, 15, 3), $formatted);

    }


    public function testPHPSerialisedStringsGetConvertedCorrectlyOnFormat() {
        $formatter = new SerialisingFieldFormatter();
        $formatter->setFormat(SerialisingFieldFormatter::FORMAT_PHP);

        $samplePHP = serialize(new ObjectWithId("Bernard", 38, 15, 3));

        // Format sample JSON, check it returns correctly.
        $formatted = $formatter->format($samplePHP);

        $this->assertEquals(new ObjectWithId("Bernard", 38, 15, 3), $formatted);

    }


    public function testJSONObjectsGetConvertedCorrectlyOnUnformat() {

        $formatter = new SerialisingFieldFormatter();
        $formatter->setFormat(SerialisingFieldFormatter::FORMAT_JSON);

        $object = new ObjectWithId("Monkey", 55, 12, 9);

        $unformatted = $formatter->unformat($object);

        $converter = new ObjectToJSONConverter();

        $this->assertEquals($converter->convert($object), $unformatted);


    }


    public function testXMLObjectsGetConvertedCorrectlyOnUnformat() {

        $formatter = new SerialisingFieldFormatter();
        $formatter->setFormat(SerialisingFieldFormatter::FORMAT_XML);

        $object = new ObjectWithId("Monkey", 55, 12, 9);

        $unformatted = $formatter->unformat($object);

        $converter = new ObjectToXMLConverter();

        $this->assertEquals($converter->convert($object), $unformatted);


    }


    public function testPHPSerialisedObjectsGetConvertedCorrectlyOnUnformat() {

        $formatter = new SerialisingFieldFormatter();
        $formatter->setFormat(SerialisingFieldFormatter::FORMAT_PHP);

        $object = new ObjectWithId("Monkey", 55, 12, 9);

        $unformatted = $formatter->unformat($object);

        $this->assertEquals(serialize($object), $unformatted);


    }


}
