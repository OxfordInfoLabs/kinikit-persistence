<?php


namespace Kinikit\Persistence\Database\PreparedStatement;


abstract class BasePreparedStatement implements PreparedStatement {


    /**
     * @var string
     */
    private $sql;

    /**
     * Construct with the SQL statement
     *
     * BasePreparedStatement constructor.
     * @param string $sql
     */
    public function __construct($sql) {
        $this->sql = $sql;
    }

    /**
     * Return the source statement SQL for this statement.
     *
     * @return string
     */
    public function getStatementSQL() {
        return $this->sql;
    }


}
