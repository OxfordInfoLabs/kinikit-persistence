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

}
