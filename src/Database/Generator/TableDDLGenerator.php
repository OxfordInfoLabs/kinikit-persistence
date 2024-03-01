<?php


namespace Kinikit\Persistence\Database\Generator;


use Kinikit\Core\Logging\Logger;
use Kinikit\Core\Util\ObjectArrayUtils;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;

class TableDDLGenerator {


    /**
     * Generate table create sql using a table meta data object
     *
     * @param TableMetaData $tableMetaData
     * @param DatabaseConnection
     */
    public function generateTableCreateSQL($tableMetaData, $databaseConnection) {

        $sql = "CREATE TABLE {$tableMetaData->getTableName()} (\n";

        $columnLines = array();
        $pks = array();
        foreach ($tableMetaData->getColumns() as $column) {

            list($columnName, $line) = $this->createColumnDefinitionString($column, $databaseConnection, true);

            if ($column->isPrimaryKey()) {
                if ($column->isAutoIncrement())
                    $line .= ' PRIMARY KEY';
                else
                    $pks[] = $columnName;
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
     * Generate alter statements by comparing original and modified metadata
     *
     * @param TableMetaData $originalTableMetaData
     * @param TableMetaData $modifiedTableMetaData
     * @param DatabaseConnection $databaseConnection
     */
    public function generateTableModifySQL($originalTableMetaData, $modifiedTableMetaData, $databaseConnection) {

        $tableName = $originalTableMetaData->getTableName();

        // Index both sets of columns
        $originalColumns = ObjectArrayUtils::indexArrayOfObjectsByMember("name", $originalTableMetaData->getColumns());
        $modifiedColumns = ObjectArrayUtils::indexArrayOfObjectsByMember("name", $modifiedTableMetaData->getColumns());

        // Initialise clauses
        $statements = [];
        $clauses = [];

        // Now loop through original columns and process any matches / missing in modified
        $originalPKColumnNames = [];
        $modifiedPKColumnNames = [];
        foreach ($modifiedColumns as $name => $modifiedColumn) {

            $nameChangeRequired = ($modifiedColumn instanceof UpdatableTableColumn) && $modifiedColumn->getPreviousName();

            // Calculate the original column as required
            $originalColumn =
                $originalColumns[$name] ??
                ($nameChangeRequired ? $originalColumns[$modifiedColumn->getPreviousName()] ?? null : null);


            // Check if modification required
            if ($originalColumn) {

                // If original column was part of PK, add it in.
                if ($originalColumn->isPrimaryKey())
                    $originalPKColumnNames[] = $originalColumn->getName();

                // If a change is required, make it
                if (($originalColumn->getType() != $modifiedColumn->getType()) ||
                    ($originalColumn->getLength() != $modifiedColumn->getLength()) ||
                    ($originalColumn->getPrecision() != $modifiedColumn->getPrecision()) ||
                    ($originalColumn->isNotNull() != $modifiedColumn->isNotNull()) ||
                    (($modifiedColumn instanceof UpdatableTableColumn) && $modifiedColumn->getPreviousName())) {

                    list($columnName, $line) = $this->createColumnDefinitionString($modifiedColumn, $databaseConnection);

                    $clauses[] = ($nameChangeRequired ? "CHANGE" : "MODIFY") . " COLUMN $line";
                }

                // Unset original columns
                unset($originalColumns[$originalColumn->getName()]);

            } // Otherwise it's an add
            else {
                list($columnName, $line) = $this->createColumnDefinitionString($modifiedColumn, $databaseConnection);
                $clauses[] = "ADD COLUMN $line";


            }

            if ($modifiedColumn->isPrimaryKey())
                $modifiedPKColumnNames[] = $modifiedColumn->getName();

        }


        // Now loop through the remaining modified columns and treat as adds.
        foreach ($originalColumns as $name => $originalColumn) {
            if ($originalColumn->isPrimaryKey())
                $originalPKColumnNames[] = $originalColumn->getName();

            $clauses[] = "DROP COLUMN " . $databaseConnection->escapeColumn($name);
        }


        // Now check if pk has changed and adjust as appropriate
        if ($originalPKColumnNames !== $modifiedPKColumnNames) {
            $clauses[] = "DROP PRIMARY KEY";
            $modifiedPKColumnNames = array_map(function ($columnName) use ($databaseConnection) {
                return $databaseConnection->escapeColumn($columnName);
            }, $modifiedPKColumnNames);
            $clauses[] = "ADD PRIMARY KEY (" . join(", ", $modifiedPKColumnNames) . ")";
        }

        if (sizeof($clauses)) {
            $statements[] = "ALTER TABLE $tableName " . join(", ", $clauses);
        }


        // Grab original and modified indexes.
        $originalIndexes = $originalTableMetaData->getIndexes();
        $modifiedIndexes = $modifiedTableMetaData->getIndexes();

        // Loop through modified indexes, drop and create accordingly
        foreach ($modifiedIndexes as $key => $index) {
            if (isset($originalIndexes[$key])) {
                // If columns differ from original columns
                if ($index->getColumns() != $originalIndexes[$key]->getColumns()) {
                    $statements[] = "DROP INDEX $key ON $tableName";
                    $statements[] = $this->generateCreateIndexSQL($index, $tableName);
                }
                unset($originalIndexes[$key]);
            } else {
                $statements[] = $this->generateCreateIndexSQL($index, $tableName);
            }
        }

        // Loop through any dangling originals and drop
        foreach ($originalIndexes as $key => $index) {
            $statements[] = "DROP INDEX $key ON $tableName";
        }


        // Return statements
        return sizeof($statements) ? join(";", $statements) . ";" : "";


    }


    /**
     * Generate table drop SQL
     *
     * @param string $tableName
     */
    public function generateTableDropSQL($tableName) {
        return "DROP TABLE $tableName";
    }

    /**
     * Create a column definition string
     *
     * @param $databaseConnection
     * @param $column
     * @return array
     */
    private function createColumnDefinitionString($column, $databaseConnection, $create = false): array {
        $columnName = $databaseConnection->escapeColumn($column->getName());

        if ($column instanceof UpdatableTableColumn && $column->getPreviousName() && !$create) {
            $line = $databaseConnection->escapeColumn($column->getPreviousName()) . " " . $columnName;
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
            $line .= ' AUTOINCREMENT';

        if ($column->isNotNull())
            $line .= " NOT NULL";

        if ($column->getDefaultValue())
            $line .= " DEFAULT " . (is_numeric($column->getDefaultValue()) ? $column->getDefaultValue() : "'" . $column->getDefaultValue() . "'");


        return array($columnName, $line);
    }

    /**
     * @param $index
     * @param TableMetaData $tableMetaData
     * @param $sql
     * @return string
     */
    private function generateCreateIndexSQL($index, $tableName) {
        $columnDescriptors = [];
        foreach ($index->getColumns() as $column) {
            $columnDescriptors[] = $column->getName() . ($column->getMaxBytesToIndex() > 0 ? "(" . $column->getMaxBytesToIndex() . ")" : "");
        }
        return "CREATE INDEX {$index->getName()} ON $tableName (" . join(",", $columnDescriptors) . ")";
    }


}

