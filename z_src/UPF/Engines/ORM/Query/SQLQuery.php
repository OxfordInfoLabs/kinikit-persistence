<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query;

use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMNotEnoughQueryValuesException;

/**
 * ORM SQL Query object which allows for ? substitution parameters to be passed in the Query string
 * and then the values passed as additional parameters to the constructor.  These are automatically
 * escaped and quoted as required before execution by the framework.
 *
 * @author mark
 *
 */
class SQLQuery {

    private $sql;
    private $valueParams = array();

    /**
     * Construct with the parameterised SQL and then the values for each parameter as additional optional arguments
     *
     * @param String $sql
     */
    public function __construct($sql) {
        $this->sql = $sql;

        // Grab the args and shift off the first one to get the value params.
        $this->valueParams = func_get_args();
        array_shift($this->valueParams);

        if (sizeof($this->valueParams) > 0 && is_array($this->valueParams[0])) {
            $this->valueParams = $this->valueParams[0];
        }

    }

    /**
     * Get the expanded query string based on the constructed sql and value params.
     * Accept a database connection object to correctly escape the parameters as required.
     *
     * @param DatabaseConnection $databaseConnection
     *
     * @param $staticTableInfo
     * @return string
     * @throws ORMNotEnoughQueryValuesException
     */
    public function getExpandedQueryString($databaseConnection, $staticTableInfo) {

        // Split the string on ?'s
        $splitSQL = explode("?", $this->sql);

        // Throw an exception if not enough values were supplied to this query.
        if (sizeof($splitSQL) - 1 > sizeof($this->valueParams)) {
            throw new ORMNotEnoughQueryValuesException ($this->sql);
        }

        // Now expand out params and rebuild the query.
        $expandedQuery = $splitSQL [0];
        for ($i = 0; $i < sizeof($this->valueParams); $i++) {
            $escapedValue = $databaseConnection->escapeString($this->valueParams [$i]);
            $expandedQuery .= (is_numeric($escapedValue) ? $escapedValue : "'" . $escapedValue . "'");
            $expandedQuery .= $splitSQL [$i + 1];
        }

        if ($staticTableInfo) {
            foreach ($staticTableInfo->getFieldColumnNameMappings() as $field => $column) {
                $expandedQuery = preg_replace_callback("/(?<![\\S'])($field)(?![\\S'])/", function ($matches) use ($column) {
                    return $column;
                }, $expandedQuery);
            }
        }

        return $expandedQuery;

    }

    /**
     * @param String $sql
     */
    public function setSql($sql) {
        $this->sql = $sql;
    }

}

?>