<?php

namespace Kinikit\Persistence\Database\DDL;

use Kinikit\Persistence\Database\MetaData\TableColumn;

class ColumnAlterations {

    /**
     * @var TableColumn[]
     */
    private array $addColumns;

    /**
     * @var TableColumn[]
     */
    private array $modifyColumns;

    /**
     * @var string[]
     */
    private array $dropColumns;

    /**
     * @param TableColumn[] $addColumns
     * @param TableColumn[] $modifyColumns
     * @param string[] $dropColumns
     */
    public function __construct(array $addColumns, array $modifyColumns, array $dropColumns) {
        $this->addColumns = $addColumns;
        $this->modifyColumns = $modifyColumns;
        $this->dropColumns = $dropColumns;
    }

    public function getAddColumns(): array {
        return $this->addColumns;
    }

    public function setAddColumns(array $addColumns): void {
        $this->addColumns = $addColumns;
    }

    public function getModifyColumns(): array {
        return $this->modifyColumns;
    }

    public function setModifyColumns(array $modifyColumns): void {
        $this->modifyColumns = $modifyColumns;
    }

    public function getDropColumns(): array {
        return $this->dropColumns;
    }

    public function setDropColumns(array $dropColumns): void {
        $this->dropColumns = $dropColumns;
    }

}