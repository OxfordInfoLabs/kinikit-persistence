<?php


namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;

/**
 * Not null filter
 *
 * Class ORMNotNullFilter
 */
class NotNullFilter extends Filter {

    /**
     * Return a suitable SQL clause for this filter value (either a single value or an array)
     *
     * @param $filterColumn
     * @param $databaseConnection DatabaseConnection
     * @internal param $filterValue
     * @return mixed
     */
    public function evaluateFilterClause($filterColumn, $databaseConnection) {
        return $filterColumn . " IS NOT NULL";
    }
}