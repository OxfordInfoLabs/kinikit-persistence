<?php


namespace Kinikit\Persistence\TableMapper\Mapper;


use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Relationship\OneToOneTableRelationship;

include_once "autoloader.php";

class TablePersistenceEngineTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var TablePersistenceEngine
     */
    private $persistenceEngine;

    /**
     * @var TableQueryEngine
     */
    private $queryEngine;


    public function setUp(): void {
        parent::setUp();
        $this->persistenceEngine = new TablePersistenceEngine();
        $this->queryEngine = new TableQueryEngine();

        $databaseConnection = Container::instance()->get(DatabaseConnection::class);
        $databaseConnection->executeScript(file_get_contents(__DIR__ . "/tablemapper.sql"));
    }


    public function testCanInsertDataForSimpleTable() {

        // Create a basic mapping
        $tableMapping = new TableMapping("example");

        $this->persistenceEngine->saveRows($tableMapping, ["name" => "Conrad"], TablePersistenceEngine::SAVE_OPERATION_INSERT);

        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE name = 'Conrad'")));

        $this->persistenceEngine->saveRows($tableMapping, [["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]], TablePersistenceEngine::SAVE_OPERATION_INSERT);

        $this->assertEquals(3, sizeof($this->queryEngine->query($tableMapping, "WHERE name In ('Stephen', 'Willis', 'Pedro')")));

    }


    public function testCanInsertRelationalDataForRelationshipsAsWellIfSupplied() {

        $childMapper = new TableMapping("example_child_with_parent_key");
        $tableMapper = new TableMapping("example_parent", [new OneToOneTableRelationship($childMapper, "child", "parent_id")]);


        $insertData = [
            "name" => "Michael",
            "child" => [
                "description" => "Swimming Lanes"
            ]];


        $this->persistenceEngine->saveRows($tableMapper, $insertData);


        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapper, "WHERE name = 'Michael'")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE description = 'Swimming Lanes' AND parent_id = 5")));


        // Now try a double nested one.
        $childMapper = new TableMapping("example_child_with_parent_key", [
            new OneToOneTableRelationship("example_child_with_parent_key", "child", "parent_id")
        ]);

        $tableMapper = new TableMapping("example_parent", [
            new OneToOneTableRelationship($childMapper, "child", "parent_id")
        ]);


        $insertData = [
            "name" => "Stephanie",
            "child" => [
                "description" => "Cycling Lanes",
                "child" => [
                    "description" => "Jumping up and down"
                ]
            ]];


        $this->persistenceEngine->saveRows($tableMapper, $insertData);


        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapper, "WHERE name = 'Stephanie'")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE description = 'Cycling Lanes' AND parent_id = 6")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE description = 'Jumping up and down' AND parent_id = 9")));

    }

}
