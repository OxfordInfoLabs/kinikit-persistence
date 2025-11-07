<?php

namespace Kinikit\Persistence\ORM\Query\Filter;

class FilterJunction implements Filter {

    const LOGIC_AND = "AND";
    const LOGIC_OR = "OR";

    /**
     * @param Filter[] $filters
     * @param string $logic
     */
    public function __construct(private array $filters = [], private string $logic = self::LOGIC_AND) {
    }


    public function getSQLClause() {

        $logic = $this->logic == self::LOGIC_OR ? self::LOGIC_OR : self::LOGIC_AND;
        $sqlClauses = array_merge(array_map(fn($filter) => '(' . $filter->getSQLClause() . ')', $this->filters));
        return join(" {$logic} ", $sqlClauses);

    }


    public function getParameterValues() {
        return array_merge(...array_map(fn($filter) => $filter->getParameterValues(), $this->filters));
    }
}