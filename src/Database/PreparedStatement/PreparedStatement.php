<?php

namespace Kinikit\Persistence\Database\PreparedStatement;

/**
 * Database agnostic prepared statement
 *
 */
interface PreparedStatement {


    /**
     * Return the source statement SQL for this statement.
     *
     * @return string
     */
    public function getStatementSQL();


    /**
     * Execute this statement for an array of parameter values matching ? in SQL.
     *
     * @param mixed[] $parameterValues
     * @return mixed
     */
    public function execute($parameterValues);


    /**
     * Close this statement and free resources.
     */
    public function close();

}




