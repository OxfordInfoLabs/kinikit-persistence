<?php


namespace Kinikit\Persistence\Database\Connection;


class TestPDODatabaseConnection extends PDODatabaseConnection {


    /**
     * @var string
     */
    private $resultSetClass;


    public function __construct($configParams = null, $resultSetClass) {
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


    public function getResultSetClass() {
        return $this->resultSetClass;
    }
}
