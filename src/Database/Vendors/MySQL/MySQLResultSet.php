<?php


namespace Kinikit\Persistence\Database\Vendors\MySQL;


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
        "LONGTEXT" => TableColumn::SQL_LONGBLOB,
        "JSON" => TableColumn::SQL_LONGBLOB
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

    // Store columns for efficiency
    private $columns = null;
    private $decimalColumns = [];

    /**
     * Preload columns up front
     *
     * @param $statement
     * @param $databaseConnection
     */
    public function __construct($statement, $databaseConnection) {
        parent::__construct($statement, $databaseConnection);
        $this->getColumns();
    }


    /**
     * Return the columns for a MySQL Result Set
     *
     * @return ResultSetColumn[]
     */
    public function getColumns() {


        if (!$this->columns) {
            $this->columns = array();
            for ($i = 0; $i < $this->statement->columnCount(); $i++) {
                try {
                    $columnMeta = $this->statement->getColumnMeta($i);

                    if ($columnMeta) {

                        // Fall back to varchar
                        if (isset($columnMeta["native_type"])){
                            $columnType = self::NATIVE_SQL_MAPPINGS[$columnMeta["native_type"]] ?? TableColumn::SQL_VARCHAR;
                            $lengthDivisor = self::LENGTH_COLUMN_DIVISORS[$columnMeta["native_type"]] ?? null;
                        } else { // Where we have a non-native PHP type
                            $columnType = TableColumn::SQL_LONGBLOB;
                            $lengthDivisor = null;
                        }


                        if ($columnType == TableColumn::SQL_BLOB && $columnMeta["len"] > 300000)
                            $columnType = TableColumn::SQL_LONGBLOB;

                        // Record decimal types for later
                        if ($columnType == TableColumn::SQL_REAL || $columnType == TableColumn::SQL_DOUBLE || $columnType == TableColumn::SQL_DECIMAL || $columnType == TableColumn::SQL_FLOAT) {
                            $this->decimalColumns[] = $columnMeta["name"];
                        }


                        $this->columns[] = new ResultSetColumn($columnMeta["name"], $columnType,
                            $lengthDivisor ? $columnMeta["len"] / $lengthDivisor : null, $lengthDivisor ? $columnMeta["precision"] : null);

                    } else {
                        $this->columns[] = new ResultSetColumn("column" . ($i + 1), TableColumn::SQL_VARCHAR);
                    }

                } catch (\PDOException $e) {
                }
            }
        }
        return $this->columns;
    }

    /**
     * Override next row to handle numeric values correctly
     *
     * @return mixed|void
     */
    public function nextRow() {
        $row = parent::nextRow();

        // If any decimal columns, ensure we map them back to the correct PHP type
        // GETS ROUND A HOPEFULLY TEMPORARY WEIRDNESS WITH PDO/MYSQL
        if ($this->decimalColumns && $row) {
            foreach ($this->decimalColumns as $decimalColumn) {
                $row[$decimalColumn] = doubleval($row[$decimalColumn]);
            }
        }

        return $row;
    }


}