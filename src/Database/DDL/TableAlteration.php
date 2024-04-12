<?php

namespace Kinikit\Persistence\Database\DDL;

use Kinikit\Persistence\Database\MetaData\TableMetaData;

class TableAlteration {

    /**
     * @var string
     */
    private string $tableName;

    /**
     * @var ?string
     */
    private ?string $newTableName;

    /**
     * @var ColumnAlterations
     */
    private ColumnAlterations $columnAlterations;

    /**
     * @var IndexAlterations
     */
    private IndexAlterations $indexAlterations;

    /**
     * This is required as SQLite doesn't support alter column statements
     *
     * @var ?TableMetaData
     */
    private ?TableMetaData $newTableMetaData;

    /**
     * @param string $tableName
     * @param string|null $newTableName
     * @param ColumnAlterations $columnAlterations
     * @param IndexAlterations $indexAlterations
     * @param ?TableMetaData $newTableMetaData
     */
    public function __construct(string $tableName, ?string $newTableName, ColumnAlterations $columnAlterations, IndexAlterations $indexAlterations, ?TableMetaData $newTableMetaData = null) {
        $this->tableName = $tableName;
        $this->newTableName = $newTableName;
        $this->columnAlterations = $columnAlterations;
        $this->indexAlterations = $indexAlterations;
        $this->newTableMetaData = $newTableMetaData;
    }

    public function getTableName(): string {
        return $this->tableName;
    }

    public function setTableName(string $tableName): void {
        $this->tableName = $tableName;
    }

    public function getNewTableName(): ?string {
        return $this->newTableName;
    }

    public function setNewTableName(?string $newTableName): void {
        $this->newTableName = $newTableName;
    }

    public function getColumnAlterations(): ColumnAlterations {
        return $this->columnAlterations;
    }

    public function setColumnAlterations(ColumnAlterations $columnAlterations): void {
        $this->columnAlterations = $columnAlterations;
    }

    public function getIndexAlterations(): IndexAlterations {
        return $this->indexAlterations;
    }

    public function setIndexAlterations(IndexAlterations $indexAlterations): void {
        $this->indexAlterations = $indexAlterations;
    }

    public function getNewTableMetaData(): ?TableMetaData {
        return $this->newTableMetaData;
    }

    public function setNewTableMetaData(?TableMetaData $newTableMetaData): void {
        $this->newTableMetaData = $newTableMetaData;
    }

}