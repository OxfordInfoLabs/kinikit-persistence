<?php

namespace Kinikit\Persistence\Database\Vendors\MySQL;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Testing\MockObject;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\BulkData\StandardBulkDataManager;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;

include_once "autoloader.php";

class MySQLBulkDataManagerTest extends \PHPUnit\Framework\TestCase {

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
        $this->mockDatabaseConnection->returnValue("escapeColumn", "`id`", [
            "id"
        ]);
        $this->mockDatabaseConnection->returnValue("escapeColumn", "`name`", [
            "name"
        ]);
        $this->mockDatabaseConnection->returnValue("escapeColumn", "`dob`", [
            "dob"
        ]);

    }

    public function testBulkInsertCorrectlyCreatesAPreparedStatementOnceAndCallsItRepeatedly() {

        $manager = new MySQLBulkDataManager($this->mockDatabaseConnection);

        // try simple insert
        $manager->insert("example", ["id" => 3, "name" => "Jeeves"]);

        $this->assertTrue($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement", ["INSERT INTO example (`id`,`name`) VALUES (?,?)"]));
        $this->assertTrue($this->mockPreparedStatement->methodWasCalled("execute", [[3, "Jeeves"]]));


        $this->mockDatabaseConnection->resetMethodCallHistory("createPreparedStatement");
        $this->mockPreparedStatement->resetMethodCallHistory("execute");

        // Now construct 100 random records
        $randomRecords = [];
        for ($i = 0; $i < 100; $i++) {
            $randomRecords[] = ["id" => $i, "name" => "Name $i"];
        }

        $manager->insert("example", $randomRecords);

        $this->assertFalse($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement", ["INSERT INTO example (`id`,`name`) VALUES (?,?)"]));
        $this->assertEquals(2, sizeof($this->mockPreparedStatement->getMethodCallHistory("execute")));

    }


    public function testBulkInsertWhenIgnoringDuplicatesCorrectlyCreatesAPreparedStatementOnceAndCallsItRepeatedly() {

        $manager = new MySQLBulkDataManager($this->mockDatabaseConnection);

        // try simple insert
        $manager->insert("example", ["id" => 3, "name" => "Jeeves"],null, true);

        $this->assertTrue($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement", ["INSERT IGNORE INTO example (`id`,`name`) VALUES (?,?)"]));
        $this->assertTrue($this->mockPreparedStatement->methodWasCalled("execute", [[3, "Jeeves"]]));


        $this->mockDatabaseConnection->resetMethodCallHistory("createPreparedStatement");
        $this->mockPreparedStatement->resetMethodCallHistory("execute");

        // Now construct 100 random records
        $randomRecords = [];
        for ($i = 0; $i < 100; $i++) {
            $randomRecords[] = ["id" => $i, "name" => "Name $i"];
        }

        $manager->insert("example", $randomRecords);

        $this->assertFalse($this->mockDatabaseConnection->methodWasCalled("createPreparedStatement", ["INSERT IGNORE INTO example (`id`,`name`) VALUES (?,?)"]));
        $this->assertEquals(2, sizeof($this->mockPreparedStatement->getMethodCallHistory("execute")));

    }

}