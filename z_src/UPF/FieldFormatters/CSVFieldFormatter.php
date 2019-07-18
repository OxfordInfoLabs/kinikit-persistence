<?php

namespace Kinikit\Persistence\UPF\FieldFormatters;
use Kinikit\Persistence\UPF\Framework\ObjectFieldFormatter;

/**
 * Formatter object for converting values from CSV string format to an array of values
 * and vice versa.
 *
 * Class CSVFormatter
 */
class CSVFieldFormatter extends ObjectFieldFormatter {


    private $delimiter;
    private $isKeyValue;

    /**
     * @param mixed $delimiter
     */
    public function setDelimiter($delimiter) {
        $this->delimiter = $delimiter;
    }

    /**
     * @return mixed
     */
    public function getDelimiter() {
        return $this->delimiter;
    }

    /**
     * @param mixed $isKeyValue
     */
    public function setIsKeyValue($isKeyValue) {
        $this->isKeyValue = $isKeyValue;
    }

    /**
     * @return mixed
     */
    public function getIsKeyValue() {
        return $this->isKeyValue;
    }


    /**
     * Format a value returned from an in use engine following a query
     *
     * @param mixed $sourceValue
     */
    public function format($unformattedValue) {

        $delimiter = $this->delimiter ? $this->delimiter : ",";

        if (!trim($unformattedValue))
            return array();

        $valuesArray = explode($delimiter, $unformattedValue);

        if ($this->isKeyValue) {

            $kvpValues = array();
            foreach ($valuesArray as $entry) {
                $explodedEntry = explode(":", $entry);
                $kvpValues[$explodedEntry[0]] = $explodedEntry[1];
            }

            return $kvpValues;

        } else {
            return $valuesArray;
        }


    }

    /**
     * Unformat an object value into engine format before saving / removing the object.
     *
     * @param mixed $objectValue
     */
    public function unformat($formattedValue) {

        $delimiter = $this->delimiter ? $this->delimiter : ",";

        if ($this->isKeyValue) {
            $kvpArray = array();
            foreach ($formattedValue as $key => $value) {
                $kvpArray[] = $key . ":" . $value;
            }
            return implode($delimiter, $kvpArray);
        } else {
            return implode($delimiter, $formattedValue);
        }
    }
}