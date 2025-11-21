<?php

namespace Kinikit\Persistence\Database\Vendors\MySQL;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Persistence\Database\Exception\SQLException;

include_once "autoloader.php";

class MySQLPreparedStatementTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var MySQLDatabaseConnection
     */
    private $mysqlDatabaseConnection;


    /**
     * @throws \Kinikit\Persistence\Database\Connection\DatabaseConnectionException
     * @throws \Kinikit\Persistence\Database\Exception\SQLException
     */
    public function setUp(): void {

        $configParams = Configuration::instance()->getParametersMatchingPrefix("mysql.db.", true);

        if (!$this->mysqlDatabaseConnection)
            $this->mysqlDatabaseConnection = new MySQLDatabaseConnection($configParams);


        $this->mysqlDatabaseConnection->execute("DROP TABLE IF EXISTS test_child");
        $this->mysqlDatabaseConnection->execute("CREATE TABLE test_child(id INTEGER AUTO_INCREMENT, note VARCHAR(20), parent_id INTEGER, PRIMARY KEY (id))");
        $this->mysqlDatabaseConnection->query("DROP TABLE IF EXISTS test_child_multi_key");
        $this->mysqlDatabaseConnection->query("CREATE TABLE test_child_multi_key (id INTEGER AUTO_INCREMENT, description VARCHAR(20), parent_field1 INTEGER, parent_field2 VARCHAR(10), parent_field3 INTEGER, PRIMARY KEY (id))");


    }


    /**
     * @return void
     * @throws SQLException
     * @throws \Kinikit\Persistence\Database\Connection\DatabaseConnectionException
     */
    public function testIfExceptionRaisedWithARetryStatusExecutionsAreRetriedAccordingToNumberOfTimes() {

        if (file_exists("application.log"))
            unlink("application.log");

        $configParams = Configuration::instance()->getParametersMatchingPrefix("mysql.db.", true);
        $connection2 = new MySQLDatabaseConnection($configParams);

        $connection2->execute("SET SESSION innodb_lock_wait_timeout = 1");
        $this->mysqlDatabaseConnection->execute("SET SESSION innodb_lock_wait_timeout = 1");

        $this->mysqlDatabaseConnection->execute("INSERT INTO test_child (id) VALUES (1)");

        $connection2->beginTransaction();
        $connection2->execute("SELECT * FROM test_child WHERE id = 1 LOCK IN SHARE MODE");


        try {
            $this->mysqlDatabaseConnection->beginTransaction();

            $statement = $this->mysqlDatabaseConnection->createPreparedStatement("UPDATE test_child SET id = ? WHERE id = ?");
            $statement->execute([2, 1]);
        } catch (SQLException $e) {
            // We expect this but with a retry according to configured retry times
        }

        $log = file_get_contents("application.log");
        $this->assertStringContainsString("MySQL Statement Retry 1", $log);
        $this->assertStringContainsString("MySQL Statement Retry 2", $log);
        $this->assertStringNotContainsString("MySQL Statement Retry 3", $log);

        $this->mysqlDatabaseConnection->rollback();

    }


}