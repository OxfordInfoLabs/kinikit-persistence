<?php

namespace Kinikit\Persistence\Database\Vendors\MySQL;

use Kinikit\Persistence\Database\BulkData\StandardBulkDataManager;
use Kinikit\Persistence\Database\Connection\PDODatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\PreparedStatement\BlobWrapper;
use Kinikit\Persistence\Database\PreparedStatement\ColumnType;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;

use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\PreparedStatement\WrongNumberOfPreparedStatementParametersException;

/**
 * Standard MYSQL implementation of the database connection class
 *
 * @noProxy
 */
class MySQLDatabaseConnection extends PDODatabaseConnection {


    /**
     * Connect to the database.  This receives an array of normalised stripped config parameters
     * so e.g. "db.name" or "db.test.name" would be mapped to simply "name" for convenience of handling.
     *
     * @return boolean
     *
     */
    public function connect($configParams = []) {

        $dsn = "mysql:host=" . $configParams["host"];
        $dsn .= (isset($configParams["database"]) ? ";dbname=" . $configParams["database"] : "");
        $dsn .= (isset($configParams["port"]) ? ";port=" . $configParams["port"] : "");
        $dsn .= (isset($configParams["socket"]) ? ";socket=" . $configParams["socket"] : "");
        $dsn .= (isset($configParams["charset"]) ? ";charset=" . $configParams["charset"] : "");


        $pdoParams = [
            "dsn" => $dsn
        ];

        // Add username and password in.
        if (isset($configParams["username"])) $pdoParams["username"] = $configParams["username"];
        if (isset($configParams["password"])) $pdoParams["password"] = $configParams["password"];

        return parent::connect($pdoParams);

    }


    /**
     * Begin a transaction
     * Also switch off auto commit when the main transaction starts.
     *
     * @throws SQLException
     */
    public function beginTransaction() {
        if ($this->transactionDepth == 0) {
            $this->connection->setAttribute(\PDO::ATTR_AUTOCOMMIT, 0);
        }
        parent::beginTransaction();
    }

    /**
     * Commit the current transaction
     * Also switch back on auto commit once the outer transaction is complete.
     * @throws SQLException
     */
    public function commit() {
        parent::commit();
        if ($this->transactionDepth == 0) $this->connection->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
    }

    /**
     * Rollback the current transaction
     * Also switch back on auto commit once the outer transaction is complete.
     *
     * @param $wholeTransaction bool
     * @throws SQLException
     */
    public function rollback($wholeTransaction = true) {
        parent::rollback($wholeTransaction);

        if ($this->transactionDepth == 0) $this->connection->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
    }


    /**
     * Get table column meta data for a given table as an associative array keyed in by column name.
     *
     * @param $tableName
     * @return \Kinikit\Persistence\Database\MetaData\TableColumn[]
     */
    public function getTableColumnMetaData($tableName) {
        $results = $this->query("SHOW COLUMNS FROM " . $tableName)->fetchAll();

        // Loop through each result and map to table column objects
        $columns = [];
        foreach ($results as $result) {

            // Grab the type
            $explodedType = explode("(", rtrim($result["Type"], ")"));
            $type = $explodedType[0];
            $length = $precision = null;
            if (sizeof($explodedType) > 1) {
                $explodedArgs = explode(",", $explodedType[1]);
                $length = intval(trim($explodedArgs[0]));
                if (sizeof($explodedArgs) > 1) {
                    $precision = intval($explodedArgs[1]);
                }
            }

            $columns[$result["Field"]] = new TableColumn($result["Field"], strtoupper($type), $length, $precision,
                $result["Default"], $result["Key"] == "PRI", is_numeric(strpos($result["Extra"], "auto_increment")),
                $result["Null"] === "NO");
        }


        return $columns;

    }

    /**
     * Return the standard bulk data manager for MySQL
     *
     * @return \Kinikit\Persistence\Database\BulkData\BulkDataManager|StandardBulkDataManager
     */
    public function getBulkDataManager() {
        return new StandardBulkDataManager($this);
    }


}

?>
