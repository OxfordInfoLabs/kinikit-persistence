<?php

namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Exception\PrimaryKeyRowNotFoundException;
use Kinikit\Persistence\TableMapper\Exception\WrongPrimaryKeyLengthException;
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

        $databaseConnection->query("DROP TABLE IF EXISTS example");
        $databaseConnection->query("CREATE TABLE example (id integer PRIMARY KEY, name VARCHAR(20), last_modified DATE)");

        $databaseConnection->query("INSERT INTO example(name, last_modified) VALUES ('Mark', '2010-01-01')");
        $databaseConnection->query("INSERT INTO example(name, last_modified) VALUES ('John', '2012-01-01')");
        $databaseConnection->query("INSERT INTO example(name, last_modified) VALUES ('Dave', '2014-01-01')");

    }


    public function testNotFoundExceptionRaisedIfAttemptToGetInvalidPrimaryKeyRow() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example", "id");


        try {
            $tableMapper->fetch(4);
            $this->fail("Should have thrown here");
        } catch (PrimaryKeyRowNotFoundException $e) {
            $this->assertTrue(true);
        }

    }

    public function testWrongPrimaryKeyLengthExceptionRaisedIfAttemptToGetRowWithDifferentKeyLength() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example", "id");

        try {
            $tableMapper->fetch([4, 12]);
            $this->fail("Should have thrown here");
        } catch (WrongPrimaryKeyLengthException $e) {
            $this->assertTrue(true);
        }

    }


    public function testCanFetchValidRowsByPrimaryKeyUsingDefaultConnection() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example", "id");

        $this->assertEquals(["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"], $tableMapper->fetch(1));
        $this->assertEquals(["id" => 2, "name" => "John", "last_modified" => "2012-01-01"], $tableMapper->fetch(2));
        $this->assertEquals(["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"], $tableMapper->fetch(3));

        // Check arrays as well
        $this->assertEquals(["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"], $tableMapper->fetch([3]));
    }


    public function testCanMultiFetchRowsByPrimaryKeyUsingDefaultConnection() {

        // Create a basic mapper
        $tableMapper = new TableMapper("example", "id");

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
        $tableMapper = new TableMapper("example", "id");
        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"]
        ], $tableMapper->query("WHERE name = ?", "Mark"));


        $this->assertEquals([
            ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
            ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]
        ], $tableMapper->query("WHERE name = ? or name = ? ORDER by id", "Mark", "Dave"));


        // Test full query
        $this->assertEquals([
            ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]
        ], $tableMapper->query("SELECT * from example where last_modified > '2013-01-01'"));


    }


}
