<?php


namespace Kinikit\Persistence\Database\PreparedStatement;


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
