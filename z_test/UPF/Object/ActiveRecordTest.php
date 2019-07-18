<?php

namespace Kinikit\Persistence\UPF\Object;


use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

class ActiveRecordTest extends \PHPUnit\Framework\TestCase {


    public function setUp():void {

        DefaultDB::instance()->query("DROP TABLE IF EXISTS test_active_record");
        DefaultDB::instance()->query("CREATE TABLE test_active_record (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), age INTEGER, nickname VARCHAR(255), container_tag VARCHAR(255))");


    }


    public function testCanSaveNewActiveRecord() {

        $newRecord = new TestActiveRecord("Bob", 33, "Bobby");
        $newRecord->save();

        $this->assertEquals(1, DefaultDB::instance()->queryForSingleValue("SELECT COUNT(*) FROM test_active_record WHERE id = 1 AND age = 33 AND nickname = 'Bobby' AND name = 'Bob'"));


    }


    public function testCanRemoveActiveRecord() {

        $newRecord = new TestActiveRecord("Bob", 33, "Bobby");
        $newRecord->save();
        $this->assertEquals(1, DefaultDB::instance()->queryForSingleValue("SELECT COUNT(*) FROM test_active_record WHERE id = 1 AND age = 33 AND nickname = 'Bobby' AND name = 'Bob'"));

        $newRecord->remove();

        $this->assertEquals(0, DefaultDB::instance()->queryForSingleValue("SELECT COUNT(*) FROM test_active_record WHERE id = 1 AND age = 33 AND nickname = 'Bobby' AND name = 'Bob'"));

    }


    public function testCanQueryForActiveRecords() {

        $newRecord1 = new TestActiveRecord("Bob", 33, "Bobby");
        $newRecord1->save();

        $newRecord2 = new TestActiveRecord("Mary", 22, "Maz");
        $newRecord2->save();

        $newRecord3 = new TestActiveRecord("Jane", 44, "Janey");
        $newRecord3->save();


        $records = TestActiveRecord::query("WHERE name = ?", "Bob");
        $this->assertEquals(1, sizeof($records));
        $this->assertEquals($newRecord1, $records[0]);

        $records = TestActiveRecord::query("WHERE age > ?", 22);
        $this->assertEquals(2, sizeof($records));
        $this->assertEquals($newRecord1, $records[0]);
        $this->assertEquals($newRecord3, $records[1]);

    }


    public function testCanCountQueryForActiveRecords() {

        $newRecord1 = new TestActiveRecord("Bob", 33, "Bobby");
        $newRecord1->save();

        $newRecord2 = new TestActiveRecord("Mary", 22, "Maz");
        $newRecord2->save();

        $newRecord3 = new TestActiveRecord("Jane", 44, "Janey");
        $newRecord3->save();


        $records = TestActiveRecord::countQuery("WHERE name = ?", "Bob");
        $this->assertEquals(1, $records);

        $records = TestActiveRecord::countQuery("WHERE age > ?", 22);
        $this->assertEquals(2, $records);

    }

}
