<?php

namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Testing\MockObject;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Exception\PrimaryKeyRowNotFoundException;
use Kinikit\Persistence\TableMapper\Exception\WrongPrimaryKeyLengthException;
use Kinikit\Persistence\TableMapper\Relationship\ManyToOneTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToOneTableRelationship;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

/**
 * Test cases for the table mapper
 *
 * Class TableMapperTest
 * @package Kinikit\Persistence\TableMapper
 */
class TableMapperTest extends TestCase {

    /**
     * @var TableMapper
     */
    private $tableMapper;

    /**
     * @var MockObject
     */
    private $queryEngine;


    /**
     * @var MockObject
     */
    private $persistenceEngine;

    public function setUp(): void {

        /**
         * @var MockObjectProvider $mockObjectProvider
         */
        $mockObjectProvider = Container::instance()->get(MockObjectProvider::class);

        $this->queryEngine = $mockObjectProvider->getMockInstance(TableQueryEngine::class);
        $this->persistenceEngine = $mockObjectProvider->getMockInstance(TablePersistenceEngine::class);

        $this->tableMapper = new TableMapper($this->queryEngine, $this->persistenceEngine);
    }


    public function testNotFoundExceptionRaisedIfAttemptToGetInvalidPrimaryKeyRow() {

        $tableMapping = new TableMapping("example");

        $this->queryEngine->returnValue("query", [], [$tableMapping, "SELECT * FROM example WHERE id=?", [4]]);


        try {
            $this->tableMapper->fetch($tableMapping, 4);
            $this->fail("Should have thrown here");
        } catch (PrimaryKeyRowNotFoundException $e) {
            $this->assertTrue(true);
        }

    }

    public function testWrongPrimaryKeyLengthExceptionRaisedIfAttemptToGetRowWithDifferentKeyLength() {

        $tableMapping = new TableMapping("example");

        try {
            $this->tableMapper->fetch($tableMapping, [4, 12]);
            $this->fail("Should have thrown here");
        } catch (WrongPrimaryKeyLengthException $e) {
            $this->assertTrue(true);
        }

    }


