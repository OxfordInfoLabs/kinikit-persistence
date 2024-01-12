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
    protected $tableColumns = [];


    /**
     * Array fo indexes, keyed in by name
     *
     * @var TableIndex[string]
     */
    protected $indexes = [];


    /**
     * @var TableColumn[]
     */
    private $pkColumns = [];

    /**
     * TableMetaData constructor.
     *
     * @param string $tableName
     * @param TableColumn[] $tableColumns
     * @param TableIndex[] $indexes
     */
    public function __construct($tableName, $tableColumns, $indexes = []) {
        $this->tableName = $tableName;

        foreach ($tableColumns as $tableColumn) {
            $this->addColumn($tableColumn);
        }

        foreach ($indexes as $index) {
            $this->indexes[$index->getName()] = $index;
        }
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * @return TableColumn[]
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

    /**
     * @return TableIndex[]
     */
    public function getIndexes() {
        return $this->indexes;
    }


    /**
     * Add a table column
     *
     * @param $tableColumn
     */
    protected function addColumn($tableColumn) {
        $this->tableColumns[$tableColumn->getName()] = $tableColumn;
        if ($tableColumn->isPrimaryKey())
            $this->pkColumns[$tableColumn->getName()] = $tableColumn;
    }


}
