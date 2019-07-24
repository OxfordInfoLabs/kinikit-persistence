<?php

namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Exception\PrimaryKeyRowNotFoundException;
use Kinikit\Persistence\TableMapper\Exception\WrongPrimaryKeyLengthException;
use Kinikit\Persistence\TableMapper\Relationship\ManyToOneTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToOneTableRelationship;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for the table mapper
 *
 * Class TableMapperTest
 * @package Kinikit\Persistence\TableMapper
 */
class TableMapperTest extends TestCase {

    public function setUp(): void {

        $databaseConnection = Container::instance()->get(DatabaseConnection::class);
        $databaseConnection->executeScript(file_get_contents(__DIR__ . "/tablemapper.sql"));

    }


    public function testNotFoundExceptionRaisedIfAttemptToGetInvalidPrimaryKeyRow() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example");

        try {
            $tableMapper->fetch(4);
            $this->fail("Should have thrown here");
        } catch (PrimaryKeyRowNotFoundException $e) {
            $this->assertTrue(true);
        }

    }

    public function testWrongPrimaryKeyLengthExceptionRaisedIfAttemptToGetRowWithDifferentKeyLength() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example");

        try {
            $tableMapper->fetch([4, 12]);
            $this->fail("Should have thrown here");
        } catch (WrongPrimaryKeyLengthException $e) {
            $this->assertTrue(true);
        }

    }


    public function testCanFetchValidRowsByPrimaryKeyUsingDefaultConnection() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example");

        $this->assertEquals(["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"], $tableMapper->fetch(1));
        $this->assertEquals(["id" => 2, "name" => "John", "last_modified" => "2012-01-01"], $tableMapper->fetch(2));
        $this->assertEquals(["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"], $tableMapper->fetch(3));

        // Check arrays as well
        $this->assertEquals(["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"], $tableMapper->fetch([3]));
    }


    public function testCanMultiFetchRowsByPrimaryKeyUsingDefaultConnection() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example");

        // Single id syntax
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
            ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]
        ], $tableMapper->multiFetch([1, 3]));


        // Order preservation
        $this->assertEquals([
            ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"],
            ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
        ], $tableMapper->multiFetch([3, 1]));


        // Array syntax.
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
            ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]
        ], $tableMapper->multiFetch([[1], [3]]));


        // Tolerate missing values
        $this->assertEquals([
            ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"],
            ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
        ], $tableMapper->multiFetch([5, 3, 1, 4], true));


        // Throw if not ignoring missing values
        try {
            $tableMapper->multiFetch([5, 3, 1, 4]);
            $this->fail("Should have thrown here");
        } catch (PrimaryKeyRowNotFoundException $e) {
            // Success
        }

    }


    public function testCanGetValuesArray() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example");

        // Check array one
        $this->assertEquals([["bobby" => "Mark"], ["bobby" => "John"], ["bobby" => "Dave"]], $tableMapper->values(["DISTINCT(name) bobby"]));


        // Check if supplied as single string just values returned
        $this->assertEquals(["Mark", "John", "Dave"], $tableMapper->values("DISTINCT(name) bobby"));

    }


    public function testCanInsertDataForSimpleTable() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example");

        $tableMapper->insert(["name" => "Conrad"]);

        $this->assertEquals(1, sizeof($tableMapper->filter("WHERE name = 'Conrad'")));

        $tableMapper->insert([["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]]);

        $this->assertEquals(3, sizeof($tableMapper->filter("WHERE name In ('Stephen', 'Willis', 'Pedro')")));

    }


    public function testCanInsertRelationalDataToOneToOneRelationshipAsWellIfSupplied() {

        $childMapper = new TableMapper("example_child_with_parent_key");
        $tableMapper = new TableMapper("example_parent", [new OneToOneTableRelationship($childMapper, "child", "parent_id")]);


        $insertData = [
            "name" => "Michael",
            "child" => [
                "description" => "Swimming Lanes"
            ]];


        $tableMapper->insert($insertData);


        $this->assertEquals(1, sizeof($tableMapper->filter("WHERE name = 'Michael'")));
        $this->assertEquals(1, sizeof($childMapper->filter("WHERE description = 'Swimming Lanes' AND parent_id = 5")));


        // Now try a double nested one.
        $childMapper = new TableMapper("example_child_with_parent_key", [
            new OneToOneTableRelationship("example_child_with_parent_key", "child", "parent_id")
        ]);

        $tableMapper = new TableMapper("example_parent", [
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


        $tableMapper->insert($insertData);


        $this->assertEquals(1, sizeof($tableMapper->filter("WHERE name = 'Stephanie'")));
        $this->assertEquals(1, sizeof($childMapper->filter("WHERE description = 'Cycling Lanes' AND parent_id = 6")));
        $this->assertEquals(1, sizeof($childMapper->filter("WHERE description = 'Jumping up and down' AND parent_id = 9")));

    }

}
