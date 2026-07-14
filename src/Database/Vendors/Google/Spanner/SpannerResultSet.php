<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;


use Google\Cloud\Spanner\Result;
use Google\Cloud\Spanner\V1\TypeCode;
use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\ResultSet\BaseResultSet;

class SpannerResultSet extends BaseResultSet {

    const SPANNER_MAPPINGS = [
        TypeCode::BOOL => TableColumn::SQL_TINYINT,
        TypeCode::INT64 => TableColumn::SQL_INTEGER,
        TypeCode::FLOAT64 => TableColumn::SQL_FLOAT,
        TypeCode::FLOAT32 => TableColumn::SQL_FLOAT,
        TypeCode::TIMESTAMP => TableColumn::SQL_TIMESTAMP,
        TypeCode::DATE => TableColumn::SQL_DATE,
        TypeCode::STRING => TableColumn::SQL_VARCHAR,
        TypeCode::BYTES => TableColumn::SQL_BLOB,
        TypeCode::PBARRAY => TableColumn::SQL_VECTOR,
        TypeCode::STRUCT => TableColumn::SQL_VARCHAR,
        TypeCode::NUMERIC => TableColumn::SQL_DECIMAL,
        TypeCode::JSON => TableColumn::SQL_JSON,
        TypeCode::PROTO => TableColumn::SQL_VARCHAR,
        TypeCode::ENUM => TableColumn::SQL_VARCHAR,
        TypeCode::INTERVAL => TableColumn::SQL_VARCHAR,
        TypeCode::UUID => TableColumn::SQL_VARCHAR,
    ];
    /**
     * @var \Generator
     */
    private $rows;

    /**
     * @var ResultSetColumn[]
     */
    private $columns;

    /**
     * @param Result $queryResults
     */
    public function __construct(Result $queryResults) {
        $this->rows = $queryResults->rows();
        $this->columns = $this->initColumns($queryResults);
    }

    /**
     * Get the column names for a results set
     *
     * @return string[]
     */
    public function getColumnNames() {
        return array_map(fn($col) => $col->getName(), $this->columns);
    }

    /**
     * @return ResultSetColumn[]
     */
    public function getColumns() {
        return $this->columns;
    }

    /**
     * Get the next row
     *
     * @return mixed
     */
    public function nextRow() {
        if ($this->rows->valid()) {
            $value = $this->rows->current();
            $this->rows->next();
            return $this->normaliseRow($value);
        }

        return false;
    }

    /**
     * No need to close anything here
     *
     * @return void
     */
    public function close() {
        $this->rows = null;
        $this->columns = [];
    }

    /**
     * @param Result $queryResults
     * @return ResultSetColumn[]
     */
    private function initColumns(Result $queryResults) {
        $columns = [];

        // Required to initialise metadate
        foreach ($queryResults as $result) {
            break;
        }

        $metadata = $queryResults->metadata();
        $fields = $metadata['rowType']['fields'];

        foreach ($fields as $field) {
            $name = $field["name"];

            $typeCode = $field["type"]["code"];
            $type = self::SPANNER_MAPPINGS[$typeCode];

            $columns[] = new ResultSetColumn($name, $type);
        }

        return $columns;
    }

    private function normaliseRow(array $row) {
        return array_map(function ($val) {
            if (is_object($val)) {
                if (method_exists($val, 'formatAsString')) {
                    return $val->formatAsString();
                }
                if ($val instanceof \DateTimeInterface) {
                    return $val->format('Y-m-d H:i:s');
                }
                if (method_exists($val, '__toString')) {
                    return (string)$val;
                }
            }
            return $val;
        }, $row);
    }

}