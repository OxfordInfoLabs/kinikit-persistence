<?php


namespace Kinikit\Persistence\Database\Generator;


use Kinikit\Core\Util\ObjectArrayUtils;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\DDL\ColumnAlterations;
use Kinikit\Persistence\Database\DDL\IndexAlterations;
use Kinikit\Persistence\Database\DDL\TableAlteration;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableColumn;

class TableDDLGenerator {


    /**
     * Generate table create sql using a table meta data object
     *
     * @param TableMetaData $tableMetaData
     * @param DatabaseConnection $databaseConnection
     *
     * @return string
     */
    public function generateTableCreateSQL($tableMetaData, $databaseConnection) {

        $ddlGenerator = $databaseConnection->getDDLManager();
        $sql = $ddlGenerator->generateTableCreateSQL($tableMetaData);

        return $sql;

    }


    /**
     * Generate alter statements by comparing original and modified metadata
     *
     * @param TableMetaData $originalTableMetaData
     * @param TableMetaData $modifiedTableMetaData
     * @param DatabaseConnection $databaseConnection
     * @return string
     */
    public function generateTableModifySQL($originalTableMetaData, $modifiedTableMetaData, $databaseConnection) {

        // Get the table name and any change of name
        $tableName = $originalTableMetaData->getTableName();
        $newTableName = $modifiedTableMetaData->getTableName() == $tableName ? null : $modifiedTableMetaData->getTableName();

        // Index both sets of columns
        $originalColumns = ObjectArrayUtils::indexArrayOfObjectsByMember("name", $originalTableMetaData->getColumns());
        $modifiedColumns = ObjectArrayUtils::indexArrayOfObjectsByMember("name", $modifiedTableMetaData->getColumns());

        // Initialise arrays
        $addColumns = [];
        $modifyColumns = [];
        $dropColumns = [];

        $addIndexes = [];
        $modifyIndexes = [];
        $dropIndexes = [];

        $originalPKColumns = [];
        $modifiedPKColumns = [];

        // Loop through the modified columns to identify changes
        foreach ($modifiedColumns as $name => $modifiedColumn) {

            if ($modifiedColumn instanceof UpdatableTableColumn){
                $originalColumn =  $originalColumns[$modifiedColumn->getPreviousName()] ?? $originalColumns[$name] ?? null;
            } else {
                $originalColumn = $originalColumns[$name] ?? null;
            }


            if ($originalColumn) {

                // Deal with whether we have a change in primary key
                if ($modifiedColumn->isPrimaryKey() && !$originalColumn->isPrimaryKey())
                    $modifiedPKColumns[] = $modifiedColumn;

                if ($originalColumn->isPrimaryKey())
                    $originalPKColumns[] = $originalColumn;


                // If a change is required, make it
                if (($originalColumn->getName() != $modifiedColumn->getName()) ||
                    ($originalColumn->getType() != $modifiedColumn->getType()) ||
                    ($originalColumn->getLength() != $modifiedColumn->getLength()) ||
                    ($originalColumn->getPrecision() != $modifiedColumn->getPrecision()) ||
                    ($originalColumn->isNotNull() != $modifiedColumn->isNotNull())) {

                    $modifyColumns[] = $modifiedColumn;

                }

                // Remove the original column from the list
                unset($originalColumns[$originalColumn->getName()]);


            } else {
                $addColumns[] = $modifiedColumn;

                if ($modifiedColumn->isPrimaryKey())
                    $modifiedPKColumns[] = $modifiedColumn;
            }


        }

        // Any remaining columns will be dropped
        foreach ($originalColumns as $name => $column) {
            $dropColumns[] = $name;
        }

        // Check if we have a new primary key
        $primaryKeyColumns = $originalPKColumns == $modifiedPKColumns ? null : $modifiedPKColumns;

        // Now to deal with indexes
        // Get the original and modified indexes.
        $originalIndexes = $originalTableMetaData->getIndexes();
        $modifiedIndexes = $modifiedTableMetaData->getIndexes();

        // Loop through modified indexes, drop and create accordingly
        foreach ($modifiedIndexes as $key => $index) {

            // See if it already exists
            if (isset($originalIndexes[$key])) {

                // Check if columns are different
                if ($index->getColumns() != $originalIndexes[$key]->getColumns()) {
                    $modifyIndexes[] = $index;
                }
                unset($originalIndexes[$key]);

            } else {
                $addIndexes[] = $index;
            }
        }

        // Any remaining indexes are dropped
        foreach ($originalIndexes as $key => $index) {
            $dropIndexes[] = $index;
        }

        $columnAlterations = new ColumnAlterations($addColumns, $modifyColumns, $dropColumns);
        $indexAlterations = new IndexAlterations($primaryKeyColumns, $addIndexes, $modifyIndexes, $dropIndexes);
        $tableAlteration = new TableAlteration($tableName, $newTableName, $columnAlterations, $indexAlterations);

        $ddlGenerator = $databaseConnection->getDDLManager();
        $sql = $ddlGenerator->generateModifyTableSQL($tableAlteration, $databaseConnection);

        return $sql;

    }


    /**
     * Generate table drop SQL
     *
     * @param string $tableName
     * @return string
     */
    public function generateTableDropSQL($tableName) {
        return "DROP TABLE $tableName";
    }

}

