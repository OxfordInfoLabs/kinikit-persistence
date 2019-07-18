<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Dialects;
use Kinikit\Persistence\Database\Connection\TableColumn;

/**
 * Implementation of the SQL dialect for MSSQL
 *
 * @author mark
 *
 */
class MSSQLDialect implements ORMSQLDialect {

    /*
     * Be prescriptive about the columns and also make sure we cast any varchar
     * columns to prevent truncation issues. @param $ormTableInfo ORMTableInfo
     */
    public function generateAllColumnSelectClause($ormTableInfo) {

        $tableMetaData = $ormTableInfo->getTableMetaData();

        if ($ormTableInfo->getFieldColumnMappings()) {
            $columns = array_values($ormTableInfo->getFieldColumnMappings());
        } else {
            $columns = $tableMetaData->getColumns();
        }

        $columnStrings = array();

        // Add column strings to array
        foreach ($columns as $column) {
            if ($column->getType() == TableColumn::SQL_VARCHAR && $column->getLength() > 255) {
                $columnStrings [] = "CONVERT (text, [" . $column->getName() . "]) [" . $column->getName() . "]";
            } else {
                $columnStrings [] = "[" . $column->getName() . "]";
            }
        }

        return "SELECT " . join(",", $columnStrings);

    }

    /**
     * Generate a distinct clause for a set of columns
     *
     * @param $columns
     * @return mixed
     */
    public function generateDistinctClause($columns) {
        return "DISTINCT CAST(" . join(" AS VARCHAR) + '||' +  CAST(", $columns)." AS VARCHAR)";
    }

}

?>