<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\UPF\Engines\ObjectIndex\ObjectIndexPersistenceEngine;
use Kinikit\Persistence\UPF\Engines\ORM\ORMPersistenceEngine;


/**
 * Main entry point for the Unified persistence framework.   This is a wrapper class which provides direct access for storing and retrieving
 * objects via the persistence engine implementations.
 *
 *
 * @author mark
 *
 */
class UPF {

    private static $instance;
    private $persistenceCoordinator;

    /**
     * ORM Constructor.  Constructed optionally with a configuration file for configuring the underlying persistence engine.
     */
    public function __construct($configFile = null) {

        if ($configFile instanceof ObjectPersistenceCoordinator) {
            $this->persistenceCoordinator = $configFile;
        } else if (is_string($configFile)) {
            $this->persistenceCoordinator = ObjectPersistenceCoordinator::createFromConfigFile($configFile);
        } else {
            $this->persistenceCoordinator = new ObjectPersistenceCoordinator ();
        }

        if (!$this->persistenceCoordinator->getEngines()) {
            $this->persistenceCoordinator->setEngines(array(new ORMPersistenceEngine(DefaultDB::instance(), "sql")));
        }

    }

    /**
     * Get an object by it's primary key.  Supply the class and the primary key
     *
     * @param string $objectClass
     * @param mixed $primaryKey
     */
    public function getObjectByPrimaryKey($objectClass, $primaryKey) {
        return $this->persistenceCoordinator->getObjectByPrimaryKey($objectClass, $primaryKey);
    }

    /**
     * Get multiple objects by primary key.  If the ignore missing boolean is passed in as false, exception will be thrown
     * if any of the passed objects don't exist.
     *
     * @param string $objectClass
     * @param mixed $primaryKeys
     * @param boolean $ignoreMissingObjects
     */
    public function getMultipleObjectsByPrimaryKey($objectClass, $primaryKeys, $ignoreMissingObjects = false) {
        return $this->persistenceCoordinator->getMultipleObjectsByPrimaryKey($objectClass, $primaryKeys, $ignoreMissingObjects);
    }

    /**
     * Perform an engine specific query for objects of the supplied class.  If no engine identifier is supplied,
     * the first engine defined for the particular class is used.  The query object must be in the format
     * expected by the target engine.
     *
     *
     * @param string $objectClass
     * @param mixed $queryObject
     * @param string $engineIdentifier
     */
    public function queryForObjects($objectClass, $queryObject) {

        // Grab all arguments after the first query object and pass them through
        $additionalArgs = func_get_args();
        array_shift($additionalArgs);
        array_shift($additionalArgs);

        if (sizeof($additionalArgs) == 1 && is_array($additionalArgs[0])) {
            $additionalArgs = $additionalArgs[0];
        }

        return $this->persistenceCoordinator->query($objectClass, $queryObject, $additionalArgs);
    }


    /**
     * Count the number of results for the supplied query
     *
     * @param $objectClass
     * @param $queryObject
     * @param null $engineIdentifier
     */
    public function countQueryResults($objectClass, $queryObject) {

        // Grab all arguments after the first query object and pass them through
        $additionalArgs = func_get_args();
        array_shift($additionalArgs);
        array_shift($additionalArgs);

        if (sizeof($additionalArgs) == 1 && is_array($additionalArgs[0])) {
            $additionalArgs = $additionalArgs[0];
        }

        return $this->persistenceCoordinator->count($objectClass, $queryObject, $additionalArgs);
    }


    /**
     * Synchronise any relationships for an existing object.  This effectively repopulates any contained relational
     * fields if possible and is particularly useful if creating a new skeletal object where the contained
     * relational objects are useful.
     *
     * @param $existingObject
     */
    public function synchroniseRelationships($existingObject) {
        $this->persistenceCoordinator->synchroniseRelationships($existingObject);
    }


    /*
     * Save an object.  This will save to all installed engines.
     *
     * @param object $object
     */
    public function saveObject($object) {
        $this->persistenceCoordinator->saveObject($object);
    }

    /**
     * Convenience method for saving an array of objects
     *
     * @param array $objects
     */
    public function saveMultipleObjects($objects) {
        if (is_array($objects)) {
            foreach ($objects as $object) {
                $this->saveObject($object);
            }
        } else {
            $this->saveObject($objects);
        }
    }


    /**
     * Remove an object.  This will remove from all installed engines.
     *
     * @param object $object
     */
    public function removeObject($object) {
        $this->persistenceCoordinator->removeObject($object);
    }

    /**
     * Get the persistence coordinator in use.
     *
     * @return ObjectPersistenceCoordinator $persistenceCoordinator
     */
    public function getPersistenceCoordinator() {
        return $this->persistenceCoordinator;
    }

    /**
     * Static instance method.  Used for obtaining ORM
     *
     * @return UPF
     */
    public static function instance($path = "config/upf.xml") {
        if (!UPF::$instance) {

            if (file_exists($path)) {
                UPF::$instance = new UPF ($path);
            } else {
                UPF::$instance = new UPF ();
            }
        }

        return UPF::$instance;
    }

}

?>