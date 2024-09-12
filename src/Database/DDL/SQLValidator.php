<?php

namespace Kinikit\Persistence\Database\DDL;

/**
 * Only alphanumeric characters and underscores are allowed in SQL index, column, and table names.
 */
class SQLValidator {
    public static function validateIndexName(string $indexName) {
        if (!$indexName) throw new InvalidIndexNameException("Cannot use empty index name");
        if (!mb_check_encoding($indexName, 'ASCII')){
            throw new InvalidIndexNameException("Cannot use non-ASCII characters in a index name.");
        }
        if (!preg_match("/^[a-zA-Z0-9_]*$/", $indexName)){
            throw new InvalidIndexNameException("Only alphanumeric characters and underscores are allowed in a index name.");
        }
        if (preg_match("/^[0-9_]*$/", $indexName)){
            throw new InvalidIndexNameException("Index name cannot be all numbers.");
        }
        return $indexName;
    }

    public static function validateTableName(string $tableName) {
        if (!$tableName) throw new InvalidTableNameException("Cannot use empty table name");
        if (!mb_check_encoding($tableName, 'ASCII')){
            throw new InvalidIndexNameException("Cannot use non-ASCII characters in a index name.");
        }
        if (!preg_match("/^[a-zA-Z0-9_]*$/", $tableName)){
            throw new InvalidTableNameException("Only alphanumeric characters and underscores are allowed in a index name.");
        }
        if (preg_match("/^[0-9_]*$/", $tableName)){
            throw new InvalidTableNameException("Table name cannot be all numbers.");
        }
        return $tableName;
    }
}