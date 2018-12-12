<?php

namespace Kinikit\Persistence\UPF\LockingProviders;

use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\Database\Connection\MySQL\MySQLDatabaseConnection;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\UPF\Framework\ObjectMapper;

include_once "autoloader.php";


// Test cases for the standard SQL optimistic locking provider.
class SQLOptimisticLockingProviderTest extends \PHPUnit\Framework\TestCase {

    private $provider;

    public function setUp() {
        $this->provider = new SQLOptimisticLockingProvider ();

        try {
            DefaultDB::instance()->query("CREATE TABLE kinikit_object_locking(
	object_class		VARCHAR(255),
	object_pk			VARCHAR(255),
	last_modified		DATETIME,
	PRIMARY KEY (object_class, object_pk)
)");
        } catch (SQLException $e) {
            // OK
        }
        DefaultDB::instance()->query("DELETE FROM kinikit_object_locking");
    }

    public function testGetLockingDataForObjectReturnsCurrentTimestampAsStringRegardlessOfMapperOrPKValues() {

        $this->assertEquals(date('Y-m-d H:i:s'), $this->provider->getLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 34));
        $this->assertEquals(date('Y-m-d H:i:s'), $this->provider->getLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId"), 256));
        $this->assertEquals(date('Y-m-d H:i:s'), $this->provider->getLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), "34||88"));

    }

    public function testUpdateLockingDataUpdatesTheDatabaseCorrectlyWithLatestTimestamp() {

        $this->assertEquals(0, DefaultDB::instance()->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_locking"));

        $this->provider->updateLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 44);

        $this->assertEquals("Kinikit\Persistence\UPF\Framework\NewObjectWithId||44||" . date('Y-m-d H:i'), DefaultDB::instance()->queryForSingleValue("SELECT  object_class || '||' || object_pk || '||' || SUBSTR(last_modified, 1, 16) FROM kinikit_object_locking WHERE object_pk = 44"));

        DefaultDB::instance()->query("UPDATE kinikit_object_locking SET last_modified = '2010-01-01'");

        $this->provider->updateLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 44);

        $this->assertEquals("Kinikit\Persistence\UPF\Framework\NewObjectWithId||44||" . date('Y-m-d H:i'), DefaultDB::instance()->queryForSingleValue("SELECT object_class || '||' || object_pk || '||' || SUBSTR(last_modified, 1, 16) FROM kinikit_object_locking WHERE object_pk = 44"));

        $this->provider->updateLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 55);

        $this->assertEquals("Kinikit\Persistence\UPF\Framework\NewObjectWithId||55||" . date('Y-m-d H:i'), DefaultDB::instance()->queryForSingleValue("SELECT object_class || '||' || object_pk || '||' || SUBSTR(last_modified, 1, 16) FROM kinikit_object_locking WHERE object_pk = 55"));

    }

    public function testIsObjectLockedReturnsFalseOnlyIfPrestoredLockingDateIfLockingDataContainsDateWhichIsLessThanTheStoredDate() {

        // Check that no problems if no data has been stored
        $this->assertFalse($this->provider->isObjectLocked(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 55, '2009-01-01'));
        $this->assertFalse($this->provider->isObjectLocked(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 77, '2011-01-01'));

        // Insert some data
        DefaultDB::instance()->query("INSERT INTO kinikit_object_locking (object_class, object_pk, last_modified) VALUES ('Kinikit\\Persistence\\UPF\\Framework\\NewObjectWithId', 23, '2010-11-01')");

        // Check that a date in the future returns false for locked
        $this->assertFalse($this->provider->isObjectLocked(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 23, '2011-01-01'));
        $this->assertFalse($this->provider->isObjectLocked(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 23, '2010-12-01'));

        // But check that a date in the past returns true for locked
        $this->assertTrue($this->provider->isObjectLocked(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 23, '2010-10-31'));
        $this->assertTrue($this->provider->isObjectLocked(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 23, '2009-12-01'));

    }

    /**
     * @group dev
     */
    public function testDatabaseTransactionsAreStartedCommittedAndRolledBackWhenTransactionalHooksAreCalled() {

        $this->assertEquals(0, DefaultDB::instance()->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_locking"));

        // Start a transaction
        $this->provider->persistenceTransactionStarted();

        $this->provider->updateLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 44);
        $this->assertEquals(1, DefaultDB::instance()->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_locking"));
        $this->provider->updateLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 55);
        $this->assertEquals(2, DefaultDB::instance()->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_locking"));

        $this->provider->persistenceTransactionFailed();

        // Check for rollback
        $this->assertEquals(0, DefaultDB::instance()->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_locking"));

        // Try again

        $this->provider->persistenceTransactionStarted();

        $this->provider->updateLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 44);
        $this->assertEquals(1, DefaultDB::instance()->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_locking"));
        $this->provider->updateLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 55);
        $this->assertEquals(2, DefaultDB::instance()->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_locking"));

        $this->provider->persistenceTransactionSucceeded();

        // Check we are ok
        $this->assertEquals(2, DefaultDB::instance(true)->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_locking"));

    }

    public function testIfIgnoreFailuresSetLockingProviderFailsSilentlyAndAlwaysReturnsFalseForObjectLocked() {

        $badProvider = new SQLOptimisticLockingProvider(new MySQLDatabaseConnection("localhost"), true);

        // Check transaction functions fail silently
        $badProvider->persistenceTransactionStarted();
        $badProvider->persistenceTransactionFailed();
        $badProvider->persistenceTransactionSucceeded();

        // Check update fails silently
        $badProvider->updateLockingDataForObject(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 44);

        // Check is locked always returns false
        $this->assertFalse($badProvider->isObjectLocked(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId"), 55, '2009-01-01'));

    }


}

?>