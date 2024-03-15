<?php

namespace Kinikit\Persistence\Database\Vendors\SQLite3;

use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\DDL\TableAlteration;
use Kinikit\Persistence\Database\Exception\SQLException;
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
                    $pks[] = '"' . $column->getName() . '"';
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
     * Generate the SQL for an alter table statement
     * SQLite doesn't support alter columns, so we create a new table to spec
     * and insert the data.
     *
     * @param TableAlteration $tableAlteration
     * @param ?DatabaseConnection $connection
     * @return string
     */
    public function generateModifyTableSQL(TableAlteration $tableAlteration, ?DatabaseConnection $connection = null): string {

        $sql = "";

        // In the case of modifying columns, the table must be regenerated
        // 1. Rename table
        // 2. Create new table
        // 3. Copy data
        // 4. Drop old table
        if ($tableAlteration->getColumnAlterations()->getModifyColumns()) {
            $newMetaData = $tableAlteration->getNewTableMetaData();
            $tableName = $tableAlteration->getTableName();
            $newTableName = $tableAlteration->getNewTableName();

            $insertColumnNames = array_map(fn($col) => $col->getName(), $newMetaData->getColumns());
            $selectColumnNames = array_map(fn($col) => $col instanceof UpdatableTableColumn ? $col->getPreviousName() : $col->getName(), $newMetaData->getColumns());

            // 1.
            $connection->execute("DROP TABLE IF EXISTS __$tableName");
            $connection->execute("ALTER TABLE $tableName RENAME TO __$tableName;");

            // 2.
            try {
                $connection->executeScript($this->generateTableCreateSQL($newMetaData));

                // 3.
                $insertSql = "INSERT INTO $newTableName (" . join(",", $insertColumnNames) . ") SELECT " . join(",", $selectColumnNames) . " FROM __$tableName;";
                $connection->execute($insertSql);

                // 4.
                $connection->execute("DROP TABLE __$tableName;");

                $sql = "DROP TABLE __$tableName";
            } catch (SQLException $e) {
                // Reset the table if an error occurs
                $this->execute("DROP TABLE IF EXISTS $tableName");
                $this->execute("ALTER TABLE __$tableName RENAME TO $tableName");
                throw ($e);
            }

        } else {

            // Otherwise write create/drop statements
            $tableName = $tableAlteration->getTableName();

            $columnAlterations = $tableAlteration->getColumnAlterations();
            foreach ($columnAlterations->getAddColumns() as $col) {
                $columnString = $this->createColumnDefinitionString($col);
                $sql .= "ALTER TABLE $tableName ADD COLUMN {$columnString};";
            }

            foreach ($columnAlterations->getDropColumns() as $col) {
                $sql .= "ALTER TABLE $tableName DROP COLUMN $col;";
            }

            // Create & drop indexes
            $indexAlterations = $tableAlteration->getIndexAlterations();
            foreach ($indexAlterations->getAddIndexes() as $index) {
                $indexName = $index->getName();
                $indexCols = join(",", array_map(fn($col) => $col->getName(), $index->getColumns()));
                $sql .= "CREATE INDEX $indexName ON $tableName ($indexCols);";
            }

            // For modify, drop and recreate
            foreach ($indexAlterations->getModifyIndexes() as $index) {
                $sql .= "DROP INDEX {$index->getName()};";

                $indexName = $index->getName();
                $indexCols = join(",", array_map(fn($col) => $col->getName(), $index->getColumns()));
                $sql .= "CREATE INDEX $indexName ON $tableName ($indexCols);";

            }

            foreach ($indexAlterations->getDropIndexes() as $index)
                $sql .= "DROP INDEX {$index->getName()};";


            // Rename the table if required
            if ($newTableName = $tableAlteration->getNewTableName())
                $sql .= "ALTER TABLE $tableName RENAME TO $newTableName;";

        }


        return $sql;
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