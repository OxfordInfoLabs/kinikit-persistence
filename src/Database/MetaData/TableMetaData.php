<?php


namespace Kinikit\Persistence\Database\MetaData;


class TableMetaData {

    /**
     * @var string
     */
    private $tableName;

    /**
     * Array of columns as constructed for this table
     *
     * @var TableColumn[string]
     */
    private $tableColumns = [];


    /**
     * @var TableColumn[]
     */
    private $pkColumns = [];

    /**
     * TableMetaData constructor.
     * @param string $tableName
     * @param TableColumn[] $tableColumns
     */
    public function __construct($tableName, $tableColumns) {
        $this->tableName = $tableName;

        foreach ($tableColumns as $tableColumn) {
            $this->tableColumns[$tableColumn->getName()] = $tableColumn;
            if ($tableColumn->isPrimaryKey())
                $this->pkColumns[$tableColumn->getName()] = $tableColumn;
        }
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * @return TableColumn[string]
     */
    public function getColumns() {
        return $this->tableColumns;
    }

    /**
     * @return TableColumn[]
     */
    public function getPrimaryKeyColumns() {
        return $this->pkColumns;
    }

}
