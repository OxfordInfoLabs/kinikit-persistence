<?php


namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Persistence\Database\DDL\DDLManager;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableIndex;

class TestPDODatabaseConnection extends PDODatabaseConnection {


    /**
     * @var string
     */
    private $resultSetClass;


    public function __construct(array $configParams = [], $resultSetClass = null) {
        parent::__construct($configParams);
        $this->resultSetClass = $resultSetClass;
    }

    /**
     * Get table column meta data for a given table as an associative array keyed in by column name.
     *
     * @param string $tableName
     * @return TableColumn[]
     */
    public function getTableColumnMetaData(string $tableName): array {

    }

    /**
     * Return the index data for a table
     *
     * @param $tableName
     * @return TableIndex[]
     */
    public function getTableIndexMetaData($tableName): array {
        // TODO: Implement getTableIndexMetaData() method.
    }



    public function getResultSetClass(): string {
        return $this->resultSetClass;
    }

    public function getDDLManager(): DDLManager {
        // TODO: Implement getDDLManager() method.
    }
}
