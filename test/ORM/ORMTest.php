<?php

namespace Kinikit\Persistence\ORM;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Reflection\ClassInspectorProvider;
use Kinikit\Core\Testing\MockObject;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\ORM\Exception\ObjectNotFoundException;
use Kinikit\Persistence\ORM\Interceptor\ConfigFileORMInterceptor;
use Kinikit\Persistence\ORM\Interceptor\GlobalORMInterceptor;
use Kinikit\Persistence\ORM\Interceptor\InlineORMInterceptor;
use Kinikit\Persistence\ORM\Interceptor\ORMInterceptorProcessor;
use Kinikit\Persistence\ORM\Mapping\ORMMapping;
use Kinikit\Persistence\TableMapper\Mapper\TableMapper;
use Kinikit\Persistence\TableMapper\Mapper\TablePersistenceEngine;
use Kinikit\Persistence\TableMapper\Mapper\TableQueryEngine;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class ORMTest extends TestCase {

    /**
     * @var ORM
     */
    private $orm;

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;


    public function setUp(): void {
        parent::setUp();

        ORMMapping::clearMappings();

        // Reset interceptors
        Container::instance()->set(GlobalORMInterceptor::class, new GlobalORMInterceptor());
        Container::instance()->set(ConfigFileORMInterceptor::class, new ConfigFileORMInterceptor());
        Container::instance()->set(InlineORMInterceptor::class, new InlineORMInterceptor());
        Container::instance()->set(ORMInterceptorProcessor::class, new ORMInterceptorProcessor(new ClassInspectorProvider()));


        $this->orm = Container::instance()->get(ORM::class);

        $this->databaseConnection = Container::instance()->get(DatabaseConnection::class);
        $this->databaseConnection->executeScript(file_get_contents(__DIR__ . "/orm.sql"));


    }

    public function testCanFetchSimpleObjectsByPrimaryKey() {

        // Try regular class with convention driven properties

        $address = $this->orm->fetch(Address::class, 1);
        $targetAddress = new Address(1, "Oxil", "Lincoln House", "Pony Road", "01865 777777", "GB");
        $this->assertEquals($targetAddress, $address);

        $address = $this->orm->fetch(Address::class, 2);
        $this->assertEquals(new Address(2, "Home", "3 Some Street", "Somewhere",
            "01865 111111", "GB"), $address);


        try {

            $this->orm->fetch(Address::class, 5);
            $this->fail("Should have thrown here");

        } catch (ObjectNotFoundException $e) {
            // Success
        }


        // Try class with custom table name and column definitions.

        $address = $this->orm->fetch(AltAddress::class, 1);
        $this->assertEquals(new AltAddress(1, "Oxil", "Lincoln House", "Pony Road", "01865 777777", "GB"), $address);


        $address = $this->orm->fetch(AltAddress::class, 2);
        $this->assertEquals(new AltAddress(2, "Home", "3 Some Street", "Somewhere",
            "01865 111111", "GB"), $address);


    }


    public function testCanFetchMultipleSimpleObjectsByPrimaryKey() {

        $matches = $this->orm->multiFetch(Address::class, [2, 1]);

        $this->assertEquals(2, sizeof($matches));
        $this->assertEquals(new Address(1, "Oxil", "Lincoln House", "Pony Road", "01865 777777", "GB"), $matches[1]);
        $this->assertEquals(new Address(2, "Home", "3 Some Street", "Somewhere", "01865 111111", "GB"), $matches[2]);

        $matches = $this->orm->multiFetch(AltAddress::class, [2, 1]);

        $this->assertEquals(2, sizeof($matches));
        $this->assertEquals(new AltAddress(1, "Oxil", "Lincoln House", "Pony Road", "01865 777777", "GB"), $matches[1]);
        $this->assertEquals(new AltAddress(2, "Home", "3 Some Street", "Somewhere", "01865 111111", "GB"), $matches[2]);

    }


    public function testCanFilterSimpleObjects() {

        $matches = $this->orm->filter(Address::class, "WHERE name = ?", 'Holiday Home');
        $this->assertEquals(1, sizeof($matches));
        $this->assertEquals(new Address(3, "Holiday Home", "22 Some Lane", "Nice", "5654646", "FR"), $matches[0]);

        $matches = $this->orm->filter(AltAddress::class, "WHERE altPhoneNumber LIKE ?", '01865%');
        $this->assertEquals(2, sizeof($matches));
        $this->assertEquals(new AltAddress(1, "Oxil", "Lincoln House", "Pony Road", "01865 777777", "GB"), $matches[0]);
        $this->assertEquals(new AltAddress(2, "Home", "3 Some Street", "Somewhere", "01865 111111", "GB"), $matches[1]);

    }


    public function testCanGetValuesForSimpleObjects() {

        $matches = $this->orm->values(Address::class, "COUNT(*)");
        $this->assertEquals(1, sizeof($matches));
        $this->assertEquals([3], $matches);

        $matches = $this->orm->values(Address::class, "COUNT(*)", "GROUP BY phoneNumber");
        $this->assertEquals(3, sizeof($matches));
        $this->assertEquals([1, 1, 1], $matches);


        $matches = $this->orm->values(Address::class, ["phoneNumber", "COUNT(*)"], "GROUP BY phoneNumber");
        $this->assertEquals(3, sizeof($matches));
        $this->assertEquals([["phoneNumber" => "01865 111111", "COUNT(*)" => 1], ["phoneNumber" => "01865 777777", "COUNT(*)" => 1], ["phoneNumber" => "5654646", "COUNT(*)" => 1]], $matches);
    }


    public function testCanSaveSimpleObjects() {

        $address = new Address(null, "James Bond", "Moneypenny Ave", "Q Street", "01111 222222", "US");
        $this->orm->save($address);

        $this->assertEquals(4, $address->getId());

        $reAddress = $this->orm->fetch(Address::class, 4);
        $this->assertEquals(new Address(4, "James Bond", "Moneypenny Ave", "Q Street", "01111 222222", "US"), $reAddress);


        $address1 = new Address(null, "Mary Jones", "Moneypenny Ave", "Q Street", "01111 222222", "US");
        $address2 = new Address(null, "Peter Jones", "Moneypenny Ave", "Q Street", "01111 222222", "US");
        $this->orm->save([$address1, $address2]);
        $this->assertEquals(5, $address1->getId());
        $this->assertEquals(6, $address2->getId());

        $this->assertEquals($address1, $this->orm->fetch(Address::class, 5));
        $this->assertEquals($address2, $this->orm->fetch(Address::class, 6));


    }


    public function testCanDeleteSimpleObjects() {

        $address = $this->orm->fetch(Address::class, 2);
        $this->orm->delete($address);

        try {
            $this->orm->fetch(Address::class, 2);
            $this->fail("Should have thrown here");
        } catch (ObjectNotFoundException $e) {
            // Success
            $this->assertTrue(true);
        }

    }


    public function testInterceptorsAreFiredCorrectlyForLifecycleEvents() {


        /**
         * @var MockObjectProvider $mockObjectProvider
         *
         * Set up a mock processor
         *
         */
        $mockObjectProvider = Container::instance()->get(MockObjectProvider::class);
        $interceptorProcessor = $mockObjectProvider->getMockInstance(ORMInterceptorProcessor::class);
        Container::instance()->set(ORMInterceptorProcessor::class, $interceptorProcessor);

        $orm = new ORM(new TableMapper(new TableQueryEngine(), new TablePersistenceEngine()));


        // Test for a vetoing interceptor.
        $interceptorProcessor->returnValue("processPostMapInterceptors", []);
        try {
            $orm->fetch(Address::class, 2);
            $this->fail("Should have thrown here");
        } catch (ObjectNotFoundException $e) {
            // Success
        }
        $this->assertTrue($interceptorProcessor->methodWasCalled("processPostMapInterceptors"));


        $interceptorProcessor->resetMethodCallHistory("processPostMapInterceptors");

        $address1 = new Address(1, "Oxil", "Lincoln House", "Pony Road", "01865 777777", "GB");
        $address2 = new Address(2, "Home", "3 Some Street", "Somewhere",
            "01865 111111", "GB");

        // Set it so that only one address is returned from post map.
        $interceptorProcessor->returnValue("processPostMapInterceptors", [1 => $address1], [Address::class, [$address1]]);


        $result = $orm->fetch(Address::class, 1);
        $this->assertEquals($address1, $result);


        $this->assertTrue($interceptorProcessor->methodWasCalled("processPostMapInterceptors", [Address::class, [$address1]]));


        // Set it so that only one address is returned from post map.
        $interceptorProcessor->returnValue("processPostMapInterceptors", [1 => $address1], [Address::class, [2 => $address2, 1 => $address1]]);

        $matches = $orm->multiFetch(Address::class, [2, 1]);

        $this->assertEquals(1, sizeof($matches));
        $this->assertEquals(new Address(1, "Oxil", "Lincoln House", "Pony Road", "01865 777777", "GB"), $matches[1]);

        $this->assertTrue($interceptorProcessor->methodWasCalled("processPostMapInterceptors", [Address::class, [2 => $address2, 1 => $address1]]));


        $orm->save($address1);

        $this->assertTrue($interceptorProcessor->methodWasCalled("processPreSaveInterceptors", [Address::class, [$address1]]));
        $this->assertTrue($interceptorProcessor->methodWasCalled("processPostSaveInterceptors", [Address::class, [$address1]]));


        $orm->delete($address1);

        $this->assertTrue($interceptorProcessor->methodWasCalled("processPreDeleteInterceptors", [Address::class, [$address1]]));
        $this->assertTrue($interceptorProcessor->methodWasCalled("processPostDeleteInterceptors", [Address::class, [$address1]]));


    }


    public function testEntitiesWithRelationshipsAreRecursivelyMapped() {

        $contact = $this->orm->fetch(Contact::class, 1);

        $this->assertEquals("Mark", $contact->getName());
        $this->assertEquals($this->orm->fetch(Address::class, 1), $contact->getPrimaryAddress());
        $this->assertEquals(array_values($this->orm->multiFetch(Address::class, [2, 3])), $contact->getOtherAddresses());
        $this->assertEquals($this->orm->fetch(Profile::class, 1), $contact->getProfile());
        $this->assertEquals($this->orm->fetch(Address::class, 1), $contact->getPrimaryAddress());
        $this->assertEquals(array_values($this->orm->multiFetch(PhoneNumber::class, [2, 1])), $contact->getPhoneNumbers());

    }


    public function testEntitiesWithRelationshipsAreRecursivelyAssociatedAndSavedWithDefaultRules() {

        $primaryAddress = new Address(null, "Oxford Swimming", "3 The Lane", "Notown", "07565 898989", "GB");

        // Create a new contact with one to one address and save
        $newContact = new Contact("Bobby Brown", $primaryAddress);
        $this->orm->save($newContact);
        $this->assertNotNull($newContact->getId());
        $this->assertNotNull($newContact->getPrimaryAddress()->getId());

        $this->assertNull($newContact->getProfile());
        $this->assertEquals([], $newContact->getOtherAddresses());
        $this->assertEquals([], $newContact->getPhoneNumbers());

        $reContact = $this->orm->fetch(Contact::class, $newContact->getId());


        $this->assertEquals("Bobby Brown", $reContact->getName());
        $this->assertEquals($this->orm->fetch(Address::class, $primaryAddress->getId()), $reContact->getPrimaryAddress());
        $this->assertEquals([], $reContact->getPhoneNumbers());
        $this->assertEquals([], $reContact->getOtherAddresses());
        $this->assertNull($reContact->getProfile());


        $reContact->setProfile(new Profile(null, date_create_from_format("d/m/Y", "06/12/1977"), new \DateTime()));
        $this->orm->save($reContact);
        $this->assertNotNull($reContact->getProfile()->getId());

        $reContact = $this->orm->fetch(Contact::class, $newContact->getId());


        $this->assertEquals("Bobby Brown", $reContact->getName());
        $this->assertEquals($this->orm->fetch(Address::class, $primaryAddress->getId()), $reContact->getPrimaryAddress());
        $this->assertEquals($this->orm->fetch(Profile::class, $reContact->getProfile()->getId()), $reContact->getProfile());
        $this->assertEquals([], $reContact->getPhoneNumbers());
        $this->assertEquals([], $reContact->getOtherAddresses());


        $phoneNumbers = [new PhoneNumber(null, "Business", "07548 989898"), new PhoneNumber(null, "Home", "07434 232434")];
        $reContact->setPhoneNumbers($phoneNumbers);

        $this->orm->save($reContact);
        $this->assertNotNull($reContact->getPhoneNumbers()[0]->getId());
        $this->assertNotNull($reContact->getPhoneNumbers()[1]->getId());


        $reContact = $this->orm->fetch(Contact::class, $newContact->getId());
        $this->assertEquals("Bobby Brown", $reContact->getName());
        $this->assertEquals($this->orm->fetch(Address::class, $primaryAddress->getId()), $reContact->getPrimaryAddress());
        $this->assertEquals($this->orm->fetch(Profile::class, $reContact->getProfile()->getId()), $reContact->getProfile());
        $this->assertEquals([$this->orm->fetch(PhoneNumber::class, $reContact->getPhoneNumbers()[0]->getId()),
            $this->orm->fetch(PhoneNumber::class, $reContact->getPhoneNumbers()[1]->getId())], $reContact->getPhoneNumbers());
        $this->assertEquals([], $reContact->getOtherAddresses());


        $otherAddresses = [$this->orm->fetch(Address::class, 3), $this->orm->fetch(Address::class, 2)];
        $reContact->setOtherAddresses($otherAddresses);
        $this->orm->save($reContact);

        $reContact = $this->orm->fetch(Contact::class, $newContact->getId());
        $this->assertEquals("Bobby Brown", $reContact->getName());
        $this->assertEquals($this->orm->fetch(Address::class, $primaryAddress->getId()), $reContact->getPrimaryAddress());
        $this->assertEquals($this->orm->fetch(Profile::class, $reContact->getProfile()->getId()), $reContact->getProfile());
        $this->assertEquals([$this->orm->fetch(PhoneNumber::class, $reContact->getPhoneNumbers()[0]->getId()),
            $this->orm->fetch(PhoneNumber::class, $reContact->getPhoneNumbers()[1]->getId())], $reContact->getPhoneNumbers());
        $this->assertEquals([$this->orm->fetch(Address::class, 3), $this->orm->fetch(Address::class, 2)], $reContact->getOtherAddresses());

    }


    public function testEntitiesWithDefaultRelationshipsAreUnrelatedAndDeletedAppropriatelyOnSave() {

        $contact = $this->orm->fetch(Contact::class, 1);
        $this->assertEquals(array_values($this->orm->multiFetch(Address::class, [2, 3])), $contact->getOtherAddresses());
        $this->assertEquals($this->orm->fetch(Profile::class, 1), $contact->getProfile());
        $this->assertEquals($this->orm->fetch(Address::class, 1), $contact->getPrimaryAddress());
        $this->assertEquals(array_values($this->orm->multiFetch(PhoneNumber::class, [2, 1])), $contact->getPhoneNumbers());

        // Check nulling many to one
        $contact->setPrimaryAddress(null);
        $this->orm->save($contact);

        // Address should still exist (many to one).
        $this->orm->fetch(Address::class, 1);

        // Check unrelated correctly.
        $contact = $this->orm->fetch(Contact::class, 1);
        $this->assertNull($contact->getPrimaryAddress());


        // Check nulling one to one.
        $contact->setProfile(null);
        $this->orm->save($contact);

        $contact = $this->orm->fetch(Contact::class, 1);
        $this->assertNull($contact->getProfile());

        // This should be deleted.
        try {
            $this->orm->fetch(Profile::class, 1);
            $this->fail("Should have thrown here");
        } catch (ObjectNotFoundException $e) {
            // Success
        }


        // Check blanking phone numbers (one to many).
        $contact->setPhoneNumbers([]);
        $this->orm->save($contact);

        $contact = $this->orm->fetch(Contact::class, 1);
        $this->assertEquals([], $contact->getPhoneNumbers());

        // Check deleted.
        $this->assertEquals([], $this->orm->multiFetch(PhoneNumber::class, [2, 1], true));


        // Check removing many to many
        $contact->setOtherAddresses([]);
        $this->orm->save($contact);

        // Check that this has been unrelated but addresses still exist.
        $contact = $this->orm->fetch(Contact::class, 1);
        $this->assertEquals([], $contact->getOtherAddresses());
        $this->assertEquals(2, sizeof($this->orm->multiFetch(Address::class, [2, 3])));


    }


    public function testEntitiesWithDefaultRelationshipsAreDeletedOrUnrelatedCorrectlyOnDelete() {


        $contact = $this->orm->fetch(Contact::class, 1);
        $this->orm->delete($contact);


        try {
            $this->orm->fetch(Contact::class, 1);
            $this->fail("Should be deleted here");
        } catch (ObjectNotFoundException $e) {
            // Success
        }


        // Address should still exist (many to one).
        $this->orm->fetch(Address::class, 1);

        // Profile should be deleted (one to one)
        try {
            $this->orm->fetch(Profile::class, 1);
            $this->fail("Should have thrown here");
        } catch (ObjectNotFoundException $e) {
            // Success
        }


        // Phone numbers should be deleted (one to many)
        $this->assertEquals([], $this->orm->multiFetch(PhoneNumber::class, [2, 1], true));


        // Other Addresses should still exist (many to many)
        $this->assertEquals(2, sizeof($this->orm->multiFetch(Address::class, [2, 3])));

        // Link entries should not exist.
        $this->assertEquals(0, $this->databaseConnection->query("SELECT COUNT(*) total FROM contact_other_addresses WHERE contact_id = 1")->nextRow()["total"]);


    }


    public function testDateFieldsAreCorrectlyMappedToDateObjects() {

        $profile = $this->orm->fetch(Profile::class, 1);
        $this->assertEquals(1, $profile->getId());
        $this->assertEquals("1977-12-06", $profile->getDateOfBirth()->format("Y-m-d"));
        $this->assertEquals("2019-01-01 14:33:22", $profile->getInstantiated()->format("Y-m-d H:i:s"));


        // Save back
        $profile->setDateOfBirth(date_create_from_format("d/m/Y", "01/01/1988"));

        $this->orm->save($profile);

        $profile = $this->orm->fetch(Profile::class, 1);
        $this->assertEquals("1988-01-01", $profile->getDateOfBirth()->format("Y-m-d"));


    }

    public function testJSONFieldsAreCorrectlyMapped() {

        $profile = $this->orm->fetch(Profile::class, 1);
        $this->assertEquals(1, $profile->getId());
        $this->assertEquals(["test" => "Mark", "live" => "Luke"],
            $profile->getData());

        $profile = $this->orm->fetch(Profile::class, 2);
        $this->assertEquals(2, $profile->getId());
        $this->assertEquals([1, 2, 3, 4, 5],
            $profile->getData());


    }


//    public function testProxyObjectsAreMappedInsteadIfAnyLazyLoadedProperties() {
//
////        $contact = $this->orm->fetch(LazyContact::class, 1);
////        $this->assertEquals("Kinikit\Persistence\ORM\LazyContactProxy", get_class($contact));
//
//    }

}
