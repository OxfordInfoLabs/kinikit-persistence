<?php

namespace Kinikit\Persistence\Database\Vendors\MySQL;

use Kinikit\Core\Logging\Logger;
use Kinikit\Core\Util\FunctionStringRewriter;
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
     * Default type lengths
     */
    const DEFAULT_TYPE_LENGTHS = [
        "INT" => 11,
        "TINYINT" => 4,
        "SMALLINT" => 6,
        "BIGINT" => 20
    ];


    /**
     * Connect to the database.  This receives an array of normalised stripped config parameters
     * so e.g. "db.name" or "db.test.name" would be mapped to simply "name" for convenience of handling.
     *
     * @return boolean
     *
     */
    public function connect($configParams = []) {

        if (isset($configParams["host"]))
            $dsn = "mysql:host=" . $configParams["host"];
        else
            $dsn = "mysql:unix_socket=" . ($configParams["socket"] ?? "");

        $dsn .= (isset($configParams["database"]) ? ";dbname=" . $configParams["database"] : "");
        $dsn .= (isset($configParams["port"]) ? ";port=" . $configParams["port"] : "");
        $dsn .= (isset($configParams["charset"]) ? ";charset=" . $configParams["charset"] : "");

        $pdoParams = [
            "dsn" => $dsn
        ];

        // Add username and password in.
        if (isset($configParams["username"])) $pdoParams["username"] = $configParams["username"];
        if (isset($configParams["password"])) $pdoParams["password"] = $configParams["password"];

        $connection = parent::connect($pdoParams);

        // Set sql mode to allow for our distinct logic
        $this->execute("SET @@sql_mode = (SELECT REPLACE(REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''), 'STRICT_TRANS_TABLES', ''))");

        return $connection;

    }

    /**
     * @return string
     */
    public function getResultSetClass() {
        return MySQLResultSet::class;
    }

    /**
     * Escape a MySQL column
     *
     * @param $columnName
     * @return mixed
     */
    public function escapeColumn($columnName) {
        return "`" . $columnName . "`";
    }

    /**
     * Add MySQL specific parsing rules to SQL.
     *
     * @param $sql
     * @param array &$parameterValues
     * @return mixed|void
     */
    public function parseSQL($sql, &$parameterValues = []) {

        // Substitute AUTOINCREMENT keyword
        $sql = str_replace("AUTOINCREMENT", "AUTO_INCREMENT", $sql);

        // Substitute plain VARCHAR keyword
        $sql = preg_replace("/VARCHAR([^\(]|$)/i", "VARCHAR(255)$1", $sql);

        // Map functions
        if (!strpos($sql, "SEPARATOR")) {
            $sql = FunctionStringRewriter::rewrite($sql, "GROUP_CONCAT", "GROUP_CONCAT($1 SEPARATOR $2)", [null, "','"], $parameterValues);
        }

        $sql = FunctionStringRewriter::rewrite($sql, "EPOCH_SECONDS", "UNIX_TIMESTAMP($1)", [0], $parameterValues);
        $sql = FunctionStringRewriter::rewrite($sql, "ROW_NUMBER", "ROW_NUMBER() OVER (ORDER BY $1,$2)", ["1=1", "1=1"], $parameterValues);
        $sql = FunctionStringRewriter::rewrite($sql, "ROW_COUNT", "COUNT(*) OVER ()", [0], $parameterValues);
        $sql = FunctionStringRewriter::rewrite($sql, "TOTAL", "SUM($1) OVER ()", [0], $parameterValues);
        $sql = FunctionStringRewriter::rewrite($sql, "PERCENT", "100 * $1 / SUM($1) OVER ()", [0], $parameterValues);

        // Handle custom aggregate functions
        $sql = FunctionStringRewriter::rewrite($sql, "COUNT_PERCENT", "100 * COUNT($1) / COUNT_TOTAL($1)", [0], $parameterValues);
        $sql = FunctionStringRewriter::rewrite($sql, "SUM_PERCENT", "100 * SUM($1) / SUM_TOTAL($1)", [0], $parameterValues);
        $sql = FunctionStringRewriter::rewrite($sql, "COUNT_TOTAL", "SUM(COUNT($1)) OVER ()", [0], $parameterValues);
        $sql = FunctionStringRewriter::rewrite($sql, "SUM_TOTAL", "SUM(SUM($1)) OVER ()", [0], $parameterValues);


        // Handle Internet Address Conversions
        $sql = FunctionStringRewriter::rewrite($sql, "IP_ADDRESS_TO_NUMBER", "CASE WHEN $1 LIKE '%:%' THEN (CAST(CONV(SUBSTR(HEX(INET6_ATON($1)), 1, 16), 16, 10) as DECIMAL(65))*18446744073709551616 + CAST(CONV(SUBSTR(HEX(INET6_ATON($1)), 17, 16), 16, 10) as DECIMAL(65))) ELSE INET_ATON($1) END", [], $parameterValues);
        $sql = FunctionStringRewriter::rewrite($sql, "IP_NUMBER_TO_ADDRESS", "CASE WHEN $1 LIKE '%:%' THEN NULL ELSE INET_NTOA($1) END", [], $parameterValues);

        return $sql;
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
            $type = strtoupper($explodedType[0]);
            $length = $precision = null;
            if (sizeof($explodedType) > 1) {
                $explodedArgs = explode(",", $explodedType[1]);
                $length = intval(trim($explodedArgs[0]));
                if (sizeof($explodedArgs) > 1) {
                    $precision = intval($explodedArgs[1]);
                }
            } else {
                $length = self::DEFAULT_TYPE_LENGTHS[$type] ?? null;
            }

            $columns[$result["Field"]] = new TableColumn($result["Field"], $type, $length, $precision,
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

