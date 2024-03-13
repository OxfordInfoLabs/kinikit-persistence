<?php

namespace Kinikit\Persistence\Database\DDL;

use Kinikit\Persistence\Database\MetaData\TableIndex;

class IndexAlterations {

    /**
     * @var TableIndex
     */
    private TableIndex $addIndexes;

    /**
     * @var TableIndex
     */
    private TableIndex $dropIndexes;

    /**
     * @var TableIndex
     */
    private TableIndex $modifyIndexes;     // May not use??

    /**
     * @param TableIndex $addIndexes
     * @param TableIndex $dropIndexes
     * @param TableIndex $modifyIndexes
     */
    public function __construct(TableIndex $addIndexes, TableIndex $dropIndexes, TableIndex $modifyIndexes) {
        $this->addIndexes = $addIndexes;
        $this->dropIndexes = $dropIndexes;
        $this->modifyIndexes = $modifyIndexes;
    }

    public function getAddIndexes(): TableIndex {
        return $this->addIndexes;
    }

    public function setAddIndexes(TableIndex $addIndexes): void {
        $this->addIndexes = $addIndexes;
    }

    public function getDropIndexes(): TableIndex {
        return $this->dropIndexes;
    }

    public function setDropIndexes(TableIndex $dropIndexes): void {
        $this->dropIndexes = $dropIndexes;
    }

    public function getModifyIndexes(): TableIndex {
        return $this->modifyIndexes;
    }

    public function setModifyIndexes(TableIndex $modifyIndexes): void {
        $this->modifyIndexes = $modifyIndexes;
    }

}