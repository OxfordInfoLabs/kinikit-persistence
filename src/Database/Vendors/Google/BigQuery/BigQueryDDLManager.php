<?php

namespace Kinikit\Persistence\Database\Vendors\Google\BigQuery;

use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\DDL\TableAlteration;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableMetaData;

class BigQueryDDLManager implements DDLManager {

    const SQL_TYPE_MAPPINGS = [
        ResultSetColumn::SQL_VARCHAR => "STRING",
        ResultSetColumn::SQL_TINYINT => "INT64",
        ResultSetColumn::SQL_SMALLINT => "INT64",
        ResultSetColumn::SQL_INT => "INT64",
        ResultSetColumn::SQL_INTEGER => "INT64",
        ResultSetColumn::SQL_BIGINT => "INT64",
        ResultSetColumn::SQL_FLOAT => "FLOAT64",
        ResultSetColumn::SQL_DOUBLE => "FLOAT64",
        ResultSetColumn::SQL_REAL => "FLOAT64",
        ResultSetColumn::SQL_DECIMAL => "NUMERIC",
        ResultSetColumn::SQL_DATE => "DATE",
        ResultSetColumn::SQL_TIME => "TIME",
        ResultSetColumn::SQL_DATE_TIME => "DATETIME",
        ResultSetColumn::SQL_TIMESTAMP => "TIMESTAMP",
        ResultSetColumn::SQL_BLOB => "BYTES",
        ResultSetColumn::SQL_LONGBLOB => "BYTES",
        ResultSetColumn::SQL_UNKNOWN => "STRING",
        ResultSetColumn::SQL_VECTOR => "ARRAY<FLOAT64>"
    ];

    public function generateTableCreateSQL(TableMetaData $tableMetaData): string {
        $sql = "CREATE TABLE {$tableMetaData->getTableName()} (\n";

        $columnLines = [];

        foreach ($tableMetaData->getColumns() as $column) {
            $line = "`" . $column->getName() . "` " . self::SQL_TYPE_MAPPINGS[$column->getType()];

            if ($column->isNotNull()) {
                $line .= " NOT NULL";
            }

            $columnLines[] = $line;
        }

        $sql .= join(",\n", $columnLines);

        $sql .= "\n)";

        $indexes = $tableMetaData->getIndexes();

        // For indexes, we do a CLUSTER BY using the first index
        // as it's the nearest equivalent
        if (!empty($indexes)) {
            $firstIndex = $indexes[0];
            $clusterColumns = implode(", ", array_map(fn($c) => "`$c`", $firstIndex->getColumns()));
            $sql .= " CLUSTER BY " . $clusterColumns;
        }

        $sql .= ";";

        Logger::log($sql, 6);
        return $sql;
    }

    public function generateModifyTableSQL(TableAlteration $tableAlteration, ?DatabaseConnection $connection = null): string {
        $statements = [];
        $tableName = $tableAlteration->getTableName();
        $columnAlterations = $tableAlteration->getColumnAlterations();

        if ($columnAlterations) {
            // 1. SIMPLE ADD
            foreach ($columnAlterations->getAddColumns() as $column) {
                $type = self::SQL_TYPE_MAPPINGS[$column->getType()];
                $statements[] = "ALTER TABLE $tableName ADD COLUMN `" . $column->getName() . "` $type";
            }

            // 2. SIMPLE DROP
            foreach ($columnAlterations->getDropColumns() as $columnName) {
                $statements[] = "ALTER TABLE $tableName DROP COLUMN `" . $columnName . "`";
            }

            if ($columnAlterations->getModifyColumns()) {
                // BigQuery literally doesn't support MODIFY COLUMN for types/names
                // We just ignore or log it.
            }
        }

        return implode(";", $statements) . ";";
    }

    public function generateTableDropSQL(string $tableName): string {
        return "DROP TABLE IF EXISTS $tableName";
    }

}