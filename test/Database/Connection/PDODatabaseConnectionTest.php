<?php


namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Core\Configuration\Configuration;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\PreparedStatement\BlobWrapper;
use Kinikit\Persistence\Database\Vendors\MySQL\MySQLResultSet;
use Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3ResultSet;

include_once "autoloader.php";

class PDODatabaseConnectionTest extends \PHPUnit\Framework\TestCase {


    public function testCanConnectToPDOSourceAndExecuteQuery() {

        $configuration = Configuration::instance()->getAllParameters();

        // Try MySQL one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "mysql:dbname=" . $configuration["mysql.db.database"] . ";host=" . $configuration["mysql.db.host"],
            "username" => $configuration["mysql.db.username"], "password" => $configuration["mysql.db.password"]], MySQLResultSet::class);


        $this->assertTrue($pdoConnection->getPDO() instanceof \PDO);

        // Try SQL Lite one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "sqlite:" . $configuration["db.filename"]], SQLite3ResultSet::class);

        $this->assertTrue($pdoConnection->getPDO() instanceof \PDO);

    }


    public function testCanExecuteCommandsAndQueries() {

        $configuration = Configuration::instance()->getAllParameters();

        // Try MySQL one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "mysql:dbname=" . $configuration["mysql.db.database"] . ";host=" . $configuration["mysql.db.host"],
            "username" => $configuration["mysql.db.username"], "password" => $configuration["mysql.db.password"]], MySQLResultSet::class);


        $pdoConnection->execute("DROP TABLE IF EXISTS example_pdo");
        $pdoConnection->execute("CREATE TABLE example_pdo (id INTEGER, name VARCHAR(50))");
        $pdoConnection->execute("INSERT INTO example_pdo VALUES (1, 'Mark'),(2, 'Luke'),(3, 'Bob')");

        $results = $pdoConnection->query("SELECT * FROM example_pdo ORDER BY id");
        $data = $results->fetchAll();
        $this->assertEquals([
            ["id" => 1, "name" => "Mark"],
            ["id" => 2, "name" => "Luke"],
            ["id" => 3, "name" => "Bob"]
        ], $data);


        // Try SQL Lite one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "sqlite:" . $configuration["db.filename"]], SQLite3ResultSet::class);


        $pdoConnection->execute("DROP TABLE IF EXISTS example_pdo");
        $pdoConnection->execute("CREATE TABLE example_pdo (id INTEGER, name VARCHAR(50))");
        $pdoConnection->execute("INSERT INTO example_pdo VALUES (?, ?),(?,?),(?,?)", 1, 'Mark', 2, 'Luke', 3, 'Bob');

        $results = $pdoConnection->query("SELECT * FROM example_pdo ORDER BY id");
        $data = $results->fetchAll();
        $this->assertEquals([
            ["id" => 1, "name" => "Mark"],
            ["id" => 2, "name" => "Luke"],
            ["id" => 3, "name" => "Bob"]
        ], $data);

    }


    public function testCanInsertBlobsViaBlobWrapper() {

        $configuration = Configuration::instance()->getAllParameters();

        // Try MySQL one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "mysql:dbname=" . $configuration["mysql.db.database"] . ";host=" . $configuration["mysql.db.host"],
            "username" => $configuration["mysql.db.username"], "password" => $configuration["mysql.db.password"]], MySQLResultSet::class);

        $pdoConnection->execute("DROP TABLE IF EXISTS example_pdo_with_blob");
        $pdoConnection->execute("CREATE TABLE example_pdo_with_blob (id INTEGER, name VARCHAR(50), pdf BLOB)");

        $statement = $pdoConnection->createPreparedStatement("INSERT INTO example_pdo_with_blob VALUES (?, ?, ?)");

        $statement->execute([1, "Robert", new BlobWrapper(null, __DIR__ . "/testlargeobject.txt")]);
        $statement->execute([2, "Jane", new BlobWrapper(null, __DIR__ . "/testlargeobject.txt")]);

        $results = $pdoConnection->query("SELECT * FROM example_pdo_with_blob ORDER BY id");

        $this->assertEquals(["id" => 1, "name" => "Robert", "pdf" => file_get_contents(__DIR__ . "/testlargeobject.txt")], $results->fetchAll()[0]);
        $this->assertEquals(["id" => 2, "name" => "Jane", "pdf" => file_get_contents(__DIR__ . "/testlargeobject.txt")], $results->fetchAll()[1]);


        // Try SQL Lite one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "sqlite:" . $configuration["db.filename"]], SQLite3ResultSet::class);


        $pdoConnection->execute("DROP TABLE IF EXISTS example_pdo_with_blob");
        $pdoConnection->execute("CREATE TABLE example_pdo_with_blob (id INTEGER, name VARCHAR(50), pdf BLOB)");

        $statement = $pdoConnection->createPreparedStatement("INSERT INTO example_pdo_with_blob VALUES (?, ?, ?)");

        $statement->execute([1, "Robert", new BlobWrapper(null, __DIR__ . "/testlargeobject.txt")]);
        $statement->execute([2, "Jane", new BlobWrapper(null, __DIR__ . "/testlargeobject.txt")]);

        $results = $pdoConnection->query("SELECT * FROM example_pdo_with_blob ORDER BY id");

        $this->assertEquals(["id" => 1, "name" => "Robert", "pdf" => file_get_contents(__DIR__ . "/testlargeobject.txt")], $results->fetchAll()[0]);
        $this->assertEquals(["id" => 2, "name" => "Jane", "pdf" => file_get_contents(__DIR__ . "/testlargeobject.txt")], $results->fetchAll()[1]);


    }


    public function testCanInsertBooleanValuesCorrectly() {

        $configuration = Configuration::instance()->getAllParameters();

        // Try SQL Lite one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "sqlite:" . $configuration["db.filename"]], SQLite3ResultSet::class);

        $pdoConnection->execute("DROP TABLE IF EXISTS example_pdo_with_boolean");
        $pdoConnection->execute("CREATE TABLE example_pdo_with_boolean (id INTEGER, name VARCHAR(50), is_happy BOOLEAN)");

        $statement = $pdoConnection->createPreparedStatement("INSERT INTO example_pdo_with_boolean VALUES (?, ?, ?)");

        $statement->execute([1, "Robert", true]);
        $statement->execute([2, "Jane", false]);

        $results = $pdoConnection->query("SELECT * FROM example_pdo_with_boolean ORDER BY id");

        $this->assertEquals(["id" => 1, "name" => "Robert", "is_happy" => true], $results->fetchAll()[0]);
        $this->assertEquals(["id" => 2, "name" => "Jane", "is_happy" => 0], $results->fetchAll()[1]);



        // Try MySQL one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "mysql:dbname=" . $configuration["mysql.db.database"] . ";host=" . $configuration["mysql.db.host"],
            "username" => $configuration["mysql.db.username"], "password" => $configuration["mysql.db.password"]], MySQLResultSet::class);

        $pdoConnection->execute("DROP TABLE IF EXISTS example_pdo_with_boolean");
        $pdoConnection->execute("CREATE TABLE example_pdo_with_boolean (id INTEGER, name VARCHAR(50), is_happy BOOLEAN)");

        $statement = $pdoConnection->createPreparedStatement("INSERT INTO example_pdo_with_boolean VALUES (?, ?, ?)");

        $statement->execute([1, "Robert", true]);
        $statement->execute([2, "Jane", false]);

        $results = $pdoConnection->query("SELECT * FROM example_pdo_with_boolean ORDER BY id");

        $this->assertEquals(["id" => 1, "name" => "Robert", "is_happy" => true], $results->fetchAll()[0]);
        $this->assertEquals(["id" => 2, "name" => "Jane", "is_happy" => false], $results->fetchAll()[1]);


    }


    public function testNonStringValuesAreHandledCorrectlyInQueries() {

        $configuration = Configuration::instance()->getAllParameters();

        // Try MySQL one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "mysql:dbname=" . $configuration["mysql.db.database"] . ";host=" . $configuration["mysql.db.host"],
            "username" => $configuration["mysql.db.username"], "password" => $configuration["mysql.db.password"]], MySQLResultSet::class);


        $pdoConnection->execute("DROP TABLE IF EXISTS example_pdo");
        $pdoConnection->execute("CREATE TABLE example_pdo (id INTEGER, name VARCHAR(50))");
        $pdoConnection->execute("INSERT INTO example_pdo VALUES (1, 'Mark'),(2, 'Luke'),(3, 'Bob')");

        $results = $pdoConnection->query("SELECT * FROM example_pdo LIMIT ? OFFSET ?", 10, 1);
        $this->assertEquals(2, sizeof($results->fetchAll()));


    }


    public function testSQLExceptionsRaisedWithSQLCodeIntactIfStatementOrQueryFails() {

        $configuration = Configuration::instance()->getAllParameters();

        // Try MySQL one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "mysql:dbname=" . $configuration["mysql.db.database"] . ";host=" . $configuration["mysql.db.host"],
            "username" => $configuration["mysql.db.username"], "password" => $configuration["mysql.db.password"]], MySQLResultSet::class);

        $pdoConnection->execute("DROP TABLE IF EXISTS example_pdo");
        $pdoConnection->execute("CREATE TABLE example_pdo (id INTEGER, name VARCHAR(50), PRIMARY KEY(id))");
        $pdoConnection->execute("INSERT INTO example_pdo VALUES (1, 'Mark'),(2, 'Luke'),(3, 'Bob')");

        try {
            $pdoConnection->execute("INSERT INTO example_pdo VALUES (1, 'Mark')");
            $this->fail("Should have thrown here");
        } catch (SQLException $e) {
            $this->assertEquals(23000, $e->getSqlStateCode());
        }


        try {
            $pdoConnection->query("SELECT * FROM example_pdo WHERE HELLO");
            $this->fail("Should have throw here");
        } catch (SQLException $e) {
            $this->assertEquals("42S22", $e->getSqlStateCode());
        }

    }

}
