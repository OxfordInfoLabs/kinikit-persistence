<?php

namespace Kinikit\Persistence\Database\Connection;

/**
 * Database independent wrapper for a prepared statement.
 * Essentially this is constructed initially with an SQL statement with substitution params marked using ?
 * Subsequent calls to bindParameter are then made, declaring both the type and the value of the parameter to bind.
 *
 */
class PreparedStatement {

    private $sql = null;
    private $bindParams = array();

    public function __construct($sql = null) {
        $this->sql = $sql;
    }

    /**
     * Add a bind parameter (necessarily one per question mark)
     *
     * @param integer $sqlType
     * @param mixed $value
     */
    public function addBindParameter($sqlType, $value) {
        $this->bindParams [] = new BindParameter ($sqlType, $value);
    }

    /**
     * clear all currently set bind parameters
     */
    public function clearBindParameters() {
        $this->bindParams = [];
    }

    /**
     * Set the SQL string in use.
     *
     * @param string $sql
     */
    public function setSQL($sql) {
        $this->sql = $sql;
    }

    /**
     * Get the SQL
     *
     * @return string
     */
    public function getSQL() {
        return $this->sql;
    }

    /**
     * Get the bind parameters
     *
     * @return array
     */
    public function getBindParameters() {
        return $this->bindParams;
    }


    /**
     * Return Explicit SQL with ? replaced by literal parameters - useful for logging purposes.
     */
    public function getExplicitSQL() {
        $explicitSQL = $this->sql;
        foreach ($this->bindParams as $bindParam) {

            $logValue = $bindParam->getValue() instanceof BlobWrapper ? "[[BLOB]]" : (is_numeric($bindParam->getValue()) ? $bindParam->getValue() : "'" . $bindParam->getValue() . "'");

            $explicitSQL =
                preg_replace("/\?/", $logValue, $explicitSQL, 1);
        }

        return $explicitSQL;
    }

}

/**
 * Bind parameter class
 *
 */
class BindParameter {

    private $sqlType;
    private $value;

    public function __construct($sqlType, $value) {
        $this->sqlType = $sqlType;
        $this->value = $value;
    }

    public function getSqlType() {
        return $this->sqlType;
    }

    public function getValue() {
        return $this->value;
    }

}

?>