<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;


/**
 * ORM filter which creates a logical junction of one or more source filters
 */
class CompoundFilter extends Filter {

    private $filters;
    private $logic;

    const LOGIC_AND = "AND";
    const LOGIC_OR = "OR";


    /**
     * Compound filter - operates on an array of filters with a logic.
     *
     * @param array $filters
     * @param string $logic
     */
    public function __construct($filters = array(), $logic = CompoundFilter::LOGIC_AND) {
        $this->filters = $filters;
        $this->logic = $logic;
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


        $filters = array();
        foreach ($this->filters as $filter) {
            $filters[] = $filter->evaluateAllFilterClauses(null, $databaseConnection);
        }

        return "(" . join(") " . $this->logic . " (", $filters) . ")";
    }
}