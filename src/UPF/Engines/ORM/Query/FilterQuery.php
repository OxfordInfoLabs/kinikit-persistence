<?php


namespace Kinikit\Persistence\UPF\Engines\ORM\Query;

use Kinikit\Core\Util\Logging\Logger;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\UPF\Engines\ORM\Query\Filters\EqualsFilter;
use Kinikit\Persistence\UPF\Engines\ORM\Query\Filters\Filter;
use Kinikit\Persistence\UPF\Framework\QueryResults;


/**
 * Subclass of ORMSQLQuery for automating the construction of a query from an array of
 *
 * Class ORMFilterQuery
 */
class FilterQuery extends SQLQuery implements QueryResults {

    private $filters = array();
    private $orderings = array();
    private $pageSize;
    private $page;
    private $filterLogic = FilterQuery::FILTER_AND;

    private $databaseConnection;

    const FILTER_AND = "AND";
    const FILTER_OR = "OR";

    /**
     * Constructor for optional creation of filter query
     */
    public function __construct($filters = array(), $orderings = array(), $pageSize = 10, $page = 1, $filterLogic = FilterQuery::FILTER_AND) {
        $this->filters = $filters ? $filters : array();
        $this->orderings = $orderings;
        $this->pageSize = $pageSize;
        $this->page = $page;
        $this->filterLogic = $filterLogic;
    }

    public function setFilterLogic($filterLogic) {
        $this->filterLogic = $filterLogic;
    }

    public function setFilters($filters) {
        $this->filters = $filters;
    }

    public function setPageSize($pageSize) {
        $this->pageSize = $pageSize;
    }

    public function setPage($page) {
        $this->page = $page;
    }

    public function setOrderings($orderings) {
        $this->orderings = $orderings;
    }


    /**
     * Overload the default method to return our expanded string
     *
     * @param DatabaseConnection $databaseConnection
     * @param $staticTableInfo
     * @return string
     */
    public function getExpandedQueryString($databaseConnection, $staticTableInfo) {
        $this->databaseConnection = $databaseConnection;

        $this->setSql($this->getQueryString($databaseConnection));
        return parent::getExpandedQueryString($databaseConnection, $staticTableInfo);

    }


    /**
     * @param $databaseConnection
     * @return string
     */
    private function getQueryString($databaseConnection, $filtersOnly = false) {
        $filterClauses = array();

        // Loop through each filter in turn
        foreach ($this->filters as $filterKey => $filterValue) {

            $filter = null;
            if (is_array($filterValue)) {
                $filter = new EqualsFilter($filterValue);
            } else if ($filterValue instanceof Filter) {
                $filter = $filterValue;
            } else {
                $filter = new EqualsFilter($filterValue);
            }

            if ($filter) {
                $filterClauses[] = $filter->evaluateAllFilterClauses($filterKey, $databaseConnection);
            }


        }

        $query = "";

        // If we have filter clauses add these now
        if (sizeof($filterClauses) > 0) {
            $query .= "WHERE " . join(" " . $this->filterLogic . " ", $filterClauses);
        }

        if (!$filtersOnly) {

            if (is_array($this->orderings) && sizeof($this->orderings) > 0) {
                $query .= " ORDER BY " . join(", ", $this->orderings);
            }

            if ($this->pageSize && $this->page) {

                if (is_numeric($this->page) && $this->pageSize > 0) {
                    $query .= " LIMIT " . $this->pageSize;
                }

                $offset = ((int)$this->page - 1) * (int)$this->pageSize;

                if ($offset >= 0) {
                    $query .= " OFFSET " . $offset;
                }

            }

        }

        return trim($query);
    }


    public function processResults($results, $persistenceCoordinator, $objectClass) {


        $countQuery = new FilterQuery($this->filters, array(), null);
        $count = $persistenceCoordinator->count($objectClass, $countQuery);

        return new FilterResults($results, $count, $this->pageSize, $this->page);
    }
}
