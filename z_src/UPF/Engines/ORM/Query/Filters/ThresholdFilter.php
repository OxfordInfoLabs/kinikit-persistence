<?php


namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;


use Kinikit\Persistence\Database\Connection\DatabaseConnection;

class ThresholdFilter extends Filter {

    private $filterMode;
    private $filterDateFormat;

    const GREATER_THAN = "gt";
    const GREATER_THAN_OR_EQUAL = "gte";
    const LESS_THAN = "lt";
    const LESS_THAN_OR_EQUAL = "lte";

    public function __construct($filterValue = null, $filterColumns = null, $filterMode = null, $filterDateFormat = null) {
        parent::__construct($filterValue, $filterColumns);
        $this->filterMode = $filterMode;
        $this->filterDateFormat = $filterDateFormat;
    }

    /**
     * Return a suitable SQL clause for this filter value (either a single value or an array)
     *
     * @param $filterColumn
     * @param $databaseConnection DatabaseConnection
     * @internal param $filterValue
     * @return mixed
     */
    public function evaluateFilterClause($filterColumn, $databaseConnection) {

        $filterValue = $this->getFilterValue();

        // If we have filter date format defined, utilise it.
        if ($this->filterDateFormat) {
            $dateObj = date_create_from_format($this->filterDateFormat, $filterValue);
            if ($dateObj) $filterValue = $dateObj->format("Y-m-d");
        }

        $filterValue = is_numeric($filterValue) ? $filterValue : "'" . $filterValue . "'";

        switch ($this->filterMode) {
            case ThresholdFilter::GREATER_THAN:
                return $filterColumn . " > " . $filterValue;
                break;
            case ThresholdFilter::GREATER_THAN_OR_EQUAL:
                return $filterColumn . " >= " . $filterValue;
                break;
            case ThresholdFilter::LESS_THAN:
                return $filterColumn . " < " . $filterValue;
                break;
            case ThresholdFilter::LESS_THAN_OR_EQUAL:
                return $filterColumn . " <= " . $filterValue;
                break;

        }

    }


    /**
     * @param null $mode
     */
    public function setFilterMode($mode) {
        $this->filterMode = $mode;
    }

    /**
     * @return null
     */
    public function getFilterMode() {
        return $this->filterMode;
    }

    /**
     * @param mixed $filterDateFormat
     */
    public function setFilterDateFormat($filterDateFormat) {
        $this->filterDateFormat = $filterDateFormat;
    }

    /**
     * @return mixed
     */
    public function getFilterDateFormat() {
        return $this->filterDateFormat;
    }


}