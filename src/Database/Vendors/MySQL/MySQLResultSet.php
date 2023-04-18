<?php


namespace Kinikit\Persistence\Database\Vendors\MySQL;


use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\ResultSet\PDOResultSet;

class MySQLResultSet extends PDOResultSet {

    // Mappings from MYSQL Native Types to SQL Types
    const NATIVE_SQL_MAPPINGS = [
        "LONG" => TableColumn::SQL_INTEGER,
        "VAR_STRING" => TableColumn::SQL_VARCHAR,
        "TINY" => TableColumn::SQL_TINYINT,
        "SHORT" => TableColumn::SQL_SMALLINT,
        "LONGLONG" => TableColumn::SQL_BIGINT,
        "FLOAT" => TableColumn::SQL_FLOAT,
        "DOUBLE" => TableColumn::SQL_DOUBLE,
        "NEWDECIMAL" => TableColumn::SQL_DECIMAL,
        "DATE" => TableColumn::SQL_DATE,
        "TIME" => TableColumn::SQL_TIME,
        "DATETIME" => TableColumn::SQL_DATE_TIME,
        "TIMESTAMP" => TableColumn::SQL_TIMESTAMP,
        "BLOB" => TableColumn::SQL_BLOB,
        "LONGBLOB" => TableColumn::SQL_LONGBLOB,
        "TEXT" => TableColumn::SQL_BLOB,
        "LONGTEXT" => TableColumn::SQL_LONGBLOB
    ];


    // List of Native Types which use length
    const LENGTH_COLUMN_DIVISORS = [
        "LONG" => 1,
        "VAR_STRING" => 4,
        "TINY" => 1,
        "SHORT" => 1,
        "LONGLONG" => 1,
        "FLOAT" => 1,
        "DOUBLE" => 1,
        "NEWDECIMAL" => 1
    ];

    /**
     * Return the columns for a MySQL Result Set
     *
     * @return ResultSetColumn[]
     */
    public function getColumns() {
        $columns = array();
        for ($i = 0; $i < $this->statement->columnCount(); $i++) {
            try {
                $columnMeta = $this->statement->getColumnMeta($i);

                if ($columnMeta) {

                    // Fall back to varchar
                    $columnType = self::NATIVE_SQL_MAPPINGS[$columnMeta["native_type"]] ?? TableColumn::SQL_VARCHAR;
                    $lengthDivisor = self::LENGTH_COLUMN_DIVISORS[$columnMeta["native_type"]] ?? null;

                    if ($columnType == TableColumn::SQL_BLOB && $columnMeta["len"] > 300000)
                        $columnType = TableColumn::SQL_LONGBLOB;

                    $columns[] = new ResultSetColumn($columnMeta["name"], $columnType,
                        $lengthDivisor ? $columnMeta["len"] / $lengthDivisor : null, $lengthDivisor ? $columnMeta["precision"] : null);

                } else {
                    $columns[] = new ResultSetColumn("column" . ($i + 1), TableColumn::SQL_VARCHAR);
                }

            } catch (\PDOException $e) {
            }
        }
        print_r($columns);
        return $columns;
    }
}