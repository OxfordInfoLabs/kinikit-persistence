<?php

namespace Kinikit\Persistence\ORM\Query;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Util\Primitive;
use Kinikit\Persistence\ORM\ORM;
use Kinikit\Persistence\ORM\Query\Filter\EqualsFilter;
use Kinikit\Persistence\ORM\Query\Filter\InFilter;
use Kinikit\Persistence\ORM\Query\Filter\LikeFilter;

class Query {

    /**
     * @var string
     */
    private $className;
    /**
     * @var ORM
     */
    private $orm;

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

        $whereClause = sizeof($clauses) ? "WHERE " . join(" AND ", $clauses) : "";

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
    public function summariseByMember($memberName, $filters = [], $summariseExpression = "COUNT(DISTINCT(id))") {

        // Grab filter data
        list($clauses, $params) = $this->generateFilterData($filters);

        $query = sizeof($clauses) ? "WHERE " . join(" AND ", $clauses) : "";

        $matches = $this->orm->values($this->className, [$memberName, $summariseExpression], $query . " GROUP BY $memberName HAVING $memberName IS NOT NULL", $params);

        return array_map(function ($item) use ($memberName, $summariseExpression) {
            $values = array_values($item);
            return new SummarisedValue($values[0] ?? null, $values[1] ?? null);
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
            if (is_array($filter) && sizeof($filter)) {
                $filter = new InFilter($key, $filter);
            } else if (Primitive::isPrimitive($filter)) {

                // Handle like wildcards
                $filter = str_contains($filter, "*") ? str_replace("*", "%", $filter) : $filter;
                $filter = str_contains($filter, "%") ? new LikeFilter($key, $filter) : new EqualsFilter($key, $filter);
            }

            // If a filter proceed
            if ($filter) {
                $clauses[] = $filter->getSQLClause();
                $params = array_merge($params, $filter->getParameterValues() ?? []);
            }
        }
        return array($clauses, $params);
    }


}