<?php

namespace Kinikit\Persistence\Database\Vendors\SQLite3;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Logging\Logger;
use Kinikit\Core\Util\FunctionStringRewriter;
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
use Kinikit\Persistence\Database\Vendors\SQLite3\CustomFunctions\Concat;
use Kinikit\Persistence\Database\Vendors\SQLite3\CustomFunctions\Day;
use Kinikit\Persistence\Database\Vendors\SQLite3\CustomFunctions\Month;
use Kinikit\Persistence\Database\Vendors\SQLite3\CustomFunctions\Now;
use Kinikit\Persistence\Database\Vendors\SQLite3\CustomFunctions\Year;

/**
 * Database connection implementation for SQLite 3
 * NB:  This needs the optional sqlite3 libraries installed to work correctly
 *
 * @noProxy
 */
class SQLite3DatabaseConnection extends PDODatabaseConnection {

    /**
     * Array of custom functions to be added to all created connections
     *
     * @var SQLite3CustomFunction[]
     */
    private static $customFunctions = [];


    public function connect($configParams = []) {
        if (!isset($configParams["filename"]))
            throw new DatabaseConnectionException("No filename passed for SQL Lite database");

        $connected = parent::connect(["dsn" => "sqlite:" . $configParams["filename"]]);

        if ($connected)
            $this->applyCustomFunctionsToConnection();

        return $connected;
    }


    /**
     * @param SQLite3CustomFunction $customFunction
     */
    public static function addCustomFunction($customFunction) {
        self::$customFunctions[] = $customFunction;
    }


    /**
     * Get result set class
     *
     * @return string
     */
    public function getResultSetClass() {
        return SQLite3ResultSet::class;
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


    public function escapeColumn($columnName) {
        return '"' . $columnName . '"';
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
     * @param array &$parameterValues
     * @return mixed|void
     */
    public function parseSQL($sql, &$parameterValues = []) {

        // Detect an alter table
        preg_match("/^ALTER TABLE (\w+) .*(DROP PRIMARY KEY|ADD PRIMARY KEY|ADD|DROP|MODIFY|CHANGE)/", $sql, $alterMatches);

        if (sizeof($alterMatches)) {

            // Grab table and meta data
            $table = $alterMatches[1];
            $tableMetaData = $this->getTableMetaData($table);

            // Rename the existing table
            $this->execute("DROP TABLE IF EXISTS __$table");
            $this->execute("ALTER TABLE $table RENAME TO __$table");


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

            try {
                $newString = $ddlGenerator->generateTableCreateSQL($newMetaData, $this);
                $this->executeScript($newString);

                // Perform an insert using select and insert column names to synchronise the data
                $insertSQL = "INSERT INTO $table (" . join(",", $insertColumnNames) . ") SELECT " . join(",", $selectColumnNames) . " FROM __$table";
                $this->execute($insertSQL);

                $sql = "DROP TABLE __$table";
            } catch (SQLException $e) {

                // Reset the table if an error occurs
                $this->execute("DROP TABLE IF EXISTS $table");
                $this->execute("ALTER TABLE __$table RENAME TO $table");
                throw ($e);
            }

        }


        // Map functions
        $sql = FunctionStringRewriter::rewrite($sql, "EPOCH_SECONDS", "STRFTIME('%s',$1)", [0]);

        return $sql;
    }

    // Apply all custom functions defined statically to the connection
    private function applyCustomFunctionsToConnection() {

        // Add the built ins
        $builtIns = [new Concat(), new Year(), new Month(), new Day(), new Now()];

        foreach (array_merge($builtIns, self::$customFunctions) as $customFunction) {
            $this->connection->sqliteCreateFunction($customFunction->getName(), array($customFunction, "execute"));
        }


    }


}

