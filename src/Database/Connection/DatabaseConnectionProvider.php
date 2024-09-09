<?php


namespace Kinikit\Persistence\Database\Connection;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\DependencyInjection\InterfaceResolver;

/**
 * Provider for database connections.  Particularly useful for applications where multiple
 * database connections are in use.
 *
 * Class DatabaseConnectionProvider
 * @package Kinikit\Persistence\Database\Connection
 */
class DatabaseConnectionProvider {

    /**
     * @var DatabaseConnection[string]
     */
    private $databaseConnections = [];

    /*
     * @var string[string][string]
     */
    private $explicitConfigurations = [];

    public function __construct(
        private InterfaceResolver $interfaceResolver) {
    }


    /**
     * Get a database connection by a config key.  This usually refers to a prefix in the configuration file.
     * If supplied as null, the default database connection will be returned.
     *
     * @return DatabaseConnection
     * @throws MissingDatabaseConfigurationException
     */
    public function getDatabaseConnectionByConfigKey($configKey = null) {

        // Shortcut if we already have one.
        if (isset($this->databaseConnections[$configKey])) {
            return $this->databaseConnections[$configKey];
        }

        // Check for explicit configs or get from config.
        if (isset($this->explicitConfigurations[$configKey]))
            $configParams = $this->explicitConfigurations[$configKey];
        else
            $configParams = Configuration::instance()->getParametersMatchingPrefix($configKey ? $configKey . ".db." : "db.", true);

        if (sizeof($configParams) == 0)
            throw new MissingDatabaseConfigurationException($configKey);

        if (!isset($configParams["provider"]))
            throw new InvalidDatabaseConfigurationException("The database configuration with key $configKey is missing a provider");

        $connectionClass = $this->interfaceResolver->getImplementationClassForKey(DatabaseConnection::class, $configParams["provider"]);

        // Store the database connection for future use.
        $connection = new $connectionClass($configParams);

        $this->databaseConnections[$configKey] = $connection;

        return $connection;

    }


    /**
     * Add a database configuration explicitly (e.g. in Bootstrap).  Useful if adhoc connections are required
     * e.g. dynamic configurations.
     *
     * @param $configKey
     * @param $configuration
     */
    public function addDatabaseConfiguration($configKey, $configuration) {
        $this->explicitConfigurations[$configKey] = $configuration;
    }

}
