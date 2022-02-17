<?php

namespace Kinikit\Persistence\Database\Vendors\SQLite3;

use Kinikit\Persistence\Database\BulkData\StandardBulkDataManager;
use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\Connection\PDODatabaseConnection;
use Kinikit\Persistence\Database\Generator\TableDDLGenerator;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;
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
        if (sizeof($results) == 0) {
            throw new SQLException("table $tableName does not exist");
        }

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
            if (sizeof($results) > 0)
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

    /**
     * Implement parsing rules specific to SQL Lite.  Specifically Alter table commands
     *
     * @param $sql
     * @return mixed|void
     */
    public function parseSQL($sql) {

        // Detect an alter table
        preg_match("/^ALTER TABLE (\w+) .*(DROP PRIMARY KEY|ADD PRIMARY KEY|ADD|DROP|MODIFY|CHANGE)/", $sql, $alterMatches);

        if (sizeof($alterMatches)) {

            // Grab table and meta data
            $table = $alterMatches[1];
            $tableMetaData = $this->getTableMetaData($table);

            // Rename the existing table
            $newString = "ALTER TABLE $table RENAME TO __$table;";
            $this->execute($newString);

            preg_match_all("/(ADD|DROP|MODIFY|CHANGE) COLUMN (.*?)(,|$)/i", $sql, $operationMatches, PREG_SET_ORDER);

            // Loop through all matches and update modified, add and remove arrays
            $modifiedColumns = [];
            $addColumns = [];
            $dropColumns = [];
            foreach ($operationMatches ?? [] as $matches) {
                $operation = $matches[1];

                // Grab column name as special
                $spec = explode(" ", $matches[2]);
                $previousColumnName = array_shift($spec);
                $newSpec = join(" ", $spec);

                if ($operation == "CHANGE") {
                    $splitSpec = explode(" ", trim($newSpec));
                    $newColumnName = array_shift($splitSpec);
                    $newSpec = join(" ", $splitSpec);
                    $modifiedColumns[$previousColumnName] = TableColumn::createFromStringSpec($newColumnName . " " . $newSpec);
                } else if ($operation == "MODIFY") {
                    $newColumnName = $previousColumnName;
                    $modifiedColumns[$previousColumnName] = TableColumn::createFromStringSpec($newColumnName . " " . $newSpec);
                } else if ($operation == "ADD") {
                    $addColumns[] = TableColumn::createFromStringSpec($previousColumnName . " " . $newSpec);
                } else if ($operation == "DROP") {
                    $dropColumns[$previousColumnName] = 1;
                }
            }

            // Register if we are dropping pk
            $dropPK = strpos(strtoupper($sql), "DROP PRIMARY KEY");

            // Grab any add primary key matches
            preg_match("/ADD PRIMARY KEY \((.*?)\)/", $sql, $addPKMatches);

            $pkColumns = [];
            if (sizeof($addPKMatches ?? []) > 0) {
                $pkColumns = explode(",", str_replace(" ", "", $addPKMatches[1]));
            }

            // Now make global array
            $newColumns = [];
            $selectColumnNames = [];
            $insertColumnNames = [];
            foreach ($tableMetaData->getColumns() as $column) {
                $newColumn = null;
                if ($modifiedColumns[$column->getName()] ?? null) {
                    $newColumn = UpdatableTableColumn::createFromTableColumn($modifiedColumns[$column->getName()]);
                    $selectColumnNames[] = $column->getName();
                    $insertColumnNames[] = $modifiedColumns[$column->getName()]->getName();
                } else if (!isset($dropColumns[$column->getName()])) {
                    $newColumn = UpdatableTableColumn::createFromTableColumn($column);
                    $selectColumnNames[] = $column->getName();
                    $insertColumnNames[] = $column->getName();
                }

                // Deal with primary keys
                if ($newColumn) {
                    if (in_array($newColumn->getName(), $pkColumns)) {
                        $newColumn->setPrimaryKey(true);
                    } else if ($dropPK || sizeof($pkColumns)) {
                        $newColumn->setPrimaryKey(false);
                    }
                    $newColumns[] = $newColumn;
                }
            }

            // Add any new columns
            $newColumns = array_merge($newColumns, $addColumns);

            $newMetaData = new TableMetaData($table, $newColumns);
            $ddlGenerator = new TableDDLGenerator();
            $newString = $ddlGenerator->generateTableCreateSQL($newMetaData, $this);
            $this->executeScript($newString);

            // Perform an insert using select and insert column names to synchronise the data
            $insertSQL = "INSERT INTO $table (" . join(",", $insertColumnNames) . ") SELECT " . join(",", $selectColumnNames) . " FROM __$table";
            $this->execute($insertSQL);


            $sql = "DROP TABLE __$table";

        }

        return $sql;
    }


}

