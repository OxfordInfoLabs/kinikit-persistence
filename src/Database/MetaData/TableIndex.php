<?php

namespace Kinikit\Persistence\Database\MetaData;

/**
 * Table index - currently simply a sequence of table columns
 */
class TableIndex {

    /**
     * @var string
     */
    private $name;

    /**
     * @var TableIndexColumn[]
     */
    private $columns = [];


    /**
     * @param string $name
     * @param string[]|TableIndexColumn[] $columns
     */
    public function __construct($name, $columns) {
        $this->name = $name;

        foreach ($columns as $column) {
            $this->columns[] = ($column instanceof TableIndexColumn) ? $column : new TableIndexColumn($column);
        }
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }


    /**
     * @return TableIndexColumn[]
     */
    public function getColumns() {
        return $this->columns;
    }


}