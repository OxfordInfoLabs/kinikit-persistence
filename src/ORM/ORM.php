<?php

namespace Kinikit\Persistence\ORM;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Validation\ValidationException;
use Kinikit\Core\Validation\Validator;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
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
     * @var Validator
     */
    private $validator;

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection = null;


    /**
     * ORM constructor.  Auto inject table mapper instance.
     *
     * @param TableMapper $tableMapper
     * @param Validator $validator
     */
    public function __construct($tableMapper, $validator) {
        $this->tableMapper = $tableMapper;
        $this->validator = $validator;
    }


    /**
     * Explicit database connection instance
     *
     * @param $databaseConnection
     * @return ORM
     */
    public static function get($databaseConnection) {

        /**
         * @var $orm ORM
         */
        $orm = new ORM(Container::instance()->get(TableMapper::class), Container::instance()->get(Validator::class));
        $orm->databaseConnection = $databaseConnection;
        return $orm;
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
        $mapping = ORMMapping::get($className, $this->databaseConnection);


        try {
            $results = $this->tableMapper->fetch($mapping->getReadTableMapping(), $primaryKeyValue);
            $results = $mapping->mapRowsToObjects([$results], null, $this->databaseConnection);
            $result = sizeof($results) ? array_pop($results) : null;

            // If this has been vetoed by an interceptor also throw.
            if (!$result) {
                throw new ObjectNotFoundException($className, $primaryKeyValue);
            }

            return $result;

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
        $mapping = ORMMapping::get($className, $this->databaseConnection);
        try {
            $results = $this->tableMapper->multiFetch($mapping->getReadTableMapping(), $primaryKeyValues, $ignoreMissingObjects);
            return $mapping->mapRowsToObjects($results, null, $this->databaseConnection);
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


        // If array passed instead of ... values handle this
        if (isset($placeholderValues[0]) && is_array($placeholderValues[0])) {
            $placeholderValues = $placeholderValues[0];
        }

        $mapping = ORMMapping::get($className, $this->databaseConnection);
        $whereClause = $mapping->replaceMembersWithColumns($whereClause);

        $results = $this->tableMapper->filter($mapping->getReadTableMapping(), $whereClause, $placeholderValues);

        return array_values($mapping->mapRowsToObjects($results, null, $this->databaseConnection));

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

        // If array passed instead of ... values handle this
        if (isset($placeholderValues[0]) && is_array($placeholderValues[0])) {
            $placeholderValues = $placeholderValues[0];
        }

        $mapping = ORMMapping::get($className, $this->databaseConnection);
        if (is_string($expressions)) {
            $expressions = $mapping->replaceMembersWithColumns($expressions);
        } else {
            foreach ($expressions as $index => $expression) {
                $expressions[$index] = $mapping->replaceMembersWithColumns($expression);
            }
        }
        $whereClause = $mapping->replaceMembersWithColumns($whereClause);
        $results = $this->tableMapper->values($mapping->getReadTableMapping(), $expressions, $whereClause, $placeholderValues);

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
    public function save($items, $overrideValidation = false) {

        $isArray = is_array($items);
        if (!$isArray) {
            $items = [$items];
        }

        // Group items by class name first
        $saveItems = [];
        $validations = [];
        foreach ($items as $item) {
            $itemClass = get_class($item);

            if (!isset($saveItems[$itemClass]))
                $saveItems[$itemClass] = [];

            $saveItems[$itemClass][] = $item;

            if (!$overrideValidation) {
                // Validate the object for errors
                $validationErrors = $this->validator->validateObject($item);
                if ($validationErrors) {
                    $validations[] = $validationErrors;
                }
            }
        }

        if (sizeof($validations)) {
            throw new ValidationException($isArray ? $validations : $validations[0]);
        }

        // Now save in batches by class.
        foreach ($saveItems as $class => $classItems) {
            $mapping = ORMMapping::get($class, $this->databaseConnection);
            $saveRows = $mapping->mapObjectsToRows($classItems, databaseConnection: $this->databaseConnection);
            $saveRows = $this->tableMapper->save($mapping->getWriteTableMapping(), $saveRows);
            $mapping->mapRowsToObjects($saveRows, $classItems, $this->databaseConnection);
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

            if (!isset($deleteItems[$itemClass]))
                $deleteItems[$itemClass] = [];

            $deleteItems[$itemClass][] = $item;
        }


        // Now save in batches by class.
        foreach ($deleteItems as $class => $classItems) {

            $mapping = ORMMapping::get($class, $this->databaseConnection);
            $deleteRows = $mapping->mapObjectsToRows($classItems, "DELETE", $this->databaseConnection);


            $this->tableMapper->delete($mapping->getWriteTableMapping(), $deleteRows);
            $mapping->processPostDelete($classItems);
        }

    }


}
