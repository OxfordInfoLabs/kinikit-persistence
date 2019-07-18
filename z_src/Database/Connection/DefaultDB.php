<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 15/08/2018
 * Time: 10:54
 */

namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Core\Configuration;
use Kinikit\Persistence\Database\Connection\MSAccess\MSAccessDatabaseConnection;
use Kinikit\Persistence\Database\Connection\MSSQL\MSSQLDatabaseConnection;
use Kinikit\Persistence\Database\Connection\MySQL\MySQLDatabaseConnection;
use Kinikit\Persistence\Database\Connection\ODBC\ODBCDatabaseConnection;
use Kinikit\Persistence\Database\Connection\SQLite3\SQLite3DatabaseConnection;

class DefaultDB {

    private static $instance;

    /**
     * Convenience method to get the default configured database connection.
     *
     * @return DatabaseConnection
     */
    public static function instance($forceNew = false) {

        $connection = self::$instance;

        if ($connection == null || $forceNew) {

            switch (Configuration::readParameter("db.provider")) {
                case "sqlite3":
                    $connection = new SQLite3DatabaseConnection(Configuration::readParameter("db.filename"), Configuration::readParameter("db.logfile"));
                    break;
                case "msaccess":
                    $connection = new MSAccessDatabaseConnection(Configuration::readParameter("db.dsn"), Configuration::readParameter("db.username"), Configuration::readParameter("db.password"));
                    break;
                case "mssql":
                    $connection = new MSSQLDatabaseConnection(Configuration::readParameter("db.server_name"), Configuration::readParameter("db.username"), Configuration::readParameter("db.password"), Configuration::readParameter("db.database"));
                    break;
                case "odbc":
                    $connection = new ODBCDatabaseConnection(Configuration::readParameter("db.dsn"), Configuration::readParameter("db.username"), Configuration::readParameter("db.password"));
                    break;
                default:
                    $connection = new MySQLDatabaseConnection (Configuration::readParameter("db.host"), Configuration::readParameter("db.database"), Configuration::readParameter("db.username"), Configuration::readParameter("db.password"), Configuration::readParameter("db.port"), Configuration::readParameter("db.socket"), Configuration::readParameter("db.logfile"), Configuration::readParameter("db.character_set"));
                    break;
            }

        }

        // Store the database connection for later if this is the first time an unforced connection
        // has been requested
        if (!$forceNew) {
            self::$instance = $connection;
        }

        return $connection;
    }


    /**
     * Get the default mysql connection object in a convenient manner for ease of use in code.
     *
     * * @return mysqli
     */
    public static function getConnection() {
        return self::instance()->getUnderlyingConnection();
    }


}
