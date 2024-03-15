<?php

namespace Kinikit\Persistence\Database\Vendors\PostgreSQL;

use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\DDL\TableAlteration;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;

class PostgreSQLDDLManager implements DDLManager {

    const SQL_TYPE_MAPPINGS = [
        TableColumn::SQL_VARCHAR => "VARCHAR",
        TableColumn::SQL_TINYINT => "SMALLINT",
        TableColumn::SQL_SMALLINT => "SMALLINT",
        TableColumn::SQL_INT => "INT",
        TableColumn::SQL_INTEGER => "INTEGER",
        TableColumn::SQL_BIGINT => "BIGINT",
        TableColumn::SQL_FLOAT => "FLOAT",
        TableColumn::SQL_DOUBLE => "DOUBLE PRECISION",
        TableColumn::SQL_REAL => "REAL",
        TableColumn::SQL_DECIMAL => "DECIMAL",
        TableColumn::SQL_DATE => "DATE",
        TableColumn::SQL_TIME => "TIME",
        TableColumn::SQL_DATE_TIME => "TIMESTAMP",
        TableColumn::SQL_TIMESTAMP => "TIMESTAMP",
        TableColumn::SQL_BLOB => "TEXT",
        TableColumn::SQL_LONGBLOB => "BYTEA",
        TableColumn::SQL_UNKNOWN => "UNKNOWN",
        TableColumn::SQL_VECTOR => "VECTOR"
    ];

    /**
     * Generate the create table sql
     *
     * @param TableMetaData $tableMetaData
     * @return string
     */
    public function generateTableCreateSQL(TableMetaData $tableMetaData): string {
        $sql = "CREATE TABLE {$tableMetaData->getTableName()} (\n";

        $columnLines = [];
        $pks = [];

        foreach ($tableMetaData->getColumns() as $column) {

            $line = $this->createColumnDefinitionString($column, true);

            if ($column->isPrimaryKey()) {
                if ($column->isAutoIncrement())
                    $line .= ' PRIMARY KEY';
                else
                    $pks[] = '"' . $column->getName() . '"';
            }

            $columnLines[] = $line;
        }

        $sql .= join(",\n", $columnLines);

        if (sizeof($pks) > 0) {
            $sql .= ",\nPRIMARY KEY (" . join(",", $pks) . ")";
        }

        $sql .= "\n)";

        // Add all column index clauses
        foreach ($tableMetaData->getIndexes() as $index) {
            $sql .= ";" . $this->generateCreateIndexSQL($index, $tableMetaData->getTableName());
        }

        return $sql . ";";
    }

