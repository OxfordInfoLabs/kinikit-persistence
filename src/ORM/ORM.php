<?php

namespace Kinikit\Persistence\ORM;

use Kinikit\Core\Exception\ItemNotFoundException;
use Kinikit\Persistence\ORM\Exception\ObjectNotFoundException;
use Kinikit\Persistence\ORM\Mapping\ORMMapping;
use Kinikit\Persistence\TableMapper\Exception\PrimaryKeyRowNotFoundException;
use Kinikit\Persistence\TableMapper\Mapper\TableMapper;

/**
 * @noProxy
 *
 * Class ORM
 * @package Kinikit\Persistence\ORM
 */
class ORM {

    /**
     * @var TableMapper
     */
    private $tableMapper;

    /**
     * @var ORMMapping[string]
     */
    private $ormMappings = [];


    /**
     * ORM constructor.  Auto inject table mapper instance.
     *
     * @param TableMapper $tableMapper
     */
    public function __construct($tableMapper) {
        $this->tableMapper = $tableMapper;
    }


    /**
     * Fetch a single object by primary key of the specified class.  The primary key is either a single value
     * or an array of values if a compound primary key.
     *
     * @param string $className
     * @param mixed $primaryKeyValue
     * @return mixed
     */
    public function fetch($className, $primaryKeyValue) {
        $mapping = $this->getORMMapping($className);
        try {
            $results = $this->tableMapper->fetch($mapping->getTableMapping(), $primaryKeyValue);
            return $mapping->mapRowsToObjects($results, true);
        } catch (PrimaryKeyRowNotFoundException $e) {
            throw new ObjectNotFoundException($className, $primaryKeyValue);
        }
    }


    /**
     * Fetch multiple objects by primary key of the specified class.  The primary keys are supplied as an array
     * which can be single values or arrays of values if a compound primary key.
     *
     * @param string $className
     * @param mixed $primaryKeyValues
     * @param bool $ignoreMissingObjects
     */
    public function multiFetch($className, $primaryKeyValues, $ignoreMissingObjects = false) {
        $mapping = $this->getORMMapping($className);
        try {
            $results = $this->tableMapper->multiFetch($mapping->getTableMapping(), $primaryKeyValues, $ignoreMissingObjects);
            return $mapping->mapRowsToObjects($results);
        } catch (PrimaryKeyRowNotFoundException $e) {
            throw new ObjectNotFoundException($className, $primaryKeyValues);
        }
    }


    /**
     * Perform a filtered query using a WHERE / ORDER BY SQL clause for a single entity.
     * As this is object based, filters may be supplied using object member names (rather than column names).
     * This supports a subset of SQL and may include LIMIT, OFFSET and query sub entities in WHERE clauses
     * by traversing the object tree.
     *
     * @param string $className
     * @param string $whereClause
     * @param mixed ...$placeholderValues
     */
    public function filter($className, $whereClause = "", ...$placeholderValues) {
        $mapping = $this->getORMMapping($className);
        $whereClause = $mapping->replaceMembersWithColumns($whereClause);
        $results = $this->tableMapper->filter($mapping->getTableMapping(), $whereClause, $placeholderValues);
        return $mapping->mapRowsToObjects($results);

    }


    /**
     * Return an array of values for one or more expressions (either column names or SQL expressions e.g. count, distinct etc)
     * using items from this table or related entities thereof.
     *
     * @param $className
     * @param $expressions
     * @param string $whereClause
     * @param mixed ...$placeholderValues
     */
    public function values($className, $expressions, $whereClause = "", ...$placeholderValues) {
        $mapping = $this->getORMMapping($className);
        if (is_string($expressions)) {
            $expressions = $mapping->replaceMembersWithColumns($expressions);
        } else {
            foreach ($expressions as $index => $expression) {
                $expressions[$index] = $mapping->replaceMembersWithColumns($expression);
            }
        }
        $whereClause = $mapping->replaceMembersWithColumns($whereClause);
        $results = $this->tableMapper->values($mapping->getTableMapping(), $expressions, $whereClause, $placeholderValues);

        if (is_array($expressions)) {
            foreach ($results as $index => $result) {
                $newResult = [];
                foreach ($result as $key => $value) {
                    $newResult[$mapping->replaceColumnsWithMembers($key)] = $value;
                }
                $results[$index] = $newResult;
            }
        }

        return $results;
    }


    /**
     * Save one or more items - items can be supplied as either a single object or object array.
     *
     * @param mixed $items
     */
    public function save($items) {

        if (!is_array($items)) {
            $items = [$items];
        }

        // Group items by class name first
        $saveItems = [];
        foreach ($items as $item) {
            $itemClass = get_class($item);

            if (!isset($saveItems[$itemClass]))
                $saveItems[$itemClass] = [];

            $saveItems[$itemClass][] = $item;
        }

        // Now save in batches by class.
        foreach ($saveItems as $class => $classItems) {
            $mapping = $this->getORMMapping($class);
            $saveRows = $mapping->mapObjectsToRows($classItems);
            $saveRows = $this->tableMapper->save($mapping->getTableMapping(), $saveRows);
            $mapping->mapRowsToObjects($saveRows, false, $classItems);
        }

    }


    /**
     * Delete one or more items - items can be supplied as either a single object or object array.
     *
     * @param mixed $items
     */
    public function delete($items) {

        if (!is_array($items)) {
            $items = [$items];
        }

        // Group items by class name first
        $deleteItems = [];
        foreach ($items as $item) {
            $itemClass = get_class($item);

            if (!isset($saveItems[$itemClass]))
                $deleteItems[$itemClass] = [];

            $deleteItems[$itemClass][] = $item;
        }


        // Now save in batches by class.
        foreach ($deleteItems as $class => $classItems) {
            $mapping = $this->getORMMapping($class);
            $deleteRows = $mapping->mapObjectsToRows($classItems);
            $this->tableMapper->delete($mapping->getTableMapping(), $deleteRows);
        }

    }

    /**
     * Get an ORM Mapping by class name - maintains an array for efficiency.
     *
     * @param $className
     * @return ORMMapping
     */
    private function getORMMapping($className) {
        if (!isset($this->ormMappings[$className])) {
            $this->ormMappings[$className] = new ORMMapping($className);
        }
        return $this->ormMappings[$className];
    }
}
