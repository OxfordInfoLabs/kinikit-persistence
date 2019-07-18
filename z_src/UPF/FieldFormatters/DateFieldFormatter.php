<?php

namespace Kinikit\Persistence\UPF\FieldFormatters;
use Kinikit\Persistence\UPF\Framework\ObjectFieldFormatter;

class DateFieldFormatter extends ObjectFieldFormatter {

    private $sourceFormat;
    private $targetFormat;
    private $timezone;

    const FORMAT_SQL_DATE = "Y-m-d";
    const FORMAT_SQL_DATE_TIME_MINS = "Y-m-d H:i";
    const FORMAT_SQL_FULL_DATE_TIME = "Y-m-d H:i:s";

    private $defaultFormats = array(DateFieldFormatter::FORMAT_SQL_DATE, DateFieldFormatter::FORMAT_SQL_DATE_TIME_MINS, DateFieldFormatter::FORMAT_SQL_FULL_DATE_TIME);

    /**
     * Optional constructor for testing
     *
     * @param string $targetFormat
     * @param string $sourceFormat
     */
    public function __construct($identifier = null, $targetFormat = "d/m/Y", $sourceFormat = null, $timezone = "Europe/London") {
        parent::__construct($identifier);
        $this->targetFormat = $targetFormat;
        $this->sourceFormat = $sourceFormat;
        $this->setTimezone($timezone);
    }

    /**
     * @return the $sourceFormat
     */
    public function getSourceFormat() {
        return $this->sourceFormat;
    }

    /**
     * @return the $targetFormat
     */
    public function getTargetFormat() {
        return $this->targetFormat;
    }

    /**
     * @param field_type $sourceFormat
     */
    public function setSourceFormat($sourceFormat) {
        $this->sourceFormat = $sourceFormat;
    }

    /**
     * @param field_type $targetFormat
     */
    public function setTargetFormat($targetFormat) {
        $this->targetFormat = $targetFormat;
    }

    /**
     * @return the $timezone
     */
    public function getTimezone() {
        return $this->timezone;
    }

    /**
     * @param field_type $timezone
     */
    public function setTimezone($timezone) {
        $this->timezone = $timezone;
        date_default_timezone_set($timezone);
    }

    /*
     * Format the date field value
     *
     * @see ObjectFieldFormatter::format()
     */
    public function format($unformattedValue) {


        if ($unformattedValue && substr($unformattedValue, 0, 5) != "0000-") {

            // If we have a source format, use it.
            if ($this->sourceFormat) {
                $dateObject = date_create_from_format($this->sourceFormat, $unformattedValue);
            } // Otherwise, try some standard ones...
            else {
                foreach ($this->defaultFormats as $format) {
                    if ($dateObject = date_create_from_format($format, $unformattedValue))
                        break;
                }
            }

            return $dateObject ? $dateObject->format($this->targetFormat) : $unformattedValue;
        } else {
            return null;
        }

    }

    /*
     * Unformat the date field value
     *
     *  @see ObjectFieldFormatter::unformat()
     */
    public function unformat($formattedValue) {

        if ($formattedValue && substr($formattedValue, 0, 5) != "0000-") {

            $dateObject = date_create_from_format($this->targetFormat, $formattedValue);

            // Decide the source format to convert back to depending on what has been configured.
            $sourceFormat = $this->sourceFormat ? $this->sourceFormat : DateFieldFormatter::FORMAT_SQL_DATE;

            return $dateObject ? $dateObject->format($sourceFormat) : $formattedValue;
        } else {
            return null;
        }

    }

}

?>