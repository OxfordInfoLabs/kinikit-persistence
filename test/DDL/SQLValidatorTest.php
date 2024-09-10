<?php

namespace Kinikit\Persistence\DDL;

use Kinikit\Persistence\Database\DDL\InvalidIndexNameException;
use Kinikit\Persistence\Database\DDL\InvalidTableNameException;
use Kinikit\Persistence\Database\DDL\SQLValidator;

include_once "autoloader.php";

class SQLValidatorTest extends \PHPUnit\Framework\TestCase {
    public function testCanValidateIndexes(){
        $expectedCorrectIndexNames = [
            "domain_name_idx",
            "index_1",
            "MY_INDEX",
            "idx123"
        ];
        foreach ($expectedCorrectIndexNames as $indexName) {
            $this->assertSame($indexName, SQLValidator::validateIndexName($indexName));
        }

        $expectedInvalidIndexNames = [
            "",
            "; drop",
            "index-1",
            "idx!name",
            "1_1",
            "1"
        ];
        foreach ($expectedInvalidIndexNames as $indexName) {
            try {
                $x = SQLValidator::validateIndexName($indexName);
                $this->fail("Index allowed: ". $indexName);
            } catch (InvalidIndexNameException $e) {
                // Success
            }
        }
    }

    public function testCanValidateTableNames(){
        $expectedCorrectTableNames = [
            "flagged_url",
            "chunks_document_data_set_8_1721",
            "MY_TABLE",
            "123tbl"
        ];
        foreach ($expectedCorrectTableNames as $tableName) {
            $this->assertSame($tableName, SQLValidator::validateTableName($tableName));
        }

        $expectedInvalidTableNames = [
            "",
            "; drop",
            "index-1",
            "idx!name",
            "1112_2",
            "11"
        ];
        foreach ($expectedInvalidTableNames as $tableName) {
            try {
                $x = SQLValidator::validateTableName($tableName);
                $this->fail("Table allowed: ". $tableName);
            } catch (InvalidTableNameException $e) {
                // Success
            }
        }
    }
}