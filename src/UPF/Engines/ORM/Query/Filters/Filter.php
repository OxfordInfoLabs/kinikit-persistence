<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;
use Kinikit\Core\Object\SerialisableObject;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;

/**
 * Interface for all ORM Filters.
 *
 * Interface ORMFilter
 */
abstract class Filter extends SerialisableObject {

    protected $filterValue;
    protected $filterColumns;

    /**
     * Construct with value
     *
     * @param null $filterValue
     */
    public function __construct($filterValue = null, $filterColumns = null) {
        $this->filterValue = $filterValue;
        $this->filterColumns = $filterColumns;
    }

    /**
     * @param null $filterValue
     */
    public function setFilterValue($filterValue) {
        $this->filterValue = $filterValue;
    }

    /**
     * @return null
     */
    public function getFilterValue() {
        return $this->filterValue;
    }

    /**
     * @param mixed $filterColumns
     */
    public function setFilterColumns($filterColumns) {
        $this->filterColumns = $filterColumns;
    }

    /**
     * @return mixed
     */
    public function getFilterColumns() {
        return $this->filterColumns;
    }

    /**
     * Evaluate one or more filter clauses depending on whether an array of filter columns is present.
     * Otherwise, call the child function by convention.
     *
     * @param $filterKey
     * @param $databaseConnection DatabaseConnection
     */
    public function evaluateAllFilterClauses($filterKey, $databaseConnection) {

        if ($this->filterColumns) {
            if (!is_array($this->filterColumns)) {
                $this->filterColumns = array($this->filterColumns);
            }

            if (sizeof($this->filterColumns) == 1) {
                return $this->evaluateFilterClause($databaseConnection->escapeColumn($this->filterColumns[0]), $databaseConnection);
            } else {
                $clauses = array();
                foreach ($this->filterColumns as $column) {
                    $clauses[] =
                        $this->evaluateFilterClause($databaseConnection->escapeColumn($column), $databaseConnection);
                }
                return "(" . join(" OR ", $clauses) . ")";
            }

        } else {
            return $this->evaluateFilterClause($databaseConnection->escapeColumn($filterKey), $databaseConnection);
        }

    }


    /**
     * Return a suitable SQL clause for this filter value (either a single value or an array)
     *
     * @param $filterColumn
     * @param $databaseConnection BaseConnection
     * @internal param $filterValue
     * @return mixed
     */
    public abstract function evaluateFilterClause($filterColumn, $databaseConnection);


} 