<?php

namespace Kinikit\Persistence\Database\Vendors\PostgreSQL;

use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\ResultSet\PDOResultSet;

class PostgreSQLResultSet extends PDOResultSet {

    // Mappings from PostgreSQL Native Types to SQL Types
    const NATIVE_SQL_MAPPINGS = [
        "int2" => TableColumn::SQL_SMALLINT,
        "int4" => TableColumn::SQL_INTEGER,
        "int8" => TableColumn::SQL_BIGINT,
        "float4" => TableColumn::SQL_REAL,
        "float8" => TableColumn::SQL_DOUBLE,
        "numeric" => TableColumn::SQL_DECIMAL,
        "date" => TableColumn::SQL_DATE,
        "time" => TableColumn::SQL_TIME,
        "timestamp" => TableColumn::SQL_DATE_TIME,
        "text" => TableColumn::SQL_BLOB,
        "bytea" => TableColumn::SQL_LONGBLOB
    ];

    const LENGTH_MAPPINGS = [
        "int2" => 6,
        "int4" => 11,
        "int8" => 20,
        "float4" => 0,
        "float8" => 0
    ];

    public function getColumns() {

        $columns = [];
        for ($i = 0; $i < $this->statement->columnCount(); $i++) {
            try {
                $columnMeta = $this->statement->getColumnMeta($i);

                if ($columnMeta) {

                    $nativeType = $columnMeta["native_type"];
                    $columnType = self::NATIVE_SQL_MAPPINGS[$nativeType] ?? TableColumn::SQL_VARCHAR;

                    $length = self::LENGTH_MAPPINGS[$nativeType] ?? null;
                    $precision = null;

                    // Deal with varchar separately
                    if ($nativeType == "varchar") {
                        $length = $columnMeta["precision"] - 4;
                        $precision = null;
                    }

                    $columns[] = new ResultSetColumn($columnMeta["name"], $columnType, $length, $precision);

                } else {
                    $columns[] = new ResultSetColumn("column" . ($i + 1), TableColumn::SQL_VARCHAR);
                }

            } catch (\PDOException $e) {
            }
        }

        return $columns;
    }

    public function nextRow() {

        $row = parent::nextRow();

        if ($row) {
            foreach ($row as $key => $value) {
                if (is_resource($value)) {
                    $stringValue = "";
                    while (!feof($value)) {
                        $stringValue .= fread($value, 32000);
                    }
                    $row[$key] = $stringValue;
                }
            }
        }

        return $row;
    }


}