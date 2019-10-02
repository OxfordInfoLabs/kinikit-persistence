<?php

namespace Kinikit\Persistence\ORM\Tools;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3DatabaseConnection;
use Kinikit\Persistence\Objects\Address;
use Kinikit\Persistence\Objects\Contact;
use Kinikit\Persistence\Objects\Note;
use Kinikit\Persistence\Objects\Subordinates\PhoneNumber;
use Kinikit\Persistence\Objects\Subordinates\Profile;
use Kinikit\Persistence\ORM\Mapping\ORMMapping;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

/**
 * Created by PhpStorm.
 * User: mark
 * Date: 23/09/2019
 * Time: 14:16
 */
class SchemaGeneratorTest extends TestCase {


    public function testCanGenerateMetaDataForTreeOfObjects() {

        /**
         * @var SchemaGenerator $schemaGenerator
         */
        $schemaGenerator = Container::instance()->get(SchemaGenerator::class);
        $generatedSchema = $schemaGenerator->generateTableMetaData();

        $this->assertEquals(6, sizeof($generatedSchema));
        $this->assertEquals(ORMMapping::get(Contact::class)->generateTableMetaData()["new_contact"], $generatedSchema["new_contact"]);
        $this->assertEquals(ORMMapping::get(Contact::class)->generateTableMetaData()["new_contact_other_addresses"], $generatedSchema["new_contact_other_addresses"]);
        $this->assertEquals(ORMMapping::get(Address::class)->generateTableMetaData()["new_address"], $generatedSchema["new_address"]);
        $this->assertEquals(ORMMapping::get(Profile::class)->generateTableMetaData()["new_profile"], $generatedSchema["new_profile"]);
        $this->assertEquals(ORMMapping::get(PhoneNumber::class)->generateTableMetaData()["new_phone_number"], $generatedSchema["new_phone_number"]);
        $this->assertEquals(ORMMapping::get(Note::class)->generateTableMetaData()["new_note"], $generatedSchema["new_note"]);

    }


    public function testCanCreateSchemaForTreeOfObjects() {

        /**
         * @var DatabaseConnection $databaseConnection
         */
        $databaseConnection = Container::instance()->get(DatabaseConnection::class);
        $databaseConnection->executeScript(file_get_contents(__DIR__ . "/drop.sql"));


        /**
         * @var SchemaGenerator $schemaGenerator
         */
        $schemaGenerator = Container::instance()->get(SchemaGenerator::class);
        $schemaGenerator->createSchemaForPath();


        $contact = $databaseConnection->getTableMetaData("new_contact");
        $this->assertEquals(3, sizeof($contact->getColumns()));
        $this->assertEquals(new TableColumn("id", "INTEGER", null, null, null, true, true, true), $contact->getColumns()["id"]);

        $this->assertEquals(new TableColumn("name", "VARCHAR", 50, null, null, false, false, true), $contact->getColumns()["name"]);

        $this->assertEquals(new TableColumn("primary_address_id", "INTEGER"), $contact->getColumns()["primary_address_id"]);


        $profile = $databaseConnection->getTableMetaData("new_profile");
        $this->assertEquals(5, sizeof($profile->getColumns()));
        $this->assertEquals(new TableColumn("id", "INTEGER", null, null, null, true, true, true), $profile->getColumns()["id"]);

        $this->assertEquals(new TableColumn("date_of_birth", "DATE"), $profile->getColumns()["date_of_birth"]);

        $this->assertEquals(new TableColumn("instantiated", "DATETIME"), $profile->getColumns()["instantiated"]);

        $this->assertEquals(new TableColumn("data", "VARCHAR", 500), $profile->getColumns()["data"]);

        $this->assertEquals(new TableColumn("new_contact_id", "INTEGER"), $profile->getColumns()["new_contact_id"]);


    }

}
