<?php

namespace Kinikit\Persistence\Database\DDL;

class TableAlteration {

    /**
     * @var string
     */
    private string $tableName;

    /**
     * @var ColumnAlterations
     */
    private ColumnAlterations $columnAlterations;

    /**
     * @var IndexAlterations
     */
    private IndexAlterations $indexAlterations;

}