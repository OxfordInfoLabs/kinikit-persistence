<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Dialects;

/**
 * Defining interface for the ORM SQL Dialect type.
 *
 * @author mark
 *
 */
interface ORMSQLDialect {

    /**
     * Generate an all column select clause from ORM Table info for the implemented dialect.
     *
     * @param ORMTableInfo $ormTableInfo
     */
    public function generateAllColumnSelectClause($ormTableInfo);


    /**
     * Generate a distinct clase for a set of columns
     *
     * @param $columns
     * @return mixed
     */
    public function generateDistinctClause($columns);

}

?>