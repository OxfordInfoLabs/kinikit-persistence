<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\DDL\TableAlteration;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;

class SpannerDDLManager implements DDLManager {

    const SQL_TYPE_MAPPINGS = [
        ResultSetColumn::SQL_VARCHAR => "STRING(MAX)",
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
        ResultSetColumn::SQL_TIME => "STRING(MAX)",
        ResultSetColumn::SQL_DATE_TIME => "TIMESTAMP",
        ResultSetColumn::SQL_TIMESTAMP => "TIMESTAMP",
        ResultSetColumn::SQL_BLOB => "BYTES(MAX)",
        ResultSetColumn::SQL_LONGBLOB => "BYTES(MAX)",
        ResultSetColumn::SQL_UNKNOWN => "STRING(MAX)",
        ResultSetColumn::SQL_VECTOR => "ARRAY<FLOAT64>"
    ];

    /**
     * @param TableMetaData $tableMetaData
     * @return string
     */
    public function generateTableCreateSQL(TableMetaData $tableMetaData): string {
        $sql = "CREATE TABLE `{$tableMetaData->getTableName()}` (\n";

        $columnLines = [];
        $primaryKeys = [];

        foreach ($tableMetaData->getColumns() as $column) {
            $columnLines[] = $this->createColumnDefinitionString($column);

            if ($column->isPrimaryKey()) {
                $primaryKeys[] = '`' . $column->getName() . '`';
            }
        }

        $sql .= implode(",\n", $columnLines);
        $sql .= "\n)";

        if (empty($primaryKeys)) {
            $indexes = $tableMetaData->getIndexes();
            if (!empty($indexes)) {
                $primaryKeys = array_map(fn($c) => "`$c`", $indexes[0]->getColumns());
            } else {
                $firstColumn = current($tableMetaData->getColumns());
                $primaryKeys = ["`" . $firstColumn->getName() . "`"];
            }
        }

        $sql .= " PRIMARY KEY (" . implode(", ", $primaryKeys) . ");";

        foreach ($tableMetaData->getIndexes() as $index) {
            $sql .= ";\n" . $this->generateCreateIndexSQL($index, $tableMetaData->getTableName());
        }

        Logger::log($sql, 6);
        return $sql;
    }

    public function generateModifyTableSQL(TableAlteration $tableAlteration, ?DatabaseConnection $connection = null): string {
        $sql = "";
        $tableName = $tableAlteration->getTableName();
        $columnAlterations = $tableAlteration->getColumnAlterations();

        if ($columnAlterations->getAddColumns() || $columnAlterations->getModifyColumns() || $columnAlterations->getDropColumns()) {
            $statements = [];

            foreach ($columnAlterations->getAddColumns() as $column) {
                $columnDesc = $this->createColumnDefinitionString($column);
                $statements[] = "ALTER TABLE `$tableName` ADD COLUMN $columnDesc";
            }

            foreach ($columnAlterations->getModifyColumns() as $column) {
                if ($column instanceof UpdatableTableColumn && $column->getPreviousName()) {
                    // Does not support renaming - would be a drop / add
                } else {
                    // Otherwise modify the type / constraints directly
                    $columnDesc = $this->createColumnDefinitionString($column);
                    $statements[] = "ALTER TABLE `$tableName` ALTER COLUMN $columnDesc";
                }
            }

            foreach ($columnAlterations->getDropColumns() as $column) {
                $statements[] = "ALTER TABLE `$tableName` DROP COLUMN `$column`";
            }

            if (!empty($statements)) {
                $sql .= implode(";\n", $statements) . ";\n";
            }
        }

        // Manage indexes
        $indexAlterations = $tableAlteration->getIndexAlterations();

        foreach ($indexAlterations->getAddIndexes() as $index) {
            $sql .= $this->generateCreateIndexSQL($index, $tableName) . ";\n";
        }

        foreach ($indexAlterations->getModifyIndexes() as $index) {
            $sql .= "DROP INDEX `{$index->getName()}`;\n";
            $sql .= $this->generateCreateIndexSQL($index, $tableName) . ";\n";
        }

        foreach ($indexAlterations->getDropIndexes() as $index) {
            $sql .= "DROP INDEX `{$index->getName()}`;\n";
        }

        return trim($sql);
    }

    public function generateTableDropSQL(string $tableName): string {
        return "DROP TABLE IF EXISTS $tableName";
    }

    /**
     * Create a column definition string tailored for Cloud Spanner syntax
     *
     * @param TableColumn $column
     * @return string
     */
    private function createColumnDefinitionString(TableColumn $column): string {
        $line = "`" . $column->getName() . "` " . self::SQL_TYPE_MAPPINGS[$column->getType()];

        if ($column->isNotNull()) {
            $line .= " NOT NULL";
        }

        if ($column->getDefaultValue() !== null) {
            $default = $column->getDefaultValue();
            $line .= " DEFAULT (" . (is_numeric($default) ? $default : "'" . str_replace("'", "\\'", $default) . "'") . ")";
        }

        return $line;
    }

    /**
     * Create an index definition string tailored for Cloud Spanner syntax
     *
     * @param TableIndex $index
     * @param string $tableName
     * @return string
     */
    private function generateCreateIndexSQL(TableIndex $index, string $tableName): string {
        $columnDescriptors = [];
        foreach ($index->getColumns() as $column) {
            $columnDescriptors[] = "`{$column->getName()}`";
        }
        return "CREATE INDEX `{$index->getName()}` ON `$tableName` (" . implode(",", $columnDescriptors) . ")";
    }

}