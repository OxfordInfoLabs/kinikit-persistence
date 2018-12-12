<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;

/**
 * Like filter - creates like clauses for each item
 *
 * Class ORMLikeFilter
 */
class LikeFilter extends Filter {


    /**
     * Return a suitable SQL clause for this filter value (either a single value or an array)
     *
     * @param $filterColumn
     * @param $databaseConnection DatabaseConnection
     * @return mixed
     */
    public function evaluateFilterClause($filterColumn, $databaseConnection) {

        if (is_array($this->filterValue)) {
            $newValues = array();
            $hasNull = false;
            foreach ($this->filterValue as $value) {
                if ($value == null) {
                    $hasNull = true;
                    $nullValue = " OR " . $filterColumn . " IS NULL";
                } else {
                    $filterValue = str_replace("*", "%", $value);
                    $newValues[] = $filterColumn . " LIKE '" . $databaseConnection->escapeString($filterValue) . "'";
                }
            }
            if (!$hasNull) {
                return "(" . join(" OR ", $newValues) . ")";
            } else {
                return "(" . join(" OR ", $newValues) . $nullValue . ")";
            }
        } else {
            if ($this->filterValue == null) {
                return $filterColumn . " IS NULL";
            } else {
                $filterValue = str_replace("*", "%", $this->filterValue);
                return $filterColumn . " LIKE '" . $databaseConnection->escapeString($filterValue) . "'";
            }

        }

    }
}