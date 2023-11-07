<?php

namespace Kinikit\Persistence\ORM\Query\Filter;

class EqualsFilter implements Filter {

    /**
     * @var string
     */
    private $member;

    /**
     * @var mixed
     */
    private $value;


    /**
     * @var boolean
     */
    private $negated;

    /**
     * @param string $member
     * @param mixed $value
     * @param boolean $negated
     */
    public function __construct($member, $value, $negated = false) {
        $this->member = $member;
        $this->value = $value;
        $this->negated = $negated;
    }


    /**
     * @return string
     */
    public function getSQLClause() {
        return $this->member . ($this->negated ? " <> ?" : " = ?");
    }

    /**
     * @return array
     */
    public function getParameterValues() {
        return [$this->value];
    }
}