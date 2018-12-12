<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;

/**
 * Not equals filter
 */
class NotEqualsFilter extends Filter {

    /**
     * Return a suitable SQL clause for this filter value (either a single value or an array)
     *
     * @param $filterColumn
     * @param $databaseConnection DatabaseConnection
     * @return string
     */
    public function evaluateFilterClause($filterColumn, $databaseConnection) {

        if (is_array($this->filterValue)) {
            $newValues = array();
            $nullValue = "";
            $hasNull = false;
            foreach ($this->filterValue as $value) {
                if ($value == null) {
                    $hasNull = true;
                    $nullValue = " AND " . $filterColumn . " IS NOT NULL)";
                } else {
                    $newValues[] = "'" . $databaseConnection->escapeString($value) . "'";
                }

            }
            if (!$hasNull) {
                return $filterColumn . " NOT IN (" . join(",", $newValues) . ")";
            } else {
                return "(" . $filterColumn . " NOT IN (" . join(",", $newValues) . ")" . $nullValue;
            }

        } else {
            if ($this->filterValue == null) {
                return $filterColumn . " IS NOT NULL";
            } else {
                $filterValue = "'" . $databaseConnection->escapeString($this->filterValue) . "'";
                return $filterColumn . " <> " . $filterValue;
            }


        }

    }
}