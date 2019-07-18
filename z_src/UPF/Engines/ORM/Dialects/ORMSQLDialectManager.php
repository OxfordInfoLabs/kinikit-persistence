<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Dialects;

/**
 * Manager application for looking up the sql dialect to use for a particular database connection object.
 * The detection is done by class name of the Database connection object.
 *
 * @author mark
 *
 */
class ORMSQLDialectManager {

    private static $instance = null;
    private $dialects = array();

    // Block direct construction
    private function __construct() {
        $this->addDialectForConnection("default", new DefaultSQLDialect ());
        $this->addDialectForConnection("MSSQLDatabaseConnection", new MSSQLDialect ());
        $this->addDialectForConnection("MSAccessDatabaseConnection", new MSAccessSQLDialect());
    }

    /**
     * Return an instance of the manager
     *
     * @return ORMSQLDialectManager
     */
    public static function instance() {
        if (!ORMSQLDialectManager::$instance) {
            ORMSQLDialectManager::$instance = new ORMSQLDialectManager ();
        }

        return ORMSQLDialectManager::$instance;
    }

    /**
     * Inject a dialect for a database connection type supplying the class name of the connection
     * and a dialect instance for generating sql for this connection type.
     *
     * @param string $connectionClassName
     * @param ORMSQLDialect $dialectInstance
     */
    public function addDialectForConnection($connectionClassName, $dialectInstance) {
        $this->dialects [$connectionClassName] = $dialectInstance;
    }

    /**
     * Return the dialect for connection
     *
     * @param BaseDatabaseConnection $connectionObject
     * @return ORMSQLDialect
     */
    public function getDialectForConnection($connectionObject) {
        if ($connectionObject && isset ($this->dialects [get_class($connectionObject)])) {
            return $this->dialects [get_class($connectionObject)];
        } else {
            return $this->dialects ["default"];
        }
    }

}

?>