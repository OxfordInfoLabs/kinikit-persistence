<?php

namespace Kinikit\Persistence\UPF\LockingProviders;

use Kinikit\Core\Object\SerialisableObject;
use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\UPF\Framework\ObjectOptimisticLockingProvider;

/**
 * Simple SQL Optimistic Locking Provider.  This uses a single SQL Table to store last updated timestamps for objects identified
 * by class name and primary key.  An instance of this class may be plugged into the Object Persistence Coordinator as a means of enforcing
 * a locking strategy.
 *
 * @author mark
 *
 */
class SQLOptimisticLockingProvider extends SerialisableObject implements ObjectOptimisticLockingProvider {

    private $databaseConnection;
    private $ignoreFailures;

    /**
     * Construct this provider, optionally with a database connection, otherwise use the default.
     */
    public function __construct($databaseConnection = null, $ignoreFailures = false) {
        $this->databaseConnection = $databaseConnection ? $databaseConnection : DefaultDB::instance();
        $this->ignoreFailures = $ignoreFailures;
    }

    /**
     * Return the Database connection used to read/write to the lock table
     *
     * @return the $databaseConnection
     */
    public function getDatabaseConnection() {
        return $this->databaseConnection;
    }

    /**
     * Set the Database connection used to read/write to the lock table
     *
     * @param $databaseConnection the $databaseConnection to set
     */
    public function setDatabaseConnection($databaseConnection) {
        $this->databaseConnection = $databaseConnection;
    }

    /**
     * @return boolean
     */
    public function isIgnoreFailures() {
        return $this->ignoreFailures;
    }

    /**
     * @param boolean $ignoreFailures
     */
    public function setIgnoreFailures($ignoreFailures) {
        $this->ignoreFailures = $ignoreFailures;
    }


    /**
     * Register transaction start
     */
    public function persistenceTransactionStarted() {
        try {
            $this->databaseConnection->beginTransaction();
        } catch (\Exception $e) {
            if (!$this->ignoreFailures) {
                throw $e;
            }
        }
    }

    /**
     * Register transaction success
     */
    public function persistenceTransactionSucceeded() {

        try {
            $this->databaseConnection->commit();
        } catch (\Exception $e) {
            if (!$this->ignoreFailures) {
                throw $e;
            }
        }
    }

    /**
     * Register transaction failure
     */
    public function persistenceTransactionFailed() {
        try {
            $this->databaseConnection->rollback();
        } catch (\Exception $e) {
            if (!$this->ignoreFailures) {
                throw $e;
            }
        }
    }

    /**
     * Return current locking data suitable for attaching to an object for return
     *
     * @param ObjectMapper $objectMapper
     * @param string $primaryKey
     */
    public function getLockingDataForObject($objectMapper, $primaryKey) {
        return date('Y-m-d H:i:s');
    }

    /**
     * Update the locking data in the database table and return the current value for attachment to the
     * object which has just been updated.
     *
     * @param ObjectMapper $objectMapper
     * @param string $primaryKey
     */
    public function updateLockingDataForObject($objectMapper, $primaryKey) {


        $newTimeStamp = date('Y-m-d H:i:s');

        try {
            $escapedClassName = $this->databaseConnection->escapeString($objectMapper->getClassName());
            $escapedPK = $this->databaseConnection->escapeString($primaryKey);


            $existingLockingRows =
                $this->databaseConnection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_locking WHERE object_class = '" . $escapedClassName . "' AND object_pk = '" . $escapedPK . "'");
            if ($existingLockingRows == 0) {
                $this->databaseConnection->query("INSERT INTO kinikit_object_locking (object_class, object_pk, last_modified) VALUES ('" . $escapedClassName . "', '" . $escapedPK . "', '" . $newTimeStamp . "')");
            } else {
                $this->databaseConnection->query("UPDATE kinikit_object_locking SET last_modified = '" . $newTimeStamp . "' WHERE object_class = '" . $escapedClassName . "' AND object_pk = '" . $escapedPK . "'");
            }


        } catch (\Exception $e) {
            if (!$this->ignoreFailures) {
                throw $e;
            }
        }

        return $newTimeStamp;

    }

    /**
     * Check for a lock, using the mapper and primary key and locking data which has travelled with the object
     * since it was retrieved.
     *
     * @param ObjectMapper $objectMapper
     * @param string $primaryKey
     * @param mixed $objectLockingData
     */
    public function isObjectLocked($objectMapper, $primaryKey, $objectLockingData) {

        try {

            $escapedClassName = $this->databaseConnection->escapeString($objectMapper->getClassName());
            $escapedPK = $this->databaseConnection->escapeString($primaryKey);

            $matchingLockValue =
                $this->databaseConnection->queryForSingleValue("SELECT last_modified FROM kinikit_object_locking WHERE object_class = '" . $escapedClassName . "' AND object_pk = '" . $escapedPK . "'");

            // Return boolean indicating whether or not we are locked.
            return ($objectLockingData && $matchingLockValue) && ($matchingLockValue > $objectLockingData);

        } catch (\Exception $e) {
            if ($this->ignoreFailures) {
                return false;
            } else {
                throw $e;
            }
        }

    }

}

?>