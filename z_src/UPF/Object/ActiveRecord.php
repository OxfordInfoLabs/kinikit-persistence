<?php

namespace Kinikit\Persistence\UPF\Object;

use Kinikit\Core\Object\SerialisableObject;
use Kinikit\Persistence\UPF\Framework\UPF;


/**
 * Active record class.  This extends serialisable object with Active Record pattern behaviour for
 * accessing objects directly via the UPF.
 *
 * Class ActiveRecord
 * @package Kinikit\Persistence\UPF\Object
 */
class ActiveRecord extends SerialisableObject {


    /**
     * Get object by primary key
     *
     * @param $primaryKey
     * @return mixed
     */
    public static function fetch($primaryKey) {
        return UPF::instance()->getObjectByPrimaryKey(self::getClass(), $primaryKey);
    }


    /**
     * Get multiple objects by primary key
     *
     * @param $primaryKeys
     */
    public static function multiFetch($primaryKeys, $ignoreMissingObjects = false) {
        return UPF::instance()->getMultipleObjectsByPrimaryKey(self::getClass(), $primaryKeys, $ignoreMissingObjects);
    }


    /**
     * Query for objects of this type.
     *
     * @param $query
     */
    public static function query($query) {

        // Grab all arguments after the first query object and pass them through
        $additionalArgs = func_get_args();
        array_shift($additionalArgs);

        return UPF::instance()->queryForObjects(self::getClass(), $query, $additionalArgs);

    }


    /**
     * Count the number of objects in the supplied query.
     *
     * @param $query
     */
    public static function countQuery($query) {
        // Grab all arguments after the first query object and pass them through
        $additionalArgs = func_get_args();
        array_shift($additionalArgs);

        return UPF::instance()->countQueryResults(self::getClass(), $query, $additionalArgs);
    }


    /**
     * Save ourself
     */
    public function save() {
        UPF::instance()->saveObject($this);
    }


    /**
     * Remove ourself
     */
    public function remove() {
        UPF::instance()->removeObject($this);
    }


    /**
     * Synchronise relationships for ourself.
     */
    public function synchroniseRelationships() {
        UPF::instance()->synchroniseRelationships($this);
    }


    // Return the real class for the derived class.
    private static function getClass() {
        return get_class(new static());
    }
}