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
    public function getStatementSQL(): string;


    /**
     * Execute this statement for an array of parameter values matching ? in SQL.
     *
     * @param BindParameter[] $parameterValues
     * @return void
     */
    public function execute(array $parameterValues): void;


    /**
     * Close this statement and free resources.
     */
    public function close(): void;

}




