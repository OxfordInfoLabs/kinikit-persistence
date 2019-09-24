<?php

namespace Kinikit\Persistence\ORM\Mapping;

use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\ORM\Contact;
use Kinikit\Persistence\ORM\PhoneNumber;
use Kinikit\Persistence\ORM\Profile;
use Kinikit\Persistence\ORM\Address;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

/**
 * Created by PhpStorm.
 * User: mark
 * Date: 23/09/2019
 * Time: 15:27
 */
class ORMMappingTest extends TestCase {

    public function testCanGenerateTableMetaDataForSimpleObject() {

        $ormMapping = ORMMapping::get(Address::class);
        $metaData = $ormMapping->generateTableMetaData();
        $this->assertEquals(1, sizeof($metaData));

        /**
         * @var TableMetaData $addressMD
         */
        $addressMD = $metaData["address"];
        $this->assertTrue($addressMD instanceof TableMetaData);
        $this->assertEquals("address", $addressMD->getTableName());

        $columns = $addressMD->getColumns();
        $this->assertEquals(6, sizeof($columns));
        $this->assertEquals(new TableColumn("id", TableColumn::SQL_INTEGER, null, null, null, true, true, true), $columns["id"]);
        $this->assertEquals(new TableColumn("name", TableColumn::SQL_VARCHAR, 50, null, null, false, false, true), $columns["name"]);
        $this->assertEquals(new TableColumn("street_1", TableColumn::SQL_VARCHAR, 100, null, null, false, false, true), $columns["street_1"]);

        $this->assertEquals(new TableColumn("street_2", TableColumn::SQL_VARCHAR, 80, null, null, false, false, false), $columns["street_2"]);
        $this->assertEquals(new TableColumn("phone_number", TableColumn::SQL_VARCHAR), $columns["phone_number"]);
        $this->assertEquals(new TableColumn("country_code", TableColumn::SQL_VARCHAR), $columns["country_code"]);


    }


    public function testCanGenerateTableMetaDataWhenExplicitSQLTypesArePassed() {

        $ormMapping = ORMMapping::get(Profile::class);
        $metaData = $ormMapping->generateTableMetaData();
        $this->assertEquals(1, sizeof($metaData));

        /**
         * @var TableMetaData $profileMD
         */
        $profileMD = $metaData["profile"];
        $this->assertTrue($profileMD instanceof TableMetaData);
        $this->assertEquals("profile", $profileMD->getTableName());

        $columns = $profileMD->getColumns();
        $this->assertEquals(4, sizeof($columns));
        $this->assertEquals(new TableColumn("id", TableColumn::SQL_INTEGER, null, null, null, true, true, true), $columns["id"]);
        $this->assertEquals(new TableColumn("date_of_birth", TableColumn::SQL_DATE), $columns["date_of_birth"]);
        $this->assertEquals(new TableColumn("instantiated", TableColumn::SQL_DATE_TIME), $columns["instantiated"]);
        $this->assertEquals(new TableColumn("data", TableColumn::SQL_VARCHAR, 500), $columns["data"]);

    }


    public function testRelationalMetaTablesAndColumnsAreAddedAsRequiredWhenGeneratingTableMetaDataForAParentEntity() {

        $ormMapping = ORMMapping::get(Contact::class);
        $metaData = $ormMapping->generateTableMetaData();

        $this->assertEquals(2, sizeof($metaData));

        // Check extra columns added for Many to One relationships.

        /**
         * @var TableMetaData $contactMD
         */
        $contactMD = $metaData["contact"];
        $this->assertEquals("contact", $contactMD->getTableName());

        $columns = $contactMD->getColumns();
        $this->assertEquals(3, sizeof($columns));
        $this->assertEquals(new TableColumn("id", TableColumn::SQL_INTEGER, null, null, null, true, true, true), $columns["id"]);
        $this->assertEquals(new TableColumn("name", TableColumn::SQL_VARCHAR), $columns["name"]);
        $this->assertEquals(new TableColumn("primary_address_id", TableColumn::SQL_INTEGER), $columns["primary_address_id"]);


        // Check link table created for Many to Many relationships.

        /**
         * @var TableMetaData $contactOtherAddressesMD
         */
        $contactOtherAddressesMD = $metaData["contact_other_addresses"];
        $this->assertEquals("contact_other_addresses", $contactOtherAddressesMD->getTableName());
        $columns = $contactOtherAddressesMD->getColumns();

        $this->assertEquals(2, sizeof($columns));
        $this->assertEquals(new TableColumn("contact_id", TableColumn::SQL_INTEGER, null, null, null, true, false, true), $columns["contact_id"]);


        // Check child mapping updated for One to * relationships.
        $phoneNumberMD = ORMMapping::get(PhoneNumber::class)->generateTableMetaData()["phone_number"];

        $this->assertEquals(new TableColumn("contact_id", TableColumn::SQL_INTEGER, null, null, null, false, false, false), $phoneNumberMD->getColumns()["contact_id"]);

        $profileMD = ORMMapping::get(Profile::class)->generateTableMetaData()["profile"];
        $this->assertEquals(new TableColumn("contact_id", TableColumn::SQL_INTEGER, null, null, null, false, false, false), $profileMD->getColumns()["contact_id"]);


    }

}