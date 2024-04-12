<?php


namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Persistence\Database\MetaData\TableIndex;

class TestPDODatabaseConnection extends PDODatabaseConnection {


    /**
     * @var string
     */
    private $resultSetClass;


    public function __construct($configParams = null, $resultSetClass = null) {
        parent::__construct($configParams);
        $this->resultSetClass = $resultSetClass;
    }

    /**
     * Get table column meta data for a given table as an associative array keyed in by column name.
     *
     * @param $tableName
     * @return \Kinikit\Persistence\Database\MetaData\TableColumn[string]
     */
    public function getTableColumnMetaData($tableName) {

    }

    /**
     * Return the index data for a table
     *
     * @param $tableName
     * @return TableIndex[]
     */
    public function getTableIndexMetaData($tableName) {
        // TODO: Implement getTableIndexMetaData() method.
    }



    public function getResultSetClass() {
        return $this->resultSetClass;
    }

    public function getDDLManager() {
        // TODO: Implement getDDLManager() method.
    }
}
