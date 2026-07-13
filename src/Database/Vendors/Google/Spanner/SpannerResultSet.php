<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;


use Google\Cloud\Spanner\Result;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\ResultSet\BaseResultSet;

class SpannerResultSet extends BaseResultSet {

    const SPANNER_MAPPINGS = [
        "STRING"    => TableColumn::SQL_VARCHAR,
        "INT64"     => TableColumn::SQL_INT,
        "FLOAT64"   => TableColumn::SQL_FLOAT,
        "NUMERIC"   => TableColumn::SQL_DECIMAL,
        "DATE"      => TableColumn::SQL_DATE,
        "TIMESTAMP" => TableColumn::SQL_TIMESTAMP,
        "BYTES"     => TableColumn::SQL_BLOB,
        "JSON"      => TableColumn::SQL_JSON,
        "BOOL"      => TableColumn::SQL_TINYINT
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

        $metadata = $queryResults->metadata();
        $fields = $metadata['rowType']['fields'];

        foreach ($fields as $name => $typeData) {
            // Spanner type data can be a string name or a structured type array for complex types
            $rawType = is_array($typeData) ? ($typeData['type'] ?? 'STRING') : $typeData;

            // Map the uppercase Cloud Spanner Type name to your Kinikit internal format
            $type = self::SPANNER_MAPPINGS[strtoupper($rawType)] ?? TableColumn::SQL_VARCHAR;

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