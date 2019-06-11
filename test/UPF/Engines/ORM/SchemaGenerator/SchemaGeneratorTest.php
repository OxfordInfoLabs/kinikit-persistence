<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\SchemaGenerator;

use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Connection\TableMetaData;
use Kinikit\Persistence\UPF\Engines\ORM\SchemaGenerator\SubDirectory\TestStandardObject;

include_once "autoloader.php";

class SchemaGeneratorTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var SchemaGenerator
     */
    private $generator;


    public function setUp() {
        $this->generator = new SchemaGenerator(DefaultDB::instance());
    }

    public function testCanGenerateTableDefinitionForSimpleClassWithDefaults() {

        // Get the definition
        $definition = $this->generator->generateTableDefinitionForClass(TestStandardObject::class);

        $this->assertTrue($definition instanceof TableMetaData);
        $this->assertEquals("test_standard_object", $definition->getTableName());


        $columns = $definition->getColumns();
        $this->assertEquals(3, sizeof($columns));

        $idColumn = $columns[0];
        $this->assertEquals("id", $idColumn->getName());
        $this->assertEquals(TableColumn::SQL_INT, $idColumn->getType());
        $this->assertNull($idColumn->getLength());
        $this->assertTrue($idColumn->getPrimaryKey());
        $this->assertTrue($idColumn->getAutoIncrement());
        $this->assertTrue($idColumn->getNotNull());


        $nameColumn = $columns[1];
        $this->assertEquals("name", $nameColumn->getName());
        $this->assertEquals(TableColumn::SQL_VARCHAR, $nameColumn->getType());
        $this->assertEquals(255, $nameColumn->getLength());
        $this->assertTrue($nameColumn->getNotNull());
        $this->assertFalse($nameColumn->getPrimaryKey());
        $this->assertFalse($nameColumn->getAutoIncrement());

        $atHomeColumn = $columns[2];
        $this->assertEquals("at_home", $atHomeColumn->getName());
        $this->assertEquals(TableColumn::SQL_TINYINT, $atHomeColumn->getType());
        $this->assertEquals(null, $atHomeColumn->getLength());
        $this->assertFalse($atHomeColumn->getPrimaryKey());
        $this->assertFalse($atHomeColumn->getAutoIncrement());
        $this->assertFalse($atHomeColumn->getNotNull());

    }


    public function testCanGenerateTableDefinitionForCustomisedClass() {

        // Get the definition
        $definition = $this->generator->generateTableDefinitionForClass(TestCustomisedObject::class);

        $this->assertTrue($definition instanceof TableMetaData);
        $this->assertEquals("my_funny_table", $definition->getTableName());


        $columns = $definition->getColumns();
        $this->assertEquals(5, sizeof($columns));

        $nameColumn = $columns[0];
        $this->assertEquals("name", $nameColumn->getName());
        $this->assertEquals(TableColumn::SQL_VARCHAR, $nameColumn->getType());
        $this->assertEquals(255, $nameColumn->getLength());
        $this->assertTrue($nameColumn->getPrimaryKey());
        $this->assertFalse($nameColumn->getAutoIncrement());
        $this->assertFalse($nameColumn->getNotNull());

        $dobColumn = $columns[1];
        $this->assertEquals("dob", $dobColumn->getName());
        $this->assertEquals("DATE", $dobColumn->getType());
        $this->assertEquals(null, $dobColumn->getLength());
        $this->assertTrue($dobColumn->getPrimaryKey());
        $this->assertFalse($dobColumn->getAutoIncrement());
        $this->assertFalse($dobColumn->getNotNull());

        $descriptionColumn = $columns[2];
        $this->assertEquals("description", $descriptionColumn->getName());
        $this->assertEquals("VARCHAR", $descriptionColumn->getType());
        $this->assertEquals(1000, $descriptionColumn->getLength());
        $this->assertFalse($descriptionColumn->getPrimaryKey());
        $this->assertFalse($descriptionColumn->getAutoIncrement());
        $this->assertFalse($descriptionColumn->getNotNull());

        $commentsColumn = $columns[3];
        $this->assertEquals("comments", $commentsColumn->getName());
        $this->assertEquals("LONGTEXT", $commentsColumn->getType());
        $this->assertEquals(null, $commentsColumn->getLength());
        $this->assertFalse($commentsColumn->getPrimaryKey());
        $this->assertFalse($commentsColumn->getAutoIncrement());
        $this->assertFalse($commentsColumn->getNotNull());

        $lastUpdatedColumn = $columns[4];
        $this->assertEquals("last_updated", $lastUpdatedColumn->getName());
        $this->assertEquals("DATETIME", $lastUpdatedColumn->getType());
        $this->assertEquals(null, $lastUpdatedColumn->getLength());
        $this->assertFalse($lastUpdatedColumn->getPrimaryKey());
        $this->assertFalse($lastUpdatedColumn->getAutoIncrement());
        $this->assertTrue($lastUpdatedColumn->getNotNull());

    }

    public function testCanGenerateAllDefinitionsRecursivelyFromFilesystem() {

        $definitions = $this->generator->generateTableDefinitionsForAllActiveRecordObjects(__DIR__, "Kinikit\Persistence\UPF\Engines\ORM\SchemaGenerator");

        $this->assertEquals(2, sizeof($definitions));
        $this->assertEquals("my_funny_table", $definitions[0]->getTableName());
        $this->assertEquals("test_standard_object", $definitions[1]->getTableName());


    }


    public function testTablesAreCreatedRecursivelyWhenCreateSchemaCalled() {

        DefaultDB::instance()->query("DROP TABLE IF EXISTS my_funny_table");
        DefaultDB::instance()->query("DROP TABLE IF EXISTS test_standard_object");

        // Create the schema
        $this->generator->createSchema(__DIR__, "Kinikit\Persistence\UPF\Engines\ORM\SchemaGenerator");

        $tableMetaData = DefaultDB::instance()->getTableMetaData("my_funny_table");
        $this->assertEquals("my_funny_table", $tableMetaData->getTableName());

        $tableMetaData = DefaultDB::instance()->getTableMetaData("test_standard_object");
        $this->assertEquals("test_standard_object", $tableMetaData->getTableName());

    }

}
