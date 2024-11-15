<?php

namespace Kinikit\Persistence\Database\Vendors\MySQL;

use Kinikit\Persistence\Database\BulkData\StandardBulkDataManager;

class MySQLBulkDataManager extends StandardBulkDataManager {

    public function doInsert($tableName, $rows, $insertColumns, $ignoreDuplicates) {
        parent::doInsertOrReplace($tableName, $rows, $insertColumns, $ignoreDuplicates ? "INSERT IGNORE" : "INSERT");
    }
    
}