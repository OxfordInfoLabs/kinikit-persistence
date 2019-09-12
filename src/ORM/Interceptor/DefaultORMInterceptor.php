<?php


namespace Kinikit\Persistence\ORM\Interceptor;

/**
 * Default interceptor - useful when we want to only override certain interceptor functions.
 *
 * Class DefaultORMInterceptor
 * @package Kinikit\Persistence\ORM\Interceptor
 */
class DefaultORMInterceptor implements ORMInterceptor {

    /**
     * Called after an object has been mapped using the ORM.  This function should return
     * true if this object is permitted to be returned to the application or false if this
     * should not be returned.  Particularly useful for enforcing object level permissions.
     *
     * @param mixed $object
     * @return boolean
     */
    public function postMap($object) {
        return true;
    }

    /**
     * Called before an object is saved using the ORM. This function does not return a value
     * but should throw an appropriate exception (e.g. AccessDeniedException) to veto the save
     * process.
     *
     * @param $object
     */
    public function preSave($object) {
    }


    /**
     * Most useful for logging purposes or reconciling other entities.  This is called after the save
     * of this object.
     *
     * @param $object
     */
    public function postSave($object) {
    }

    /**
     * Called before an object is deleted using the ORM. This function does not return a value
     * but should throw an appropriate exception (e.g. AccessDeniedException) to veto the delete
     * process.
     *
     * @param $object
     */
    public function preDelete($object) {
    }

    /**
     * Most useful for logging purposes or reconciling other entities.  This is called after the delete
     * of this object.
     *
     * @param $object
     */
    public function postDelete($object) {
    }
}


