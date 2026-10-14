<?php

namespace Kinikit\Persistence\Database\Vendors\MySQL;

use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\BulkData\BulkDataManager;
use Kinikit\Persistence\Database\Connection\ConnectionClosedException;
use Kinikit\Persistence\Database\Connection\PDODatabaseConnection;
use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableIndexColumn;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

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

    const QUERY_RETRY_STRINGS = [
        "Deadlock" => 1,
        "Lock wait" => 0,
        "gone away" => 1
    ];


    /**
     * @var int
     */
    private $exceptionRetries = 5;


    /**
     * Connect to the database.  This receives an array of normalised stripped config parameters
     * so e.g. "db.name" or "db.test.name" would be mapped to simply "name" for convenience of handling.
     *
     * @return boolean
     *
     */
    public function connect(array $configParams = []): bool {

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

        // If exception retries, record this.
        if (isset($configParams["exceptionRetries"])) $this->exceptionRetries = $configParams["exceptionRetries"];

        $connection = parent::connect($pdoParams);

        // Set sql mode to allow for our distinct logic
        $this->execute("SET @@sql_mode = (SELECT REPLACE(REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''), 'STRICT_TRANS_TABLES', ''))");

        return $connection;

    }

    /**
     * @return string
     */
    public function getResultSetClass(): string {
        return MySQLResultSet::class;
    }

    /**
     * Escape a MySQL column
     *
     * @param $columnName
     * @return mixed
     */
    public function escapeColumn(string $columnName): string {
        return "`" . $columnName . "`";
    }

    /**
     * Add MySQL specific parsing rules to SQL.
     *
     * @param string $sql
     * @param array &$parameterValues
     * @return string
     */
    public function parseSQL(string $sql, &$parameterValues = []): string {

        // Substitute plain VARCHAR keyword
        $sql = preg_replace("/VARCHAR([^\(]|$)/i", "VARCHAR(255)$1", $sql);

        return $sql;
    }

    /**
     * Override Execute method to retry if necessary.
     *
     * @param string $sql
     * @param mixed|BindParameter ...$placeholders
     * @return bool
     */
    public function execute(string $sql, ...$placeholders) {

        if (sizeof($placeholders) > 0 && is_array($placeholders[0])) {
            $placeholders = $placeholders[0];
        }

        $retries = 1;
        while ($retries > 0) {
            try {
                return parent::execute($sql, $placeholders);
            } catch (SQLException $e) {
                if (!self::isRetryException($e) || $retries > $this->exceptionRetries) {
                    throw $e;
                }
                Logger::log("MySQL Statement Retry $retries: " . $e->getMessage(), 6);
                $retries++;
            }
        }
    }

    /**
     * @param string $sql
     * @param BindParameter[] $parameters
     * @return ResultSet
     * @throws SQLException
     * @throws ConnectionClosedException
     */
    public function doQuery(string $sql, array $parameters): ResultSet {

        $retries = 1;
        while ($retries > 0) {
            try {
                return parent::doQuery($sql, $parameters);
            } catch (SQLException $e) {
                if (!self::isRetryException($e) || $retries > $this->exceptionRetries) {
                    throw $e;
                }
                Logger::log("MySQL Query Retry $retries: " . $e->getMessage(), 6);
                $retries++;
            }
        }

    }

    /**
     * Do create prepared statement.
     *
     * @param string $sql
     * @return MySQLPreparedStatement
     * @throws SQLException
     */
    public function doCreatePreparedStatement(string $sql): PreparedStatement {
        return new MySQLPreparedStatement($sql, $this->connection, $this->exceptionRetries);
    }


    /**
     * Begin a transaction
     * Also switch off auto commit when the main transaction starts.
     *
     * @throws SQLException
     */
    public function beginTransaction(): void {
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
    public function commit(): void {
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
    public function rollback(bool $wholeTransaction = true): void {
        parent::rollback($wholeTransaction);

        if ($this->transactionDepth == 0) $this->connection->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
    }


    /**
     * Get table column meta data for a given table as an associative array keyed in by column name.
     *
     * @param $tableName
     * @return TableColumn[]
     */
    public function getTableColumnMetaData(string $tableName): array {
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
     * Return the index data for a table
     *
     * @param string $tableName
     * @return TableIndex[]
     */
    public function getTableIndexMetaData(string $tableName): array {

        $results = $this->query("SHOW INDEXES FROM $tableName");

        $indexes = [];

        // Loop through and gather indexes
        $currentKey = null;
        $columns = [];
        while ($row = $results->nextRow()) {
            $key = $row["Key_name"];

            // Ignore PK
            if ($key == "PRIMARY") continue;

            // If changing key, stash index if required
            if ($key != $currentKey) {
                if ($currentKey) {
                    $indexes[] = new TableIndex($currentKey, $columns);
                }
                $currentKey = $key;
                $columns = [];
            }

            $columns[] = new TableIndexColumn($row["Column_name"], $row["Sub_part"] ?? -1);
        }

        if ($currentKey) {
            $indexes[] = new TableIndex($currentKey, $columns);
        }

        return $indexes;

    }

    /**
     * Return the standard bulk data manager for MySQL
     *
     * @return MySQLBulkDataManager
     */
    public function getBulkDataManager(): BulkDataManager {
        return new MySQLBulkDataManager($this);
    }

    /**
     * @return MySQLDDLManager
     */
    public function getDDLManager(): DDLManager {
        return new MySQLDDLManager();
    }


    /**
     * If the exception is a retry exception
     *
     * @param \Exception $exception
     * @return bool
     */
    public static function isRetryException($exception) {
        foreach (self::QUERY_RETRY_STRINGS as $retryString => $sleep) {
            if (str_contains($exception->getMessage(), $retryString)) {
                sleep($sleep);
                return true;
            }
        }
        return false;
    }

}

