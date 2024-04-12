<?php

namespace Kinikit\Persistence\Database\DDL;

use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableMetaData;

interface DDLManager {

    /**
     * @param TableMetaData $tableMetaData
     * @return string
     */
    public function generateTableCreateSQL(TableMetaData $tableMetaData): string;

    /**
     * @param TableAlteration $tableAlteration
     * @param DatabaseConnection $connection
     * @return string
     */
    public function generateModifyTableSQL(TableAlteration $tableAlteration, DatabaseConnection $connection): string;

    /**
     * @param string $tableName
     * @return string
     */
    public function generateTableDropSQL(string $tableName): string;

}