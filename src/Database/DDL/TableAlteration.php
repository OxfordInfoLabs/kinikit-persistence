<?php

namespace Kinikit\Persistence\Database\DDL;

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
     * @param string $tableName
     * @param string|null $newTableName
     * @param ColumnAlterations $columnAlterations
     * @param IndexAlterations $indexAlterations
     */
    public function __construct(string $tableName, ?string $newTableName, ColumnAlterations $columnAlterations, IndexAlterations $indexAlterations) {
        $this->tableName = $tableName;
        $this->newTableName = $newTableName;
        $this->columnAlterations = $columnAlterations;
        $this->indexAlterations = $indexAlterations;
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

}