    /**
     * Generate the alter table sql
     *
     * @param TableAlteration $tableAlteration
     * @param ?DatabaseConnection $connection
     * @return string
     */
    public function generateModifyTableSQL(TableAlteration $tableAlteration, ?DatabaseConnection $connection = null): string {

        $tableName = $tableAlteration->getTableName();

        $alterTable = "ALTER TABLE $tableName";
        $statements = [];

        // Column Modifications
        $columnAlterations = $tableAlteration->getColumnAlterations();
        foreach ($columnAlterations->getAddColumns() as $col) {
            $statements[] = $alterTable . " ADD COLUMN " . $this->createColumnDefinitionString($col);
        }

        foreach ($columnAlterations->getModifyColumns() as $col) {
            if ($col instanceof UpdatableTableColumn)
                $statements[] = $alterTable . " RENAME COLUMN \"{$col->getPreviousName()}\" TO \"{$col->getName()}\"";

            $statements[] = $alterTable . " ALTER COLUMN " . $this->createAlterColumnDefinitionString($col, $tableName);
        }

        foreach ($columnAlterations->getDropColumns() as $col) {
            $statements[] = $alterTable . " DROP COLUMN \"$col\"";
        }

        $indexAlterations = $tableAlteration->getIndexAlterations();

        // Primary keys
        if ($pks = $indexAlterations->getNewPrimaryKeyColumns()) {
            $pkName = $connection->query("SELECT conname AS primary_key
FROM pg_constraint
WHERE contype = 'p'
  AND connamespace = 'public'::regnamespace
  AND conrelid::regclass::text = '$tableName';")->fetchAll()[0]["primary_key"];

            $pkCols = join(",", array_map(fn($col) => "\"$col\"", $pks));

            $statements[] = $alterTable . " DROP CONSTRAINT $pkName";
            $statements[] = $alterTable . " ADD PRIMARY KEY ($pkCols)";
        }

        // Indexes
        foreach ($indexAlterations->getAddIndexes() as $index) {
            $statements[] = $this->generateCreateIndexSQL($index, $tableName);
        }

        foreach ($indexAlterations->getModifyIndexes() as $index) {
            $statements[] = "DROP INDEX {$index->getName()}";
            $statements[] = $this->generateCreateIndexSQL($index, $tableName);
        }

        foreach ($indexAlterations->getDropIndexes() as $index) {
            $statements[] = "DROP INDEX {$index->getName()}";
        }


        $sql = join(";", $statements);
        $sql .= ";";

        return $sql;
    }

    /**
     * Generate the drop table sql
     *
     * @param string $tableName
     * @return string
     */
    public function generateTableDropSQL(string $tableName): string {
        return "DROP TABLE $tableName;";
    }

    /**
     * Create a column definition string
     *
     * @param TableColumn $column
     * @return string
     */
    private function createColumnDefinitionString(TableColumn $column, $create = false): string {

        $column = $this->mapToPostgreSQLColumn($column);

        $columnName = "\"{$column->getName()}\"";

        if ($column instanceof UpdatableTableColumn && $column->getPreviousName() && !$create) {
            $line = "\"{$column->getPreviousName()}\"" . " " . $columnName;
        } else {
            $line = $columnName;
        }

        $line .= " " . $column->getType();

        if ($column->getLength()) {
            $line .= "(" . $column->getLength();
            if ($column->getPrecision()) {
                $line .= "," . $column->getPrecision();
            }
            $line .= ")";
        }

        if (!$create && $column->isAutoIncrement())
            $line .= " AUTOINCREMENT";

        if ($column->isNotNull())
            $line .= " NOT NULL";

        if ($column->getDefaultValue())
            $line .= " DEFAULT " . (is_numeric($column->getDefaultValue()) ? $column->getDefaultValue() : "'" . $column->getDefaultValue() . "'");

        return $line;
    }

    /**
     * Create a column definition string
     *
     * @param TableColumn $column
     * @return string
     */
    private function createAlterColumnDefinitionString(TableColumn $column, $tableName): string {

        $column = $this->mapToPostgreSQLColumn($column);

        $line = '"' . $column->getName() . "\" TYPE " . $column->getType();

        if ($column->isNotNull())
            $line .= ";ALTER TABLE $tableName ALTER COLUMN \"{$column->getName()}\" SET NOT NULL";

        return $line;
    }

    /**
     * Create an index definition string
     *
     * @param TableIndex $index
     * @param string $tableName
     * @return string
     */
    private function generateCreateIndexSQL(TableIndex $index, string $tableName) {
        $columnDescriptors = [];
        foreach ($index->getColumns() as $column) {
            $columnDescriptors[] = $column->getName() . ($column->getMaxBytesToIndex() > 0 ? "(" . $column->getMaxBytesToIndex() . ")" : "");
        }
        return "CREATE INDEX {$index->getName()} ON $tableName (" . join(",", $columnDescriptors) . ")";
    }

    /**
     * Returns a column with PostgreSQL types
     *
     * @param TableColumn $column
     * @return TableColumn
     */
    private function mapToPostgreSQLColumn(TableColumn $column): TableColumn {

        $type = self::SQL_TYPE_MAPPINGS[$column->getType()];

        if ($column->isAutoIncrement())
            $type = "BIGSERIAL";

        return new TableColumn($column->getName(), $type, $column->getLength(), $column->getPrecision(), $column->getDefaultValue(), $column->isPrimaryKey(), false, $column->isNotNull());

    }
}