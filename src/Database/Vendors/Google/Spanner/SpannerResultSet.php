<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Google\Cloud\Spanner\Result;
use Google\Cloud\Spanner\V1\TypeCode;
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
     * @var Result
     */
    private $queryResults;

    /**
     * @var \Generator
     */
    private $rows;

    /**
     * @var ResultSetColumn[]|null
     */
    private $columns = null;

    /**
     * @param Result $queryResults
     */
    public function __construct(Result $queryResults) {
        $this->queryResults = $queryResults;
        $this->rows = $queryResults->rows();
    }

    /**
     * Get the column names for a results set
     *
     * @return string[]
     */
    public function getColumnNames() {
        return array_map(fn($col) => $col->getName(), $this->getColumns());
    }

    /**
     * @return ResultSetColumn[]
     */
    public function getColumns() {
        if ($this->columns === null) {
            $this->ensureMetadataLoaded();
        }
        return $this->columns ?? [];
    }

    /**
     * Get the next row
     *
     * @return mixed
     */
    public function nextRow() {
        if ($this->rows->valid()) {
            $value = $this->rows->current();

            // Lazy load metadata/columns on first row fetch if not already done
            if ($this->columns === null) {
                $this->extractColumnsFromMetadata();
            }

            $this->rows->next();
            return $this->normaliseRow($value);
        }

        return false;
    }

    /**
     * Close result set
     *
     * @return void
     */
    public function close() {
        $this->rows = null;
        $this->columns = [];
    }

    /**
     * Ensure metadata is available if getColumns() is called before nextRow()
     */
    private function ensureMetadataLoaded() {
        if ($this->rows->valid()) {
            // Accessing current() triggers row evaluation, populating metadata on Result
            $this->rows->current();
            $this->extractColumnsFromMetadata();
        }
    }

    /**
     * Extract metadata fields populated on the Google Result object
     */
    private function extractColumnsFromMetadata() {
        $metadata = $this->queryResults->metadata();

        if (!$metadata || !isset($metadata['rowType']['fields'])) {
            $this->columns = [];
            return;
        }

        $fields = $metadata['rowType']['fields'];
        $columns = [];

        foreach ($fields as $field) {
            $name = $field["name"] ?? '';
            $typeCode = $field["type"]["code"] ?? null;
            $type = self::SPANNER_MAPPINGS[$typeCode] ?? TableColumn::SQL_VARCHAR;

            $columns[] = new ResultSetColumn($name, $type);
        }

        $this->columns = $columns;
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