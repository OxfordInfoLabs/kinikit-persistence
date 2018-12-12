<?php

namespace Kinikit\Persistence\UPF\FieldFormatters;

use Kinikit\Core\Util\Serialisation\JSON\JSONToObjectConverter;
use Kinikit\Core\Util\Serialisation\JSON\ObjectToJSONConverter;
use Kinikit\Core\Util\Serialisation\PHP\ObjectToPHPSerialConverter;
use Kinikit\Core\Util\Serialisation\PHP\PHPSerialToObjectConverter;
use Kinikit\Core\Util\Serialisation\XML\ObjectToXMLConverter;
use Kinikit\Core\Util\Serialisation\XML\XMLToObjectConverter;
use Kinikit\Persistence\UPF\Framework\ObjectFieldFormatter;


/**
 * Generic UPF formatter useful for converting objects to serialisable form for persistence when no
 * relational behaviour is required for the persisted object.
 */
class SerialisingFieldFormatter extends ObjectFieldFormatter {

    private $format;
    private $objectToFormatConverter;
    private $formatToObjectConverter;


    const FORMAT_JSON = "json";
    const FORMAT_XML = "xml";
    const FORMAT_PHP = "php";


    /**
     * @param mixed $format
     */
    public function setFormat($format) {
        $this->format = $format;
    }

    /**
     * @return mixed
     */
    public function getFormat() {
        return $this->format;
    }


    /**
     * Format a value returned from an in use engine following a query
     *
     * @param mixed $sourceValue
     */
    public function format($unformattedValue) {

        if ($unformattedValue != null)
            return $this->getFormatToObjectConverter()->convert($unformattedValue);
        else
            return null;
    }

    /**
     * Unformat an object value into engine format before saving / removing the object.
     *
     * @param mixed $objectValue
     */
    public function unformat($formattedValue) {


        if ($formattedValue != null) {
            return $this->getObjectToFormatConverter()->convert($formattedValue);
        } else {
            return null;
        }
    }


    // Get a converter for format to object use
    // @return FormatToObjectConverter
    private function getFormatToObjectConverter() {
        if (!$this->formatToObjectConverter) {
            if ($this->format == self::FORMAT_JSON) {
                $this->formatToObjectConverter = new JSONToObjectConverter();
            } else if ($this->format == self::FORMAT_PHP) {
                $this->formatToObjectConverter = new PHPSerialToObjectConverter();
            } else {
                $this->formatToObjectConverter = new XMLToObjectConverter();
            }

        }

        return $this->formatToObjectConverter;
    }

    // Get a converter for object to format use
    // @return ObjectToFormatConverter
    private function getObjectToFormatConverter() {
        if (!$this->objectToFormatConverter) {
            if ($this->format == self::FORMAT_JSON) {
                $this->objectToFormatConverter = new ObjectToJSONConverter();
            } else if ($this->format == self::FORMAT_PHP) {
                $this->objectToFormatConverter = new ObjectToPHPSerialConverter();
            } else {
                $this->objectToFormatConverter = new ObjectToXMLConverter();
            }
        }

        return $this->objectToFormatConverter;
    }


}