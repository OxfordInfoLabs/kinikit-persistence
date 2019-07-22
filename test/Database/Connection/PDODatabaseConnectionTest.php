<?php


namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Core\Configuration\Configuration;

class PDODatabaseConnectionTest extends \PHPUnit\Framework\TestCase {


    public function testCanConnectToPDOSourceAndExecuteQuery() {

        $configuration = Configuration::instance()->getAllParameters();

        // Try MySQL one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "mysql:dbname=" . $configuration["mysql.db.database"] . ";host=" . $configuration["mysql.db.host"],
            "username" => $configuration["mysql.db.username"], "password" => $configuration["mysql.db.password"]]);


        $this->assertTrue($pdoConnection->getPDO() instanceof \PDO);

        // Try SQL Lite one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "sqlite:" . $configuration["db.filename"]]);

        $this->assertTrue($pdoConnection->getPDO() instanceof \PDO);

    }


    public function testCanExecuteCommandsAndQueries() {

        $configuration = Configuration::instance()->getAllParameters();

        // Try MySQL one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "mysql:dbname=" . $configuration["mysql.db.database"] . ";host=" . $configuration["mysql.db.host"],
            "username" => $configuration["mysql.db.username"], "password" => $configuration["mysql.db.password"]]);


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
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "sqlite:" . $configuration["db.filename"]]);


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
            "username" => $configuration["mysql.db.username"], "password" => $configuration["mysql.db.password"]]);

        $pdoConnection->execute("DROP TABLE IF EXISTS example_pdo_with_blob");
        $pdoConnection->execute("CREATE TABLE example_pdo_with_blob (id INTEGER, name VARCHAR(50), pdf BLOB)");

        $statement = $pdoConnection->createPreparedStatement("INSERT INTO example_pdo_with_blob VALUES (?, ?, ?)");

        $statement->execute([1, "Robert", new \Kinikit\Persistence\Database\PreparedStatement\BlobWrapper(null, __DIR__ . "/testlargeobject.txt")]);
        $statement->execute([2, "Jane", new \Kinikit\Persistence\Database\PreparedStatement\BlobWrapper(null, __DIR__ . "/testlargeobject.txt")]);

        $results = $pdoConnection->query("SELECT * FROM example_pdo_with_blob ORDER BY id");

        $this->assertEquals(["id" => 1, "name" => "Robert", "pdf" => file_get_contents(__DIR__ . "/testlargeobject.txt")], $results->fetchAll()[0]);
        $this->assertEquals(["id" => 2, "name" => "Jane", "pdf" => file_get_contents(__DIR__ . "/testlargeobject.txt")], $results->fetchAll()[1]);


        // Try SQL Lite one
        $pdoConnection = new TestPDODatabaseConnection(["dsn" => "sqlite:" . $configuration["db.filename"]]);


        $pdoConnection->execute("DROP TABLE IF EXISTS example_pdo_with_blob");
        $pdoConnection->execute("CREATE TABLE example_pdo_with_blob (id INTEGER, name VARCHAR(50), pdf BLOB)");

        $statement = $pdoConnection->createPreparedStatement("INSERT INTO example_pdo_with_blob VALUES (?, ?, ?)");

        $statement->execute([1, "Robert", new \Kinikit\Persistence\Database\PreparedStatement\BlobWrapper(null, __DIR__ . "/testlargeobject.txt")]);
        $statement->execute([2, "Jane", new \Kinikit\Persistence\Database\PreparedStatement\BlobWrapper(null, __DIR__ . "/testlargeobject.txt")]);

        $results = $pdoConnection->query("SELECT * FROM example_pdo_with_blob ORDER BY id");

        $this->assertEquals(["id" => 1, "name" => "Robert", "pdf" => file_get_contents(__DIR__ . "/testlargeobject.txt")], $results->fetchAll()[0]);
        $this->assertEquals(["id" => 2, "name" => "Jane", "pdf" => file_get_contents(__DIR__ . "/testlargeobject.txt")], $results->fetchAll()[1]);


    }

}
