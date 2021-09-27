<?php


namespace Kinikit\Persistence\Database\Generator;


use Kinikit\Core\Util\ObjectArrayUtils;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableMetaData;

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

            list($columnName, $line) = $this->createColumnDefinitionString($column, $databaseConnection);

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

        $sql .= "\n);";

        return $sql;

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
        $clauses = [];

        // Now loop through original columns and process any matches / missing in modified
        foreach ($originalColumns as $name => $originalColumn) {

            // Check if modification required
            if (isset($modifiedColumns[$name])) {
                $modifiedColumn = $modifiedColumns[$name];

                // If a change is required, make it
                if (($originalColumn->getType() != $modifiedColumn->getType()) ||
                    ($originalColumn->getLength() != $modifiedColumn->getLength()) ||
                    ($originalColumn->getPrecision() != $modifiedColumn->getPrecision()) ||
                    ($originalColumn->isNotNull() != $modifiedColumn->isNotNull())) {

                    list($columnName, $line) = $this->createColumnDefinitionString($modifiedColumn, $databaseConnection);
                    $clauses[] = "ALTER TABLE $tableName MODIFY COLUMN $line;";
                }

                // Unset modified columns
                unset($modifiedColumns[$name]);

            } // Otherwise it's a drop
            else {
                $clauses[] = "ALTER TABLE $tableName DROP COLUMN $name;";
            }
        }


        // Now loop through the remaining modified columns and treat as adds.
        foreach ($modifiedColumns as $modifiedColumn) {
            list($columnName, $line) = $this->createColumnDefinitionString($modifiedColumn, $databaseConnection);
            $clauses[] = "ALTER TABLE $tableName ADD COLUMN $line;";
        }


        return join("\n", $clauses);

    }

    /**
     * Create a column definition string
     *
     * @param $databaseConnection
     * @param $column
     * @return array
     */
    private function createColumnDefinitionString($column, $databaseConnection): array {
        $columnName = $databaseConnection->escapeColumn($column->getName());

        $line = $columnName . " " . $column->getType();
        if ($column->getLength()) {
            $line .= "(" . $column->getLength();
            if ($column->getPrecision()) {
                $line .= "," . $column->getPrecision();
            }
            $line .= ")";
        }
        if ($column->isNotNull())
            $line .= " NOT NULL";


        return array($columnName, $line);
    }


}

