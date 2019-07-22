<?php

namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Exception\PrimaryKeyRowNotFoundException;
use Kinikit\Persistence\TableMapper\Exception\WrongPrimaryKeyLengthException;
use Kinikit\Persistence\TableMapper\Relationship\ManyToOneTableRelationship;
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


    public function testCanQueryForRowsUsingDefaultConnection() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example");
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"]
        ], $tableMapper->filter("WHERE name = ?", "Mark"));


        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
            ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]
        ], $tableMapper->filter("WHERE name = ? or name = ? ORDER by id", "Mark", "Dave"));


    }


    public function testIfManyToOneRelationshipIsDefinedQueriesAlsoQueryRelatedEntitiesViaJoin() {

        // Create a mapper with a one to one table relationship with another child.
        $tableMapper = new TableMapper("example_parent",
            [new ManyToOneTableRelationship(new TableMapper("example_child"),
                "child1", "child_id")]);


        // Check some primary key fetches
        $this->assertEquals(["id" => 1, "name" => "Mary Jones", "child_id" => null], $tableMapper->fetch(1));
        $this->assertEquals(["id" => 2, "name" => "Jane Walsh", "child_id" => 1, "child1" =>
            ["id" => 1, "description" => "Washing", "child2_id" => null]], $tableMapper->fetch(2));

        $this->assertEquals(["id" => 3, "name" => "James Smith", "child_id" => 2, "child1" =>
            ["id" => 2, "description" => "Swimming", "child2_id" => null]], $tableMapper->fetch(3));


        // Now do a regular query
        $results = $tableMapper->filter("WHERE name LIKE ? OR child_id = ?", "JA%", 1);
        $this->assertEquals(["id" => 2, "name" => "Jane Walsh", "child_id" => 1, "child1" =>
            ["id" => 1, "description" => "Washing", "child2_id" => null]], $results[0]);

        $this->assertEquals(["id" => 3, "name" => "James Smith", "child_id" => 2, "child1" =>
            ["id" => 2, "description" => "Swimming", "child2_id" => null]], $results[1]);


        // Now do a nested one to one
        $tableMapper = new TableMapper("example_parent",
            [new ManyToOneTableRelationship(
                new TableMapper("example_child",
                    [new ManyToOneTableRelationship(new TableMapper("example_child2"), "child2", "child2_id")]
                ),
                "child1", "child_id")]);


        $this->assertEquals(["id" => 4, "name" => "Heather Wright", "child_id" => 3, "child1" =>
            ["id" => 3, "description" => "Cooking", "child2_id" => 1, "child2" => [
                "id" => 1, "profession" => "Doctor"]
            ]], $tableMapper->fetch(4));


        // Now attempt to execute a query with nested where clause constraints
        $this->assertEquals([["id" => 4, "name" => "Heather Wright", "child_id" => 3, "child1" =>
            ["id" => 3, "description" => "Cooking", "child2_id" => 1, "child2" => [
                "id" => 1, "profession" => "Doctor"]
            ]]], $tableMapper->filter("WHERE child1.description = 'Cooking'"));


        $this->assertEquals([["id" => 4, "name" => "Heather Wright", "child_id" => 3, "child1" =>
            ["id" => 3, "description" => "Cooking", "child2_id" => 1, "child2" => [
                "id" => 1, "profession" => "Doctor"]
            ]]], $tableMapper->filter("WHERE child1.child2.id = 1"));


    }


}
