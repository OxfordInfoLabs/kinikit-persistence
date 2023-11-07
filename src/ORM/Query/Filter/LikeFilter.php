<?php

namespace Kinikit\Persistence\ORM\Query\Filter;

class LikeFilter implements Filter {

    /**
     * @var mixed
     */
    private $members;


    /**
     * @var string
     */
    private $value;

    /**
     * @param mixed $members
     * @param string $value
     */
    public function __construct($members, $value) {
        $this->members = is_array($members) ? $members : [$members];
        $this->value = $value;
    }


    /**
     * @return string
     */
    public function getSQLClause() {
        if (sizeof($this->members) == 1) {
            return $this->members[0] . " LIKE ?";
        } else {
            $clauses = [];
            foreach ($this->members as $member) {
                $clauses[] = "IFNULL($member,'')";
            }
            return "CONCAT(" . join(",", $clauses) . ") LIKE ?";
        }
    }

    /**
     * @return array
     */
    public function getParameterValues() {
        return [$this->value];
    }
}