    public function testCanFetchValidRowsByPrimaryKeyUsingDefaultConnection() {

        // Create a basic mapper
        $tableMapping = new TableMapping("example");

        $this->queryEngine->returnValue("query", [["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"]], [$tableMapping, "SELECT * FROM example WHERE id=?", [1]]);
        $this->queryEngine->returnValue("query", [["id" => 2, "name" => "John", "last_modified" => "2012-01-01"]], [$tableMapping, "SELECT * FROM example WHERE id=?", [2]]);
        $this->queryEngine->returnValue("query", [["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]], [$tableMapping, "SELECT * FROM example WHERE id=?", [3]]);


        $this->assertEquals(["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"], $this->tableMapper->fetch($tableMapping, 1));
        $this->assertEquals(["id" => 2, "name" => "John", "last_modified" => "2012-01-01"], $this->tableMapper->fetch($tableMapping, 2));
        $this->assertEquals(["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"], $this->tableMapper->fetch($tableMapping, 3));

        // Check arrays as well
        $this->assertEquals(["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"], $this->tableMapper->fetch($tableMapping, [3]));
    }


    public function testCanMultiFetchRowsByPrimaryKeyUsingDefaultConnection() {

        // Create a basic mapper
        $tableMapping = new TableMapping("example");

        $this->queryEngine->returnValue("query", [1 => ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"], 3 => ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]],
            [$tableMapping, "SELECT * FROM example WHERE (id=?) OR (id=?)", [1, 3]]);

        $this->queryEngine->returnValue("query", [1 => ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"], 3 => ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]],
            [$tableMapping, "SELECT * FROM example WHERE (id=?) OR (id=?)", [3, 1]]);


        $this->queryEngine->returnValue("query", [1 => ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"], 3 => ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]],
            [$tableMapping, "SELECT * FROM example WHERE (id=?) OR (id=?) OR (id=?) OR (id=?)", [5, 3, 1, 4]]);


        // Single id syntax
        $this->assertEquals([
            1 => ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
            3 => ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]
        ], $this->tableMapper->multiFetch($tableMapping, [1, 3]));


        // Order preservation
        $this->assertEquals([
            3 => ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"],
            1 => ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
        ], $this->tableMapper->multiFetch($tableMapping, [3, 1]));


        // Array syntax.
        $this->assertEquals([
            1 => ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
            3 => ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]
        ], $this->tableMapper->multiFetch($tableMapping, [[1], [3]]));


        // Tolerate missing values
        $this->assertEquals([
            3 => ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"],
            1 => ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
        ], $this->tableMapper->multiFetch($tableMapping, [5, 3, 1, 4], true));


        // Throw if not ignoring missing values
        try {
            $this->tableMapper->multiFetch($tableMapping, [5, 3, 1, 4]);
            $this->fail("Should have thrown here");
        } catch (PrimaryKeyRowNotFoundException $e) {
            // Success
        }

    }


    public function testCanGetValuesArray() {

        // Create a basic mapper
        $tableMapping = new TableMapping("example");


        $this->queryEngine->returnValue("query", [["bobby" => "Mark"], ["bobby" => "John"], ["bobby" => "Dave"]],
            [$tableMapping, "SELECT DISTINCT(name) bobby FROM example ", []]);

        // Check array one
        $this->assertEquals([["bobby" => "Mark"], ["bobby" => "John"], ["bobby" => "Dave"]], $this->tableMapper->values($tableMapping, ["DISTINCT(name) bobby"]));


        // Check if supplied as single string just values returned
        $this->assertEquals(["Mark", "John", "Dave"], $this->tableMapper->values($tableMapping, "DISTINCT(name) bobby"));

    }


    public function testInsertCorrectlyCallsPersistenceEngine() {

        // Create a basic mapper
        $tableMapping = new TableMapping("example");

        $this->tableMapper->insert($tableMapping, ["name" => "Conrad"]);

        $this->assertTrue($this->persistenceEngine->methodWasCalled("saveRows", [$tableMapping, ["name" => "Conrad"], TablePersistenceEngine::SAVE_OPERATION_INSERT]));

        $this->tableMapper->insert($tableMapping, [["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]]);

        $this->assertTrue($this->persistenceEngine->methodWasCalled("saveRows", [$tableMapping, [["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]], TablePersistenceEngine::SAVE_OPERATION_INSERT]));

    }


    public function testUpdateCorrectlyCallsPersistenceEngine() {

        // Create a basic mapper
        $tableMapping = new TableMapping("example");

        $this->tableMapper->update($tableMapping, ["name" => "Conrad"]);

        $this->assertTrue($this->persistenceEngine->methodWasCalled("saveRows", [$tableMapping, ["name" => "Conrad"], TablePersistenceEngine::SAVE_OPERATION_UPDATE]));

        $this->tableMapper->update($tableMapping, [["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]]);

        $this->assertTrue($this->persistenceEngine->methodWasCalled("saveRows", [$tableMapping, [["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]], TablePersistenceEngine::SAVE_OPERATION_UPDATE]));

    }

    public function testReplaceCorrectlyCallsPersistenceEngine() {

        // Create a basic mapper
        $tableMapping = new TableMapping("example");

        $this->tableMapper->replace($tableMapping, ["name" => "Conrad"]);

        $this->assertTrue($this->persistenceEngine->methodWasCalled("saveRows", [$tableMapping, ["name" => "Conrad"], TablePersistenceEngine::SAVE_OPERATION_REPLACE]));

        $this->tableMapper->replace($tableMapping, [["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]]);

        $this->assertTrue($this->persistenceEngine->methodWasCalled("saveRows", [$tableMapping, [["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]], TablePersistenceEngine::SAVE_OPERATION_REPLACE]));

    }


    public function testSaveCorrectlyCallsPersistenceEngine() {

        // Create a basic mapper
        $tableMapping = new TableMapping("example");

        $this->tableMapper->save($tableMapping, ["name" => "Conrad"]);

        $this->assertTrue($this->persistenceEngine->methodWasCalled("saveRows", [$tableMapping, ["name" => "Conrad"], TablePersistenceEngine::SAVE_OPERATION_SAVE]));

        $this->tableMapper->save($tableMapping, [["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]]);

        $this->assertTrue($this->persistenceEngine->methodWasCalled("saveRows", [$tableMapping, [["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]], TablePersistenceEngine::SAVE_OPERATION_SAVE]));

    }


    public function testDeleteCorrectlyCallsPersistenceEngine() {

        $tableMapping = new TableMapping("example");

        $this->tableMapper->delete($tableMapping, ["id" => 3]);

        $this->assertTrue($this->persistenceEngine->methodWasCalled("deleteRows", [$tableMapping, ["id" => 3]]));

        $this->tableMapper->delete($tableMapping, [["id" => 3], ["id" => 5]]);

        $this->assertTrue($this->persistenceEngine->methodWasCalled("deleteRows", [$tableMapping, [["id" => 3], ["id" => 5]]]));


    }

}
