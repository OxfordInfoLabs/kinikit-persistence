<?php

namespace Kinikit\Persistence\Database\DDL;

use Kinikit\Persistence\Database\MetaData\TableIndex;

class IndexAlterations {

    /**
     * @var ?string[]
     */
    private ?array $newPrimaryKeyColumns;

    /**
     * @var TableIndex[]
     */
    private array $addIndexes;

    /**
     * @var TableIndex[]
     */
    private array $modifyIndexes;

    /**
     * @var TableIndex[]
     */
    private array $dropIndexes;

    /**
     * @param string[] $newPrimaryKeyColumns
     * @param TableIndex[] $addIndexes
     * @param TableIndex[] $modifyIndexes
     * @param TableIndex[] $dropIndexes
     */
    public function __construct(?array $newPrimaryKeyColumns, array $addIndexes, array $modifyIndexes, array $dropIndexes) {
        $this->newPrimaryKeyColumns = $newPrimaryKeyColumns;
        $this->addIndexes = $addIndexes;
        $this->modifyIndexes = $modifyIndexes;
        $this->dropIndexes = $dropIndexes;
    }

    public function getNewPrimaryKeyColumns(): ?array {
        return $this->newPrimaryKeyColumns;
    }

    public function setNewPrimaryKeyColumns(?array $newPrimaryKeyColumns): void {
        $this->newPrimaryKeyColumns = $newPrimaryKeyColumns;
    }

    public function getAddIndexes(): array {
        return $this->addIndexes;
    }

    public function setAddIndexes(array $addIndexes): void {
        $this->addIndexes = $addIndexes;
    }

    public function getModifyIndexes(): array {
        return $this->modifyIndexes;
    }

    public function setModifyIndexes(array $modifyIndexes): void {
        $this->modifyIndexes = $modifyIndexes;
    }

    public function getDropIndexes(): array {
        return $this->dropIndexes;
    }

    public function setDropIndexes(array $dropIndexes): void {
        $this->dropIndexes = $dropIndexes;
    }

}