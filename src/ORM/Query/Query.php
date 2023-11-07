<?php

namespace Kinikit\Persistence\ORM\Query;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Util\Primitive;
use Kinikit\Persistence\ORM\ORM;
use Kinikit\Persistence\ORM\Query\Filter\EqualsFilter;
use Kinikit\Persistence\ORM\Query\Filter\InFilter;
use Kinikit\Persistence\ORM\Query\Filter\LikeFilter;
use PHPUnit\Util\Filter;

class Query {

    /**
     * @var string
     */
    private $className;

    /**
     * @param string $className
     * @param ORM $orm
     */
    public function __construct($className, $orm = null) {
        $this->className = $className;
        $this->orm = $orm ?? Container::instance()->get(ORM::class);
    }


    /**
     * Perform straight query with filters, optionally paged using limit and offset
     *
     * @param mixed $filters
     * @param mixed $orderings
     * @param integer $limit
     * @param integer $offset
     *
     * @return array
     */
    public function query($filters = [], $orderings = [], $limit = null, $offset = null) {

        // Grab filter data
        list($clauses, $params) = $this->generateFilterData($filters);

        $whereClause = "WHERE " . join(" AND ", $clauses);

        if ($orderings) {
            if (is_string($orderings)) $orderings = [$orderings];
            $whereClause .= " ORDER BY " . join(", ", $orderings);
        }

        if ($limit) {
            $whereClause .= " LIMIT ?";
            $params[] = $limit;
        }
        if ($offset) {
            $whereClause .= " OFFSET ?";
            $params[] = $offset;
        }


        return $this->orm->filter($this->className, $whereClause, $params);

    }


    /**
     * Summarise
     *
     * @param string $fieldName
     * @param mixed[] $filters
     * @param string $summariseExpression
     * @return SummarisedValue[]
     */
    public function summariseByMember($memberName, $filters = [], $summariseExpression = "COUNT(*)") {

        // Grab filter data
        list($clauses, $params) = $this->generateFilterData($filters);

        $matches = $this->orm->values($this->className, [$memberName, $summariseExpression], "WHERE " . join(" AND ", $clauses) . " GROUP BY $memberName", $params);

        return array_map(function ($item) use ($memberName, $summariseExpression) {
            return new SummarisedValue($item[$memberName] ?? null, $item[$summariseExpression] ?? null);
        }, $matches);

    }


    /**
     * @param $filters
     * @return array
     */
    private function generateFilterData($filters) {

        if (!is_array($filters)) $filters = [$filters];

        $clauses = [];
        $params = [];
        foreach ($filters as $key => $filter) {

            // Simplify key-value filters
            if (is_array($filter)) {
                $filter = new InFilter($key, $filter);
            } else if (Primitive::isPrimitive($filter)) {

                // Handle like wildcards
                $filter = str_contains($filter, "*") ? str_replace("*", "%", $filter) : $filter;
                $filter = str_contains($filter, "%") ? new LikeFilter($key, $filter) : new EqualsFilter($key, $filter);
            }

            $clauses[] = $filter->getSQLClause();
            $params = array_merge($params, $filter->getParameterValues() ?? []);
        }
        return array($clauses, $params);
    }


}