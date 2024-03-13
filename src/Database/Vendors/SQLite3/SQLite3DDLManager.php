<?php

namespace Kinikit\Persistence\Database\Vendors\SQLite3;

use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;

class SQLite3DDLManager implements DDLManager {

    /**
     * Generate the SQL for a create table statement
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
                    $pks[] = '"'.$column->getName().'"';
            }
            if ($column->isAutoIncrement()) $line .= ' AUTOINCREMENT';

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
     * Generate the SQL for a drop table statement
     *
     * @param string $tableName
     * @return string
     */
    public function generateTableDropSQL(string $tableName): string {
        return "DROP TABLE $tableName;";
    }

    /**
     * Generate the SQL for an alter table statement
     *
     * @param TableMetaData $originalTableMetaData
     * @param TableMetaData $newTableMetaData
     * @return string
     */
    public function generateAlterTableSQL(TableMetaData $originalTableMetaData, TableMetaData $newTableMetaData): string {
        // TODO: Implement generateAlterTableSQL() method.
    }

    /**
     * Create a column definition string
     *
     * @param TableColumn $column
     * @return string
     */
    private function createColumnDefinitionString(TableColumn $column, $create = false): string {
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
}