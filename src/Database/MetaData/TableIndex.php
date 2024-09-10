<?php

namespace Kinikit\Persistence\Database\MetaData;

use Kinikit\Persistence\Database\DDL\InvalidIndexNameException;
use Kinikit\Persistence\Database\DDL\SQLValidator;

/**
 * Table index - currently simply a sequence of table columns
 */
class TableIndex {

    private string $name;

    /**
     * @var TableIndexColumn[]
     */
    private $columns = [];


    /**
     * @param string $name
     * @param string[]|TableIndexColumn[] $columns
     * @throws InvalidIndexNameException
     */
    public function __construct(string $name, $columns) {
        $this->name = SQLValidator::validateIndexName($name);

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