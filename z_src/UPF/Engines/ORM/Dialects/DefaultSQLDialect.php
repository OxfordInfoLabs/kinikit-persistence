<?php


namespace Kinikit\Persistence\UPF\Engines\ORM\Dialects;


/**
 * Default implementation of the SQL dialect
 *
 * @author mark
 *
 */
class DefaultSQLDialect implements ORMSQLDialect {

    /*
     * Generate all column select clause
     */
    public function generateAllColumnSelectClause($ormTableInfo) {

        return "SELECT *";

    }

    /**
     * Generate a distinct clase for a set of columns
     *
     * @param $columns
     * @return mixed
     */
    public function generateDistinctClause($columns) {
        return "DISTINCT " . join(",", $columns);
    }
}

?>