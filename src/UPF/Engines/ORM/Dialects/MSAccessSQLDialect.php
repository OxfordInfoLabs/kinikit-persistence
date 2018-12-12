<?php


namespace Kinikit\Persistence\UPF\Engines\ORM\Dialects;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\UPF\Engines\ORM\ORMTableInfo;

/**
 * Implements the SQL Dialect we require for MS Access - mostly working around a bug with VARCHAR types.
 *
 * Class MSAccessSQLDialect
 */
class MSAccessSQLDialect implements ORMSQLDialect {

    /**
     * Generate an all column select clause from ORM Table info for the implemented dialect.
     *
     * @param ORMTableInfo $ormTableInfo
     */
    public function generateAllColumnSelectClause($ormTableInfo) {

        $tableMetaData = $ormTableInfo->getTableMetaData();

        if ($ormTableInfo->getFieldColumnMappings()) {
            $columns = array_values($ormTableInfo->getFieldColumnMappings());
        } else {
            $columns = $tableMetaData->getColumns();
        }

        $columnStrings = array();

        // Remap VARCHAR to text to avoid PHP bug with Access strings.
        foreach ($columns as $column) {
            if ($column->getType() == TableColumn::SQL_VARCHAR) {
                $columnStrings [] = "CAST (" . $column->getName() . " as text) " . $column->getName();
            } else {
                $columnStrings [] = $column->getName();
            }
        }

        return "SELECT " . join(",", $columnStrings);

    }

    /**
     * Generate a distinct clase for a set of columns
     *
     * @param $columns
     * @return mixed
     */
    public function generateDistinctClause($columns) {
        return "DISTINCT " . join(" + '||' + ", $columns);
    }
}