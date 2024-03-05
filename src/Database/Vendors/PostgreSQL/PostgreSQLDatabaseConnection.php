<?php

namespace Kinikit\Persistence\Database\Vendors\PostgreSQL;

use Kinikit\Core\Logging\Logger;
use Kinikit\Core\Util\FunctionStringRewriter;
use Kinikit\Persistence\Database\Connection\PDODatabaseConnection;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;

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

        // Map alter table statements
        preg_match("/^ALTER TABLE (\w+) .*(DROP PRIMARY KEY|ADD PRIMARY KEY|ADD|DROP|MODIFY|CHANGE)/", $sql, $alterMatches);

        if (sizeof($alterMatches)) {

            // Grab table and meta data
            $table = $alterMatches[1];

            preg_match_all("/(ADD|DROP|MODIFY|CHANGE) COLUMN (.*?)(,|$)/i", $sql, $operationMatches, PREG_SET_ORDER);

            // Loop through all matches and rewrite clauses

            foreach ($operationMatches ?? [] as $matches) {

                $oldClause = $matches[0];
                $operation = $matches[1];

                $spec = explode(" ", $matches[2]);
                $previousColumnName = trim(array_shift($spec), "\"");
                $newSpec = join(" ", $spec);

                $notNull = str_contains($newSpec, "NOT NULL");
                if ($notNull)
                    $newSpec = str_ireplace(" NOT NULL", "", $newSpec);

                switch ($operation) {
                    case "CHANGE":
                        $splitSpec = explode(" ", trim($newSpec));
                        $newColumnName = array_shift($splitSpec);
                        $newSpec = join(" ", $splitSpec);
                        $newClause = "ALTER COLUMN $newColumnName TYPE $newSpec";
                        $renameClauses[] = "RENAME COLUMN $previousColumnName TO $newColumnName";
                        if ($notNull)
                            $newClause .= ", ALTER COLUMN $newColumnName SET NOT NULL";
                        else
                            $newClause .= ", ALTER COLUMN $newColumnName DROP NOT NULL";

                        $newClause .= $matches[3];
                        break;
                    case "MODIFY":
                        $newClause = "ALTER COLUMN $previousColumnName TYPE $newSpec";
                        if ($notNull)
                            $newClause .= ", ALTER COLUMN $previousColumnName SET NOT NULL";
                        else
                            $newClause .= ", ALTER COLUMN $previousColumnName DROP NOT NULL";

                        $newClause .= $matches[3];
                        break;
                    case "DROP":
                    case "ADD":
                        $newClause = $oldClause;
                        break;
                }

                $sql = str_replace($oldClause, $newClause, $sql);


            }

            // Create and execute a new statement to rename columns
            if ($renameClauses ?? "") {
                $renaming = join(", ", $renameClauses);
                $renaming = "ALTER TABLE $table " . $renaming;
                $this->execute($renaming);
            }

            // Rewrite the drop primary keys statement
            if ($alterMatches[2] ?? null == "DROP PRIMARY KEY") {
                $primaryKeyName = $this->query("SELECT conname AS primary_key
FROM pg_constraint
WHERE contype = 'p'
  AND connamespace = 'public'::regnamespace
  AND conrelid::regclass::text = '$table';")->fetchAll()[0]["primary_key"];
                $sql = str_replace("DROP PRIMARY KEY", "DROP CONSTRAINT \"{$primaryKeyName}\"", $sql);
            }

        }

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

        Logger::log($sql);
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

        if (sizeof($results) == 0) {
            throw new SQLException("table $tableName does not exist");
        }

        $primaryKeys = $this->query("SELECT a.attname AS data_type
                                          FROM pg_index i
                                          JOIN pg_attribute a ON a.attrelid = i.indrelid
                                          AND a.attnum = ANY (i.indkey)
                                          WHERE i.indrelid = '$tableName'::regclass
                                          AND i.indisprimary;")->fetchAll();

        $columns = [];
        foreach ($results as $result) {
            $defaultValue = is_numeric($result["column_default"]) ? intval($result["column_default"]) : $result["column_default"] ?? null;
            if (str_contains($defaultValue ?? "", "nextval('" . $tableName . "_id_seq")) {
                $defaultValue = null;
                $autoIncrement = true;
            } else {
                $autoIncrement = false;
            }

            $type = PostgreSQLResultSet::NATIVE_SQL_MAPPINGS[$result["udt_name"]] ?? TableColumn::SQL_VARCHAR;
            $columnName = $result["column_name"];
            $primaryKey = in_array(["data_type" => $columnName], $primaryKeys);
            $notNull = !($result["is_nullable"] == "YES");

            $length = $result["character_maximum_length"] ?? PostgreSQLResultSet::LENGTH_MAPPINGS[$result["udt_name"]] ?? null;

            $precision = isset(PostgreSQLResultSet::LENGTH_MAPPINGS[$result["udt_name"]]) ? null : $result["numeric_precision"];

            $columns[$columnName] = new TableColumn($columnName, $type, $length, $precision, $defaultValue, $primaryKey, $autoIncrement, $notNull);
        }

        return $columns;
    }


    /**
     * Return the index data for a table
     *
     * @param $tableName
     * @return TableIndex[]
     */
    public function getTableIndexMetaData($tableName) {
        $indexData = $this->query(
            "select t.relname as table_name,
    i.relname as index_name,
    array_to_string(array_agg(a.attname), ',') as column_names
from
    pg_class t,
    pg_class i,
    pg_index ix,
    pg_attribute a
where
    t.oid = ix.indrelid
    and i.oid = ix.indexrelid
    and a.attrelid = t.oid
    and a.attnum = ANY(ix.indkey)
    and t.relkind = 'r'
    and t.relname like ?
group by
    t.relname,
    i.relname
order by
    t.relname,
    i.relname", $tableName . "%");

        $indexes = [];

        // Loop through each index
        while ($index = $indexData->nextRow()) {
            if (!strpos($index["index_name"], "pkey"))
                $indexes[] = new TableIndex($index["index_name"], explode(",", $index["column_names"]));
        }

        return $indexes;

    }

    /**
     * @param string $string
     * @return string
     */
    public function sanitiseAutoIncrementString(string $string): string {

        preg_match_all("/(['|\"]?\w+['|\"]?) [A-Z]*INT[A-Z]* ([\w\s]*)AUTOINCREMENT/", $string, $matches, PREG_SET_ORDER);

        if (!$matches)
            return $string;

        $newString = $matches[0][1] . " BIGSERIAL";
        if (str_contains($matches[0][2], "PRIMARY KEY"))
            $newString .= " PRIMARY KEY";

        return str_replace($matches[0][0], $newString, $string);

    }

}