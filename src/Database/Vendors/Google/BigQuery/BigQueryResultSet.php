<?php

namespace Kinikit\Persistence\Database\Vendors\Google\BigQuery;


use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Iterator\ItemIterator;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\ResultSet\BaseResultSet;

class BigQueryResultSet extends BaseResultSet {

    const BIG_QUERY_MAPPINGS = [
        "STRING" => TableColumn::SQL_VARCHAR,
        "INTEGER" => TableColumn::SQL_INT,
        "FLOAT" => TableColumn::SQL_FLOAT,
        "NUMERIC" => TableColumn::SQL_DECIMAL,
        "DATE" => TableColumn::SQL_DATE,
        "TIME" => TableColumn::SQL_TIME,
        "DATETIME" => TableColumn::SQL_DATE_TIME,
        "TIMESTAMP" => TableColumn::SQL_TIMESTAMP,
        "BYTES" => TableColumn::SQL_BLOB,
        "JSON" => TableColumn::SQL_JSON,
        "RECORD" => TableColumn::SQL_JSON
    ];

    /**
     * @var ItemIterator
     */
    private $rows;

    /**
     * @var ResultSetColumn[]
     */
    private $columns;

    /**
     * @param QueryResults $queryResults
     */
    public function __construct(QueryResults $queryResults) {
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
        $value = $this->rows->current();

        if ($value) {
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
        // TODO: Implement close() method.
    }

    /**
     * @param QueryResults $queryResults
     * @return ResultSetColumn[]
     */
    private function initColumns(QueryResults $queryResults) {

        $columns = [];
        $rawColumns = $queryResults->info()["schema"]["fields"];

        foreach ($rawColumns as $rawColumn) {
            $name = $rawColumn["name"];
            $rawType = $rawColumn["type"];

            $type = self::BIG_QUERY_MAPPINGS[$rawType];

            $columns[] = new ResultSetColumn($name, $type);
        }

        return $columns;
    }

    private function normaliseRow(array $row) {

        $out = array_map(function ($val) {
            if (is_object($val) && method_exists($val, '__toString')) {
                return (string)$val;
            }
            if ($val instanceof \DateTimeInterface) {
                return $val->format('Y-m-d H:i:s');
            }
            return $val;
        }, $row);

        return $out;

    }

}