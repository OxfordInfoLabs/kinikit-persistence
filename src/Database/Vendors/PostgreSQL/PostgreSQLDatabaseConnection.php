<?php

namespace Kinikit\Persistence\Database\Vendors\PostgreSQL;

use Kinikit\Core\Logging\Logger;
use Kinikit\Core\Util\FunctionStringRewriter;
use Kinikit\Persistence\Database\Connection\PDODatabaseConnection;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;

/**
 * Standard PostgreSQL implementation of the database connection class
 *
 * @noProxy
 */
class PostgreSQLDatabaseConnection extends PDODatabaseConnection {

    /**
     * Connect to the database.  This receives an array of normalised stripped config parameters
     * so e.g. "db.name" or "db.test.name" would be mapped to simply "name" for convenience of handling.
     *
     * @return boolean
     *
     */
    public function connect($configParams = []) {

        if (isset($configParams["host"]))
            $dsn = "pgsql:host=" . $configParams["host"];
        else
            $dsn = "pgsql:unix_socket=" . ($configParams["socket"] ?? "");

        $dsn .= (isset($configParams["port"]) ? ";port=" . $configParams["port"] : "");
        $dsn .= (isset($configParams["database"]) ? ";dbname=" . $configParams["database"] : "");

        $pdoParams = [
            "dsn" => $dsn
        ];

        // Add username and password in.
        if (isset($configParams["username"])) $pdoParams["username"] = $configParams["username"];
        if (isset($configParams["password"])) $pdoParams["password"] = $configParams["password"];

        return parent::connect($pdoParams);

    }

    /**
     * @return string
     */
    public function getResultSetClass() {
        return PostgreSQLResultSet::class;
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
        $sql = str_ireplace("INTEGER AUTOINCREMENT", "BIGSERIAL", $sql);
        $sql = str_ireplace("INTEGER AUTO_INCREMENT", "BIGSERIAL", $sql);
        $sql = str_ireplace("INTEGER PRIMARY KEY AUTOINCREMENT", "BIGSERIAL PRIMARY KEY", $sql);
        $sql = str_ireplace("INTEGER PRIMARY KEY AUTO_INCREMENT", "BIGSERIAL PRIMARY KEY", $sql);
        $sql = str_ireplace("INT AUTOINCREMENT", "BIGSERIAL", $sql);
        $sql = str_ireplace("INT AUTO_INCREMENT", "BIGSERIAL", $sql);


        // Map across default types
        $sql = str_ireplace("TINYINT", "SMALLINT", $sql);
        $sql = preg_replace("/DOUBLE(.[^P])/", "DOUBLE PRECISION$1", $sql);
        $sql = str_ireplace("DATETIME", "TIMESTAMP", $sql);
        $sql = str_replace("LONGBLOB", "BYTEA", $sql);
        $sql = str_replace("LONGTEXT", "TEXT", $sql);
        $sql = str_replace("BLOB", "BYTEA", $sql);


        // Map the functions
        $sql = str_ireplace("IFNULL(", "COALESCE(", $sql);
        $sql = FunctionStringRewriter::rewrite($sql, "GROUP_CONCAT", "STRING_AGG($1,$2)", [null, ","]);
        $sql = FunctionStringRewriter::rewrite($sql, "INSTR", "POSITION($1 IN $2)", [null, null]);
        $sql = FunctionStringRewriter::rewrite($sql, "EPOCH_SECONDS", "EXTRACT(EPOCH FROM $1)", [0]);

        return $sql;
    }

    public function escapeColumn($columnName) {
        return "\"" . $columnName . "\"";
    }


    public function getTableColumnMetaData($tableName) {

        $results = $this->query("SELECT *
            FROM information_schema.columns
            WHERE table_name = '$tableName'
        ")->fetchAll();

        $primaryKeys = $this->query("SELECT a.attname AS data_type
                                          FROM pg_index i
                                          JOIN pg_attribute a ON a.attrelid = i.indrelid
                                          AND a.attnum = ANY (i.indkey)
                                          WHERE i.indrelid = '$tableName'::regclass
                                          AND i.indisprimary;")->fetchAll();

        $columns = [];
        foreach ($results as $result) {
            $defaultValue = is_numeric($result["column_default"]) ? intval($result["column_default"]) : $result["column_default"] ?? null;
            $autoIncrement = $defaultValue == "nextval('" . $tableName . "_id_seq'::regclass)";
            $type = PostgreSQLResultSet::NATIVE_SQL_MAPPINGS[$result["udt_name"]] ?? TableColumn::SQL_VARCHAR;
            $columnName = $result["column_name"];
            $primaryKey = in_array(["data_type" => $columnName], $primaryKeys);
            $notNull = !($result["is_nullable"] == "YES");

            $length = $result["character_maximum_length"] ?? PostgreSQLResultSet::LENGTH_MAPPINGS[$result["udt_name"]] ?? $result["numeric_scale"] ?? null;

            $precision = isset(PostgreSQLResultSet::LENGTH_MAPPINGS[$result["udt_name"]]) ? null : $result["numeric_precision"];

            $columns[$columnName] = new TableColumn($columnName, $type, $length, $precision, $defaultValue, $primaryKey, $autoIncrement, $notNull);
        }


        return $columns;
    }

}