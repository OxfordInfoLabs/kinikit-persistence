<?php


namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;

use Kinikit\Persistence\Database\Connection\DatabaseConnection;

class FullTextFilter extends Filter {


    /**
     * Construct filter with value and set of columns optionally.
     *
     * @param null $filterValue
     * @param array $matchColumns
     */
    public function __construct($filterValue = null, $filterColumns = array()) {
        parent::__construct($filterValue, $filterColumns);
    }

    /**
     * Override the evaluate all as the logic below handles this anyway.
     *
     * @param $filterKey
     * @param $databaseConnection
     * @return mixed|string|void
     */
    public function evaluateAllFilterClauses($filterKey, $databaseConnection) {
        return $this->evaluateFilterClause($filterKey, $databaseConnection);
    }


    /**
     * Return a suitable SQL clause for this filter value (either a single value or an array)
     *
     * @param $filterColumn
     * @param $databaseConnection DatabaseConnection
     * @return mixed
     */
    public function evaluateFilterClause($filterColumn, $databaseConnection) {
        if (sizeof($this->getFilterColumns()) > 0) {
            return "MATCH (" . join(", ", $this->getFilterColumns()) . ") AGAINST " . "('" . $databaseConnection->escapeString($this->filterValue) . "' IN BOOLEAN MODE)";
        } else {
            return "MATCH (" . $filterColumn . ") AGAINST (" . "'" . $databaseConnection->escapeString($this->filterValue) . "' IN BOOLEAN MODE)";
        }

    }

} 