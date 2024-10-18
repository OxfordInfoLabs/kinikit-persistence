<?php

namespace Kinikit\Persistence\ORM\Query\Filter;

class InFilter implements Filter {

    /**
     * @var string
     */
    private $member;

    /**
     * @var mixed
     */
    private $values;

    /**
     * @var boolean
     */
    private $negated;

    /**
     * @param string $member
     * @param array $values
     * @param boolean $negated
     */
    public function __construct($member, $values, $negated = false) {
        $this->member = $member;
        $this->values = $values;
        $this->negated = $negated;
    }


    /**
     * @return string
     */
    public function getSQLClause() {
        // $nullPos = array_search(null, $this->values);
        // ^ Unfortunately array_search(null, [0, null]) === 0

        $values = $this->values; // Make a copy of values
        $nullPos = false;
        foreach ($values as $key => $value) {
            if ($value === null) {
                $nullPos = $key;
            }
        }
        if (is_numeric($nullPos)) {
            array_splice($values, $nullPos, 1);
        }

        $sql = "";
        if (sizeof($values)) {
            $directive = $this->negated ? "NOT IN" : "IN";
            $sql = $this->member . " $directive (?" . str_repeat(",?", sizeof($values) - 1) . ")";
        }

        if (is_numeric($nullPos)) {
            $nullClause = $this->member . ($this->negated ? " IS NOT NULL" : " IS NULL");

            if ($sql)
                $sql = "(" . $sql . " " . ($this->negated ? "AND" : "OR") . " " . $nullClause . ")";
            else
                $sql = $nullClause;
        }

        return $sql;
    }

    public function getParameterValues() : array {
        // Ignore nulls because they're special cased in the SQL Clause
        return [...array_filter($this->values, fn($x) => $x !== null)];
    }
}