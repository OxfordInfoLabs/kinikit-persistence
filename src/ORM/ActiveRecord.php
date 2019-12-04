<?php

namespace Kinikit\Persistence\ORM;

use Kinikit\Core\DependencyInjection\Container;


/**
 * Active record class.  This provides a convenience mechanism for implementing the Active Record pattern.
 *
 * Class ActiveRecord
 * @package Kinikit\Persistence\UPF\Object
 */
class ActiveRecord {


    /**
     * Get object by primary key
     *
     * @param $primaryKey
     * @return mixed
     */
    public static function fetch($primaryKey) {
        return Container::instance()->get(ORM::class)->fetch(self::getClass(), $primaryKey);
    }


    /**
     * Get multiple objects by primary key
     *
     * @param $primaryKeys
     */
    public static function multiFetch($primaryKeys, $ignoreMissingObjects = false) {
        return Container::instance()->get(ORM::class)->multiFetch(self::getClass(), $primaryKeys, $ignoreMissingObjects);
    }


    /**
     * Query for objects of this type.
     *
     * @param $query
     */
    public static function filter($whereClause = "", ...$placeholderValues) {

        // If array passed instead of ... values handle this
        if (isset($placeholderValues[0]) && is_array($placeholderValues[0])) {
            $placeholderValues = $placeholderValues[0];
        }

        return Container::instance()->get(ORM::class)->filter(self::getClass(), $whereClause, $placeholderValues);
    }


    /**
     * /**
     * Return an array of values for one or more expressions (either column names or SQL expressions e.g. count, distinct etc)
     * using items from this table or related entities thereof.
     *
     * @param $expressions
     * @param string $whereClause
     * @param array ...$placeholderValues
     */
    public static function values($expressions, $whereClause = "", ...$placeholderValues) {

        // If array passed instead of ... values handle this
        if (isset($placeholderValues[0]) && is_array($placeholderValues[0])) {
            $placeholderValues = $placeholderValues[0];
        }

        return Container::instance()->get(ORM::class)->values(self::getClass(), $expressions, $whereClause, $placeholderValues);
    }


    /**
     * Save ourself
     */
    public function save() {
        Container::instance()->get(ORM::class)->save($this);
    }


    /**
     * Remove ourself
     */
    public function remove() {
        Container::instance()->get(ORM::class)->delete($this);
    }


    // Return the real class for the derived class.
    private static function getClass() {
        return static::class;
    }
}
