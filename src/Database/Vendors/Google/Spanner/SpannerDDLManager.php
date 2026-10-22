<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Google\Cloud\Spanner\V1\TypeCode;
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
        ResultSetColumn::SQL_VARCHAR => TypeCode::STRING,
        ResultSetColumn::SQL_TINYINT => TypeCode::INT64,
        ResultSetColumn::SQL_SMALLINT => TypeCode::INT64,
        ResultSetColumn::SQL_INT => TypeCode::INT64,
        ResultSetColumn::SQL_INTEGER => TypeCode::INT64,
        ResultSetColumn::SQL_BIGINT => TypeCode::INT64,
        ResultSetColumn::SQL_FLOAT => TypeCode::FLOAT64,
        ResultSetColumn::SQL_DOUBLE => TypeCode::FLOAT64,
        ResultSetColumn::SQL_REAL => TypeCode::FLOAT64,
        ResultSetColumn::SQL_DECIMAL => TypeCode::NUMERIC,
        ResultSetColumn::SQL_DATE => TypeCode::DATE,
        ResultSetColumn::SQL_TIME => TypeCode::STRING,
        ResultSetColumn::SQL_DATE_TIME => TypeCode::TIMESTAMP,
        ResultSetColumn::SQL_TIMESTAMP => TypeCode::TIMESTAMP,
        ResultSetColumn::SQL_BLOB => TypeCode::STRING,
        ResultSetColumn::SQL_LONGBLOB => TypeCode::STRING,
        ResultSetColumn::SQL_JSON => TypeCode::JSON,
        ResultSetColumn::SQL_UNKNOWN => TypeCode::STRING,
        ResultSetColumn::SQL_VECTOR => TypeCode::PBARRAY
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
        $type = self::SQL_TYPE_MAPPINGS[$column->getType()];
        $line = "`" . $column->getName() . "` " . TypeCode::name($type);

        if ($column->getLength() && !($type === TypeCode::STRING && $column->getLength() === SpannerDatabaseConnection::DEFAULT_STRING_LENGTH)) {
            $line .= "(" . $column->getLength() . ")";
        } else if ($type === TypeCode::STRING) {
            $line .= "(MAX)";
        }

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