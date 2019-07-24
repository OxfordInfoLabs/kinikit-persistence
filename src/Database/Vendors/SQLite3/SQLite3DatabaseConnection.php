<?php

namespace Kinikit\Persistence\Database\Vendors\SQLite3;

use Kinikit\Persistence\Database\BulkData\StandardBulkDataManager;
use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\Connection\PDODatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\PreparedStatement\BlobWrapper;
use Kinikit\Persistence\Database\PreparedStatement\ColumnType;
use Kinikit\Persistence\Database\Connection\DatabaseConnectionException;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
use Kinikit\Persistence\Database\ResultSet\ResultSet;
use Kinikit\Persistence\Database\Exception\SQLException;

/**
 * Database connection implementation for SQLite 3
 * NB:  This needs the optional sqlite3 libraries installed to work correctly
 *
 * @noProxy
 */
class SQLite3DatabaseConnection extends PDODatabaseConnection {


    public function connect($configParams = []) {
        if (!isset($configParams["filename"]))
            throw new DatabaseConnectionException("No filename passed for SQL Lite database");

        return parent::connect(["dsn" => "sqlite:" . $configParams["filename"]]);

    }

    /**
     * Get table column meta data for a given table as an associative array keyed in by column name.
     *
     * @param $tableName
     * @return \Kinikit\Persistence\Database\MetaData\TableColumn[]
     */
    public function getTableColumnMetaData($tableName) {

        $results = $this->query("PRAGMA table_info('" . $tableName . "')")->fetchAll();

        $columns = [];
        $pkColumns = [];
        foreach ($results as $result) {

            // Grab the type
            $explodedType = explode("(", rtrim($result["type"], ")"));
            $type = $explodedType[0];
            $length = $precision = null;
            if (sizeof($explodedType) > 1) {
                $explodedArgs = explode(",", $explodedType[1]);
                $length = intval(trim($explodedArgs[0]));
                if (sizeof($explodedArgs) > 1) {
                    $precision = intval($explodedArgs[1]);
                }
            }

            $columns[$result["name"]] = new TableColumn($result["name"], strtoupper($type), $length, $precision,
                $result["dflt_value"], $result["pk"] ? true : false, false,
                $result["notnull"] ? true : false);

            if ($result["pk"]) {
                $pkColumns[] = $columns[$result["name"]];
            }

        }


        // If we have a single pk, do a second check for auto increment
        if (sizeof($pkColumns) == 1) {
            $results = $this->query("SELECT 'is-autoincrement' FROM sqlite_master WHERE tbl_name='$tableName' AND sql LIKE '%AUTOINCREMENT%'")->fetchAll();
            if (sizeof($results) >0)
                $pkColumns[0]->setAutoIncrement(true);
        }


        return $columns;


    }

    /**
     * Return the standard bulk data manager for SQLite
     *
     * @return \Kinikit\Persistence\Database\BulkData\BulkDataManager|StandardBulkDataManager
     */
    public function getBulkDataManager() {
        return new StandardBulkDataManager($this);
    }
}

