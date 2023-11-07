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
        $directive = $this->negated ? "NOT IN" : "IN";
        return $this->member . " $directive (?" . str_repeat(",?", sizeof($this->values) - 1) . ")";
    }

    /**
     * @return array
     */
    public function getParameterValues() {
        return $this->values;
    }
}