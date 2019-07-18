<?php


namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;


class EqualsFilter extends Filter {


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
            $nullValue = "";
            $hasNull = false;
            foreach ($this->filterValue as $value) {
                if ($value == null) {
                    $hasNull = true;
                    $nullValue = " OR " . $filterColumn . " IS NULL)";
                } else {
                    $newValues[] = "'" . $databaseConnection->escapeString($value) . "'";
                }

            }
            if (!$hasNull) {
                return $filterColumn . " IN (" . join(",", $newValues) . ")";
            } else {
                return "(" . $filterColumn . " IN (" . join(",", $newValues) . ")" . $nullValue;
            }

        } else {
            if ($this->filterValue == null) {
                return $filterColumn . " IS NULL";
            } else {
                $filterValue = "'" . $databaseConnection->escapeString($this->filterValue) . "'";
                return $filterColumn . "=" . $filterValue;
            }


        }
    }

}