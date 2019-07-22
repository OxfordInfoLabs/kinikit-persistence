<?php


namespace Kinikit\Persistence\Database\Connection;


class TestPDODatabaseConnection extends PDODatabaseConnection {


    /**
     * Get table column meta data for a given table as an associative array keyed in by column name.
     *
     * @param $tableName
     * @return \Kinikit\Persistence\Database\MetaData\TableColumn[string]
     */
    public function getTableColumnMetaData($tableName) {
       
    }
}
