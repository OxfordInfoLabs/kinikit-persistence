<?php

namespace Kinikit\Persistence\UPF\Framework;

/**
 * Special facade object used when lazy loading is employed through the persistence engine.  This is an object which is configured with
 * the backing object's Type and Primary Key and also the Retrieval Engine instance used to pull the real version if required.  Application code should
 * simply call the getRealObject method to return the real object pulled by primary key.
 *
 * @author mark
 *
 */
class ObjectFacade extends ObjectArrayFacade {

    /**
     * Construct this facade with all data required to pull the real object instance as required.
     * This essentially contains the information to then perform the
     *
     * @param string $underlyingObjectClass
     * @param mixed $underlyingObjectPrimaryKey
     * @param ObjectRetrievalEngine $retrievalEngineInstance
     */
    public function __construct($underlyingObjectClass = null, $underlyingObjectFieldValues = null, $engineIdentifier = null, $persistenceCoordinatorInstance = null) {
        parent::__construct($underlyingObjectClass, $underlyingObjectFieldValues, $engineIdentifier, $persistenceCoordinatorInstance);
    }

    /**
     * Pull the real version of the object from the configured facade.
     *
     */
    public function returnRealObject($noCache = false) {

        $realArrayObjects = parent::returnRealObject($noCache);
        return is_array($realArrayObjects) && sizeof($realArrayObjects) > 0 ? $realArrayObjects [0] : null;

    }

}

?>