<?php

namespace Kinikit\Persistence\UPF\Engines\ObjectIndex;

/**
 * Query object used for passing to the object index
 */
class ObjectIndexQuery {

    private $filters = array();
    private $orderings = array();
    private $limit = null;
    private $offset = null;

    /**
     * Object index query, accepts a query string which is in OQL format.
     *
     * @param null $queryString
     */
    public function __construct($queryString = null) {
        if ($queryString) {
            $this->parseQueryObjects($queryString);
        }
    }


    /**
     * Return the where clause according to the object specification
     */
    public function getLogicClause($objectClass) {

        $logicClause = "";


        // If limit, offset, filters or orderings construct an inner clause
        if ($this->limit || $this->offset || $this->filters || $this->orderings) {


            // Check for filters
            if ($this->filters) {

            }


            if ($this->limit) {
                $logicClause .= " LIMIT " . $this->limit;
            }

            if ($this->offset) {
                if (!$this->limit) $logicClause .= " LIMIT 1000000";
                $logicClause .= " OFFSET " . $this->offset;
            }

        }


        return $logicClause;
    }


    /**
     * Parse query objects from query string
     */
    private function parseQueryObjects($queryString) {

        preg_match("/LIMIT\W+([0-9]+)/", $queryString, $matches);
        if (sizeof($matches) > 1) {
            $this->limit = $matches[1];
        }

        $queryString = preg_replace("/LIMIT\W+[0-9]+/", "", $queryString);


        preg_match("/OFFSET\W+([0-9]+)/", $queryString, $matches);
        if (sizeof($matches) > 1) {
            $this->offset = $matches[1];
        }

        $queryString = preg_replace("/OFFSET\W+[0-9]+/", "", $queryString);



    }


}