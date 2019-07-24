<?php


namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Vendors\MySQL\MySQLDatabaseConnection;
use Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3DatabaseConnection;

class DatabaseConnectionProviderTest extends \PHPUnit\Framework\TestCase {

    public function testCanGetDatabaseConnectionByConfigKey() {

        $provider = Container::instance()->get(DatabaseConnectionProvider::class);

        $this->assertEquals(new SQLite3DatabaseConnection(["provider" => "sqlite3", "filename" => "DB/application.db", "logFile" => "DB/db-log.txt"]), $provider->getDatabaseConnectionByConfigKey());
        $this->assertEquals(new MySQLDatabaseConnection(["provider" => "mysql", "host" => "127.0.0.1", "database" => "kinikittest", "username" => "kinikittest", "password" => "kinikittest"]), $provider->getDatabaseConnectionByConfigKey("mysql"));

        try {
            $provider->getDatabaseConnectionByConfigKey("non-existent");
            $this->fail("Should have thrown here");
        } catch (MissingDatabaseConfigurationException $e) {
            // Success
        }


        try {
            Configuration::instance()->addParameter("partial.db.test", "Hello");
            $provider->getDatabaseConnectionByConfigKey("partial");
            $this->fail("Should have thrown here");
        } catch (InvalidDatabaseConfigurationException $e) {
            // Success
        }

    }


    public function testCanAddExplicitMappingsToDatabaseConnectionProvider() {

        $provider = Container::instance()->get(DatabaseConnectionProvider::class);
        $provider->addDatabaseConfiguration("adhoc", ["provider" => "sqlite3", "filename" => __DIR__ . "/test.db"]);

        $this->assertEquals(new SQLite3DatabaseConnection(["provider" => "sqlite3", "filename" => __DIR__ . "/test.db"]), $provider->getDatabaseConnectionByConfigKey("adhoc"));

    }

    public function testConnectionsAreCachedOnFirstAccess() {
        $provider = Container::instance()->get(DatabaseConnectionProvider::class);
        $mysql1 = $provider->getDatabaseConnectionByConfigKey("mysql");
        $mysql2 = $provider->getDatabaseConnectionByConfigKey("mysql");

        $this->assertTrue($mysql1 === $mysql2);

    }


}
