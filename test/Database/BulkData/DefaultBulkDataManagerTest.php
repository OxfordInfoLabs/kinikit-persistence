<?php

namespace Kinikit\Persistence\Database\BulkData;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Testing\MockObject;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;

/**
 * Default bulk data manager tests
 *
 * Class DefaultBulkDataManagerTest
 */
class DefaultBulkDataManagerTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var MockObject
     */
    private $mockDatabaseConnection;

    /**
     * @var MockObject
     */
    private $mockPreparedStatement;


    /**
     * @var MockObject
     */
    private $mockMetaData;

    public function setUp(): void {
        $mockProvider = Container::instance()->get(MockObjectProvider::class);
        $this->mockDatabaseConnection = $mockProvider->getMockInstance(DatabaseConnection::class);
        $this->mockPreparedStatement = $mockProvider->getMockInstance(PreparedStatement::class);
        $this->mockMetaData = $mockProvider->getMockInstance(TableMetaData::class);

        $this->mockDatabaseConnection->returnValue("createPreparedStatement", $this->mockPreparedStatement);
        $this->mockDatabaseConnection->returnValue("getTableMetaData", $this->mockMetaData);


    }

    public function testBulkInsertCorrectlyCreatesAPreparedStatementOnceAndCallsItRepeatedly() {

        $manager = new DefaultBulkDataManager($this->mockDatabaseConnection);

        // try simple insert
        $manager->insert("example", ["id" => 3, "name" => "Jeeves"]);

        $this->assertTrue($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement", ["INSERT INTO example (id,name) VALUES (?,?)"]));
        $this->assertTrue($this->mockPreparedStatement->methodWasCalled("execute", [[3, "Jeeves"]]));


        $this->mockDatabaseConnection->resetMethodCallHistory("createPreparedStatement");

        // Now construct 100 random records
        $randomRecords = [];
        for ($i = 0; $i < 100; $i++) {
            $randomRecords[] = ["id" => $i, "name" => "Name $i"];
        }

        $manager->insert("example", $randomRecords);

        $this->assertFalse($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement", ["INSERT INTO example (id,name) VALUES (?,?)"]));


        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue($this->mockPreparedStatement->methodWasCalled("execute", [[$i, "Name $i"]]));

        }
    }

    public function testBulkUpdateCorrectlyCreatesAPreparedStatementOnceAndCallsItRepeatedly() {

        $this->mockMetaData->returnValue("getPrimaryKeyColumns", ["id" => new TableColumn("id", TableColumn::SQL_INT, null, null, null, true)]);

        $manager = new DefaultBulkDataManager($this->mockDatabaseConnection);

        // try simple insert
        $manager->update("example", ["id" => 3, "name" => "Jeeves", "dob" => "01/01/2003"]);

        $this->assertTrue($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement", ["UPDATE example SET name=?,dob=? WHERE id=?"]));
        $this->assertTrue($this->mockPreparedStatement->methodWasCalled("execute", [["Jeeves", "01/01/2003", 3]]));


        $this->mockDatabaseConnection->resetMethodCallHistory("createPreparedStatement");

        // Now construct 100 random records
        $randomRecords = [];
        for ($i = 0; $i < 100; $i++) {
            $randomRecords[] = ["id" => $i, "name" => "Name $i"];
        }

        $manager->update("example", $randomRecords);

        $this->assertTrue($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement", ["UPDATE example SET name=? WHERE id=?"]));


        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue($this->mockPreparedStatement->methodWasCalled("execute", [["Name $i", $i]]));

        }
    }

    public function testBulkDeleteCorrectlyBulksDeletesWithInClauseWhenSinglePKsSupplied() {

        $this->mockMetaData->returnValue("getPrimaryKeyColumns", ["id" => new TableColumn("id", TableColumn::SQL_INT, null, null, null, true)]);


        $manager = new DefaultBulkDataManager($this->mockDatabaseConnection);

        $manager->delete("example", [2, 3, 4, 5, 6, 7]);

        $this->assertTrue($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement",
            ["DELETE FROM example WHERE id IN (?,?,?,?,?,?)"]));


        $this->assertTrue($this->mockPreparedStatement->methodWasCalled("execute", [[2, 3, 4, 5, 6, 7]]));

    }


    public function testBulkDeleteCorrectlyBulksDeletesWithInClauseWhenArrayPKsSupplied() {

        $this->mockMetaData->returnValue("getPrimaryKeyColumns", ["id" => new TableColumn("id", TableColumn::SQL_INT, null, null, null, true),
            "name" => new TableColumn("name", TableColumn::SQL_INT, null, null, null, true)]);


        $manager = new DefaultBulkDataManager($this->mockDatabaseConnection);

        $manager->delete("example", [["id" => 2, "name" => "Mark"], ["id" => 3, "name" => "Luke"],
            ["id" => 4, "name" => "Tim"], ["id" => 5, "name" => "John"], ["id" => 6, "name" => "James"], ["id" => 7, "name" => "Nathan"]]);

        $this->assertTrue($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement",
            ["DELETE FROM example WHERE (id=? AND name=?) OR (id=? AND name=?) OR (id=? AND name=?) OR (id=? AND name=?) OR (id=? AND name=?) OR (id=? AND name=?)"]));


        $this->assertTrue($this->mockPreparedStatement->methodWasCalled("execute", [[2, "Mark", 3, "Luke", 4, "Tim", 5, "John",
            6, "James", 7, "Nathan"]]));

        $this->mockPreparedStatement->resetMethodCallHistory("execute");

        // Now construct 100 random records
        $randomRecords = [];
        for ($i = 0; $i < 100; $i++) {
            $randomRecords[] = ["id" => $i, "name" => "Name $i"];
        }

        $manager->delete("example", $randomRecords);


        // Check these were batched into 50s
        $this->assertEquals(2, sizeof($this->mockPreparedStatement->getMethodCallHistory("execute")));

    }


    public function testBulkReplaceCorrectlyRemovesAndInserts() {

        $this->mockMetaData->returnValue("getPrimaryKeyColumns", ["id" => new TableColumn("id", TableColumn::SQL_INT, null, null, null, true)]);


        $manager = new DefaultBulkDataManager($this->mockDatabaseConnection);

        // try simple insert
        $manager->replace("example", ["id" => 3, "name" => "Jeeves"]);

        $this->assertTrue($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement", ["DELETE FROM example WHERE (id=?)"]));
        $this->assertTrue($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement", ["INSERT INTO example (id,name) VALUES (?,?)"]));
        $this->assertTrue($this->mockPreparedStatement->methodWasCalled("execute", [[3]]));
        $this->assertTrue($this->mockPreparedStatement->methodWasCalled("execute", [[3, "Jeeves"]]));

    }


    public function testCanExecuteAllActionsWithRealDatabaseConnections() {

        $databaseConnection = Container::instance()->get(DatabaseConnection::class);

        $manager = new DefaultBulkDataManager($databaseConnection);

        $insertRows = [
            ["name" => "Petal", "last_modified" => "2020-01-01"],
            ["name" => "Gavin", "last_modified" => "2020-01-01"],
        ];

        $manager->insert("example", $insertRows);


        $query = $databaseConnection->query("SELECT * FROM example WHERE name IN ('Petal', 'Gavin')");
        $results = $query->fetchAll();
        $this->assertEquals(2, sizeof($results));


        $updateRows = [
            ["name" => "Rosy", "id" => $results[0]["id"]],
            ["name" => "Gabriel", "id" => $results[1]["id"]],
        ];

        $manager->update("example", $updateRows);

        $query = $databaseConnection->query("SELECT * FROM example WHERE name IN ('Petal', 'Gavin')");
        $results = $query->fetchAll();
        $this->assertEquals(0, sizeof($results));


        $query = $databaseConnection->query("SELECT * FROM example WHERE name IN ('Rosy', 'Gabriel')");
        $results = $query->fetchAll();
        $this->assertEquals(2, sizeof($results));
        $this->assertNotNull($results[0]["last_modified"]);
        $this->assertNotNull($results[1]["last_modified"]);


        $replaceRows = [
            ["name" => "Poppet", "id" => $results[0]["id"]],
            ["name" => "Pickle", "id" => $results[1]["id"]],
        ];

        $manager->replace("example", $replaceRows);

        $query = $databaseConnection->query("SELECT * FROM example WHERE name IN ('Rosy', 'Gabriel')");
        $results = $query->fetchAll();
        $this->assertEquals(0, sizeof($results));

        $query = $databaseConnection->query("SELECT * FROM example WHERE name IN ('Poppet', 'Pickle')");
        $results = $query->fetchAll();
        $this->assertEquals(2, sizeof($results));
        $this->assertNull($results[0]["last_modified"]);
        $this->assertNull($results[1]["last_modified"]);


        $manager->delete("example", $results);
        $query = $databaseConnection->query("SELECT * FROM example WHERE name IN ('Poppet', 'Pickle')");
        $results = $query->fetchAll();
        $this->assertEquals(0, sizeof($results));


    }

}
