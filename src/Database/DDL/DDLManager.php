<?php

namespace Kinikit\Persistence\Database\DDL;

use Kinikit\Persistence\Database\MetaData\TableMetaData;

interface DDLManager {

    /**
     * @param TableMetaData $tableMetaData
     * @return string
     */
    public function generateTableCreateSQL(TableMetaData $tableMetaData): string;

    /**
     * @param string $tableName
     * @return string
     */
    public function generateTableDropSQL(string $tableName): string;

    /**
     * @param TableMetaData $originalTableMetaData
     * @param TableMetaData $newTableMetaData
     * @return string
     */
    public function generateAlterTableSQL(TableMetaData $originalTableMetaData, TableMetaData $newTableMetaData): string;

}