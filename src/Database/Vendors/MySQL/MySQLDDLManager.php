<?php

namespace Kinikit\Persistence\Database\Vendors\MySQL;

use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\DDL\TableAlteration;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;

class MySQLDDLManager implements DDLManager {


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
                    $pks[] = '`' . $column->getName() . '`';
            }
            if ($column->isAutoIncrement()) $line .= ' AUTO_INCREMENT';

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

        $sql = "";
        $tableName = $tableAlteration->getTableName();

        // Generate alter table statements
        $columnAlterations = $tableAlteration->getColumnAlterations();
        if ($columnAlterations->getAddColumns() || $columnAlterations->getModifyColumns() || $columnAlterations->getDropColumns()) {

            $statements = [];

            foreach ($columnAlterations->getAddColumns() as $column) {
                $columnDesc = $this->createColumnDefinitionString($column);
                $statements[] = "ADD COLUMN $columnDesc";
            }

            foreach ($columnAlterations->getModifyColumns() as $column) {
                $command = ($column instanceof UpdatableTableColumn && $column->getPreviousName()) ? "CHANGE" : "MODIFY";
                $columnDesc = $this->createColumnDefinitionString($column);
                $statements[] = "$command COLUMN $columnDesc";
            }

            foreach ($columnAlterations->getDropColumns() as $column) {
                $statements[] = "DROP COLUMN `$column`";
            }

            if ($pks = $tableAlteration->getIndexAlterations()->getNewPrimaryKeyColumns()) {
                $pkCols = join(",", array_map(fn($col) => "`$col`", $pks));

                $statements[] = "DROP PRIMARY KEY";
                $statements[] = "ADD PRIMARY KEY ($pkCols)";
            }

            $sql .= "ALTER TABLE $tableName " . join(",", $statements) . ";";

        }


        // Now for the indexes
        $indexAlterations = $tableAlteration->getIndexAlterations();

        foreach ($indexAlterations->getAddIndexes() as $index) {
            $sql .= $this->generateCreateIndexSQL($index, $tableName) . ";";
        }

        foreach ($indexAlterations->getModifyIndexes() as $index) {
            $sql .= "DROP INDEX {$index->getName()} ON $tableName;";
            $sql .= $this->generateCreateIndexSQL($index, $tableName) . ";";
        }

        foreach ($indexAlterations->getDropIndexes() as $index) {
            $sql .= "DROP INDEX {$index->getName()} ON $tableName;";
        }

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
     * @param bool $create
     * @return string
     */
    private function createColumnDefinitionString(TableColumn $column, bool $create = false): string {

        $columnName = "`{$column->getName()}`";

        if ($column instanceof UpdatableTableColumn && $column->getPreviousName() && !$create) {
            $line = "`{$column->getPreviousName()}`" . " " . $columnName;
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
            $line .= " AUTO_INCREMENT";

        if ($column->isNotNull())
            $line .= " NOT NULL";

        if ($column->getDefaultValue())
            $line .= " DEFAULT " . (is_numeric($column->getDefaultValue()) ? $column->getDefaultValue() : "'" . $column->getDefaultValue() . "'");

        return $line;
    }

    /**
     * Create an index definition string
     *
     * @param TableIndex $index
     * @param string $tableName
     * @return string
     */
    private function generateCreateIndexSQL(TableIndex $index, string $tableName): string {
        $columnDescriptors = [];
        foreach ($index->getColumns() as $column) {
            $columnDescriptors[] = $column->getName() . ($column->getMaxBytesToIndex() > 0 ? "(" . $column->getMaxBytesToIndex() . ")" : "");
        }
        return "CREATE INDEX {$index->getName()} ON $tableName (" . join(",", $columnDescriptors) . ")";
    }

}