<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Exception\BadParameterException;
use Kinikit\Core\Exception\ValidationException;
use Kinikit\Persistence\Database\Connection\MySQL\MySQLDatabaseConnection;
use Kinikit\Persistence\UPF\Engines\ObjectIndex\ObjectIndexPersistenceEngine;
use Kinikit\Persistence\UPF\Exception\InvalidFieldRelationshipException;
use Kinikit\Persistence\UPF\Exception\NoEnabledEngineException;
use Kinikit\Persistence\UPF\Exception\NoneExistentEngineException;
use Kinikit\Persistence\UPF\Exception\ObjectNotFoundException;
use Kinikit\Persistence\UPF\Exception\OptimisticLockingException;
use Kinikit\Persistence\UPF\Exception\UPFObjectDeleteVetoedException;
use Kinikit\Persistence\UPF\Exception\UPFObjectSaveVetoedException;
use Kinikit\Persistence\UPF\FieldFormatters\DateFieldFormatter;
use Kinikit\Persistence\UPF\LockingProviders\SQLOptimisticLockingProvider;

include_once "autoloader.php";

/**
 * Test cases for the object persistence coordinator.
 *
 * @author mark
 *
 */
class ObjectPersistenceCoordinatorTest extends \PHPUnit\Framework\TestCase {

    public function setUp():void {

        TestPersistenceEngine::$storedValues = array();
        TestPersistenceEngine::$removedValues = array();
        TestPersistenceEngine::$incrementId = 0;
        TestPersistenceEngine::$writes = 0;
        TestPersistenceEngine::$returnMap = array();
        TestPersistenceEngine::$relatedPksMap = array();
        TestPersistenceEngine::$relatedMap = array();
        TestPersistenceEngine::$relatedObjectMap = array();

        TestAnotherPersistenceEngine::$storedValues = array();
        TestAnotherPersistenceEngine::$removedValues = array();
        TestAnotherPersistenceEngine::$incrementId = 0;
        TestAnotherPersistenceEngine::$writes = 0;
        TestAnotherPersistenceEngine::$returnMap = array();
        TestAnotherPersistenceEngine::$relatedPksMap = array();
        TestAnotherPersistenceEngine::$relatedMap = array();

    }

    public function testCanIncludeChildUPFMappingFilesAndAllPropertiesAreMerged() {

        $coordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/masterpersistence.xml");

        // Grab the comparison data
        $engines = $coordinator->getEngines();
        $interceptors = $coordinator->getInterceptorEvaluator()->getInterceptors();
        $lockingProvider = $coordinator->getOptimisticLockingProvider();
        $fieldFormatters = $coordinator->getFieldFormatters();
        $objectMappers = $coordinator->getMapperManager();

        // Now check it all merged correctly
        $this->assertEquals(2, sizeof($engines));
        $this->assertEquals("sql-slave", $engines [0]->getIdentifier());
        $this->assertEquals("sql", $engines [1]->getIdentifier());

        $this->assertEquals(new SQLOptimisticLockingProvider (), $lockingProvider);

        $this->assertEquals(2, sizeof($interceptors));
        $this->assertEquals(new TestObjectInterceptor1 (), $interceptors [0]);
        $this->assertEquals(new TestObjectInterceptor2 (), $interceptors [1]);

        $this->assertEquals(7, sizeof($fieldFormatters));
        $this->assertEquals("britishdateformat", $fieldFormatters [0]->getIdentifier());
        $this->assertEquals("britishdatetimeformat", $fieldFormatters [1]->getIdentifier());
        $this->assertEquals("britishdatesmalltimeformat", $fieldFormatters [2]->getIdentifier());
        $this->assertEquals("smalltimeformat", $fieldFormatters [3]->getIdentifier());
        $this->assertEquals("britishdatelongdatabase", $fieldFormatters [4]->getIdentifier());
        $this->assertEquals("twodecimalplaces", $fieldFormatters [5]->getIdentifier());
        $this->assertEquals("moneyformat", $fieldFormatters [6]->getIdentifier());

        $this->assertTrue($objectMappers->getMapperForClass("Kinikit\Persistence\UPF\Framework\Address") instanceof ObjectMapper);
        $this->assertTrue($objectMappers->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact") instanceof ObjectMapper);

    }

    public function testIfBadParametersPassedOnConstructionExceptionRaised() {

        try {
            $coordinator = new ObjectPersistenceCoordinator (array("Mark", "Paul"));
            $this->fail("Should have thrown here");
        } catch (BadParameterException $e) {
            // Success
        }

        try {
            $coordinator = new ObjectPersistenceCoordinator (array(new TestPersistenceEngine ()), "BadBoy");
            $this->fail("Should have thrown here");
        } catch (BadParameterException $e) {
            // Success
        }

        // Check successful construction if correct
        $coordinator =
            new ObjectPersistenceCoordinator (array(new TestPersistenceEngine ()), new ObjectMapperManager ());

        // Check we can also configure with a single engine
        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());

        $this->assertTrue(true);

    }

    public function testForSimpleObjectSavesTheStorageEngineIsCorrectlyCalledWithAppropriateArguments() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());

        $testObject = new ObjectWithId ("Bob", 5, 7, 1);
        $coordinator->saveObject($testObject);

        $storedValues = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $this->assertEquals(1, sizeof($storedValues));
        $this->assertEquals(array("id" => 1, "name" => "Bob", "age" => 5, "shoeSize" => 7), $storedValues [1]);

        // Try a different one.
        TestPersistenceEngine::$storedValues = array();
        $testObject = new ObjectWithId ("Mary Moo", 15, 10, 35);
        $coordinator->saveObject($testObject);

        $storedValues = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $this->assertEquals(1, sizeof($storedValues));
        $this->assertEquals(array("id" => 35, "name" => "Mary Moo", "age" => 15, "shoeSize" => 10), $storedValues [35]);

    }

    public function testForSimpleObjectSavesWithNewObjectsReturnedPrimaryKeyIsSetOnObjectAfterwards() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());
        $testObject = new ObjectWithId ("Bobby", 13, 7);

        $coordinator->saveObject($testObject);
        $this->assertEquals(1, $testObject->getId());

    }

    public function testForObjectsWithNestedObjectChildAndDefinedChildMasterRelationshipTheStorageEngineIsCorrectlyCalledForParentAndNoRelateMethod() {

        $relationship = new ObjectRelationship ("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId");
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        $testChild = new NewObjectWithId ("Peter", "OX4 7YY", "08998 787878");
        $testObject = new ObjectWithId ("Bob", null, $testChild);
        $coordinator->saveObject($testObject);

        $storedParents = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $storedChildren = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"];

        $this->assertEquals(1, sizeof($storedParents));
        $this->assertEquals(array("name" => "Bob", "age" => "08998 787878", "id" => null), $storedParents [2]);

        $this->assertEquals(1, sizeof($storedChildren));
        $this->assertEquals(array("name" => "Peter", "postcode" => "OX4 7YY", "id" => null,
            "mobile" => "08998 787878"), $storedChildren [1]);


    }

    public function testForObjectsWithNestedObjectChildAndDefinedParentMasterRelationshipTheStorageEngineIsCorrectlyCalledAndObjectsCorrectlyRelated() {

        $relationship =
            new ObjectRelationship ("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId", false, false, false, false, ObjectRelationship::MASTER_PARENT);
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        $testChild = new NewObjectWithId ("Peter", "OX4 7YY");
        $testObject = new ObjectWithId ("Bob", 78, $testChild, 3);

        // Program the return for the get object values
        TestPersistenceEngine::$dataForFieldValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"] ["age"] = array();

        $coordinator->saveObject($testObject);

        $storedParents = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $storedChildren = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"];

        $this->assertEquals(1, sizeof($storedParents));
        $this->assertEquals(array("name" => "Bob", "age" => 78, "id" => 3), $storedParents [3]);

        $this->assertEquals(1, sizeof($storedChildren));
        $this->assertEquals(array("name" => "Peter", "postcode" => "OX4 7YY", "id" => null,
            "mobile" => 78), $storedChildren [1]);

    }

    public function testForObjectsWithNestedObjectChildrenAndDefinedMasterRelationshipTheStorageEngineIsCalledAndAllChildrenRelated() {

        $relationship =
            new ObjectRelationship ("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId", false, false, false, false, ObjectRelationship::MASTER_PARENT);
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        $testChild1 = new NewObjectWithId ("Peter", "OX4 7YY", null, 55);
        $testChild2 = new NewObjectWithId ("Bob", "OX2 7YY", null, 66);
        $testChild3 = new NewObjectWithId ("Sue", "OX3 7YY", null, 77);

        $testObject = new ObjectWithId ("Bob", 78, array($testChild1, $testChild2, $testChild3), 3);

        // Program the return for the get object values
        TestPersistenceEngine::$dataForFieldValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"] ["age"] = array();

        $coordinator->saveObject($testObject);

        $storedParents = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $storedChildren = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"];

        $this->assertEquals(1, sizeof($storedParents));
        $this->assertEquals(array("name" => "Bob", "age" => 78, "id" => 3), $storedParents [3]);

        $this->assertEquals(3, sizeof($storedChildren));
        $this->assertEquals(array("name" => "Peter", "postcode" => "OX4 7YY", "id" => 55,
            "mobile" => 78), $storedChildren [55]);
        $this->assertEquals(array("name" => "Bob", "postcode" => "OX2 7YY", "id" => 66,
            "mobile" => 78), $storedChildren [66]);
        $this->assertEquals(array("name" => "Sue", "postcode" => "OX3 7YY", "id" => 77,
            "mobile" => 78), $storedChildren [77]);

        $testChild4 = new NewObjectWithId ("David", "OX5 7YY", null, 88);

        $testObject->setShoeSize(array($testChild2, $testChild4));

        TestPersistenceEngine::$dataForFieldValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"] [78] =
            array(array("name" => "Peter", "postcode" => "OX4 7YY", "id" => 55, "mobile" => 78),
                array("name" => "Bob", "postcode" => "OX2 7YY", "id" => 66, "mobile" => 78),
                array("name" => "Sue", "postcode" => "OX3 7YY", "id" => 77, "mobile" => 78));

        $coordinator->saveObject($testObject);

        $storedParents = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $storedChildren = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"];
        $deleted = TestPersistenceEngine::$removedValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"];

        $this->assertEquals(1, sizeof($storedParents));
        $this->assertEquals(array("name" => "Bob", "age" => 78, "id" => 3), $storedParents [3]);

        $this->assertEquals(4, sizeof($storedChildren));
        $this->assertEquals(array("name" => "Peter", "postcode" => "OX4 7YY", "id" => 55,
            "mobile" => 78), $storedChildren [55]);
        $this->assertEquals(array("name" => "Bob", "postcode" => "OX2 7YY", "id" => 66,
            "mobile" => 78), $storedChildren [66]);
        $this->assertEquals(array("name" => "Sue", "postcode" => "OX3 7YY", "id" => 77,
            "mobile" => 78), $storedChildren [77]);
        $this->assertEquals(array("name" => "David", "postcode" => "OX5 7YY", "id" => 88,
            "mobile" => 78), $storedChildren [88]);

        $this->assertEquals(2, sizeof($deleted));
        $this->assertEquals(array(55, 77), $deleted);
    }


    public function testReadOnlyMemberObjectsAreNotSaved() {

        $relationship =
            new ObjectRelationship ("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId", false, true, false, false, ObjectRelationship::MASTER_PARENT);
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        $testChild = new NewObjectWithId ("Peter", "OX4 7YY", "07676 878788", 35);
        $testObject = new ObjectWithId ("Bob", 5, $testChild);
        $coordinator->saveObject($testObject);

        $storedParents = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $this->assertFalse(isset (TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"]));

        $this->assertEquals(1, sizeof($storedParents));
        $this->assertEquals(array("name" => "Bob", "age" => 5, "id" => null), $storedParents [1]);

    }

    public function testObjectsAndObjectFacadesAreTreatedAsReadOnly() {

        $relationship =
            new ObjectRelationship ("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId", false, true, false, false, ObjectRelationship::MASTER_PARENT);
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        // Try a facade first of all.
        $testObject = new ObjectWithId ("Bob", 5, new ObjectFacade ("Kinikit\Persistence\UPF\Framework\NewObjectWithId", 83, null));
        $coordinator->saveObject($testObject);

        $storedParents = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $this->assertFalse(isset (TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"]));
        $this->assertFalse(isset (TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectFacade"]));

        $this->assertEquals(1, sizeof($storedParents));
        $this->assertEquals(array("name" => "Bob", "age" => 5, "id" => null), $storedParents [1]);

        $this->setUp();

        // Now try an array facade
        $testObject =
            new ObjectWithId ("Bob", 5, new ObjectArrayFacade (array(new ObjectFacade ("NewObjectWithId", 83, null),
                new ObjectFacade ("NewObjectWithId", 91, null), new ObjectFacade ("NewObjectWithId", 5, null)), null));
        $coordinator->saveObject($testObject);

        $storedParents = TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];

        $this->assertEquals(1, sizeof($storedParents));
        $this->assertEquals(array("name" => "Bob", "age" => 5, "id" => null), $storedParents [1]);

        $this->assertFalse(isset (TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"]));
        $this->assertFalse(isset (TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectArrayFacade"]));

    }

    public function testCanRemoveSimpleObjectAndCorrectDataIsPassedToStorageEngine() {
        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());

        $testObject = new ObjectWithId ("Bob", 5, 7, 1);
        $coordinator->removeObject($testObject);

        // Check that the test storage engine was called appropriately.
        $storedRemovals = TestPersistenceEngine::$removedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];

        $this->assertEquals(1, sizeof($storedRemovals));
        $this->assertEquals(1, $storedRemovals [0]);

    }

    public function testIfObjectHasNestedObjectMemberWithNoDeleteCascadeSpecifiedTheNestedObjectIsIgnoredAndNotRemoved() {
        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());

        $testChild = new NewObjectWithId ("Bill", "OX5 787", "06767 878787", 33);
        $testObject = new ObjectWithId ("Bob", 5, $testChild, 66);
        $coordinator->removeObject($testObject);

        // Check that the test storage engine was called appropriately.
        $removals = TestPersistenceEngine::$removedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $this->assertFalse(isset ($removals ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"]));

        $this->assertEquals(1, sizeof($removals));
        $this->assertEquals(66, $removals [0]);
    }

    public function testIfObjectHasNestedObjectMemberWithMasterChildAndDeleteCascadeSpecifiedTheNestedObjectIsAlsoDeleted() {

        $relationship = new ObjectRelationship ("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId", false, false, false, true);
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        $testChild = new NewObjectWithId ("Bill", "OX5 787", "06767 878787", 33);
        $testObject = new ObjectWithId ("Bob", 5, $testChild, 66);

        TestPersistenceEngine::$dataForFieldValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"] [5] =
            array(array("name" => "Bill", "postcode" => "OX5 787", "id" => 33, "mobile" => 78));

        $coordinator->removeObject($testObject);

        // Check that the test storage engine was called appropriately.
        $parentRemovals = TestPersistenceEngine::$removedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $childRemovals = TestPersistenceEngine::$removedValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"];

        $this->assertEquals(1, sizeof($parentRemovals));
        $this->assertEquals(66, $parentRemovals [0]);

        $this->assertEquals(1, sizeof($childRemovals));
        $this->assertEquals(33, $childRemovals [0]);

    }

    public function testIfObjectHasNestedObjectMemberWithMasterParentAndDeleteCascadeSpecifiedTheNestedObjectIsAlsoDeleted() {

        $relationship =
            new ObjectRelationship ("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId", false, false, false, true, ObjectRelationship::MASTER_PARENT);
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        $testChild = new NewObjectWithId ("Bill", "OX5 787", "06767 878787", 33);
        $testObject = new ObjectWithId ("Bob", 5, $testChild, 66);

        TestPersistenceEngine::$dataForFieldValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"] [5] =
            array(array("name" => "Bill", "postcode" => "OX5 787", "id" => 33, "mobile" => 78));

        $coordinator->removeObject($testObject);

        // Check that the test storage engine was called appropriately.
        $parentRemovals = TestPersistenceEngine::$removedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"];
        $childRemovals = TestPersistenceEngine::$removedValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"];

        $this->assertEquals(1, sizeof($parentRemovals));
        $this->assertEquals(66, $parentRemovals [0]);

        $this->assertEquals(1, sizeof($childRemovals));
        $this->assertEquals(33, $childRemovals [0]);

    }

    public function testObjectNotFoundExceptionIsRaisedIfAttemptToGetNoneExistentObjectByPrimaryKey() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());

        // Grab a none-existent value by primary key.
        try {
            $value = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 99);
            $this->fail("Should have thrown here");
        } catch (ObjectNotFoundException $e) {
            // Success
        }
        $this->assertTrue(true);

    }

    public function testCanGetSimpleObjectByPrimaryKeyUsingInstalledRetrievalEngine() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());

        // Programme in the value we want
        TestPersistenceEngine::$returnMap [45] = array("name" => "Piper", "age" => 56, "shoeSize" => 7, "id" => 45);

        // Grab a value by primary key.
        $value = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 45);

        // Check that the object data was correctly mapped to a real object
        $this->assertTrue($value instanceof ObjectWithId);
        $this->assertEquals(new ObjectWithId ("Piper", 56, 7, 45), $value);

    }

    public function testObjectNotFoundExceptionIsRaisedIfAttemptToGetNoneExistentMultipleObjectsByPrimaryKeyIfIgnoreFlagNotPassed() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());

        // Grab some none-existent values by primary key.
        try {
            $value = $coordinator->getMultipleObjectsByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array(9900, 9901, 8706));
            $this->fail("Should have thrown here");
        } catch (ObjectNotFoundException $e) {
            // Success
        }

        // Now allow a couple of valid ones and check that exception still
        // raised if at least one does not exist.
        TestPersistenceEngine::$returnMap [9900] = array("name" => "Piper", "age" => 56, "shoeSize" => 7, "id" => 9900);
        TestPersistenceEngine::$returnMap [8706] =
            array("name" => "Biggles", "age" => 56, "shoeSize" => 7, "id" => 8706);

        // Check exception still raised.
        try {
            $value = $coordinator->getMultipleObjectsByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array(9900, 9901, 8706));
            $this->fail("Should have thrown here");
        } catch (ObjectNotFoundException $e) {
            // Success
        }


        $this->assertTrue(true);
    }


    public function testGetMultipleObjectsByPrimaryKeyReturnsCorrectlyIfIgnoreFlagNotPassedAndMultipleKeysForTheSameValidObjectsSupplied() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());


        // Now allow a couple of valid ones and check that exception still
        // raised if at least one does not exist.
        TestPersistenceEngine::$returnMap [9900] = array("name" => "Piper", "age" => 56, "shoeSize" => 7, "id" => 9900);
        TestPersistenceEngine::$returnMap [8706] =
            array("name" => "Biggles", "age" => 56, "shoeSize" => 7, "id" => 8706);


        // Grab multiple existent values by primary key.
        $values = $coordinator->getMultipleObjectsByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array(9900, 8706, 8706, 9900));
        $this->assertEquals(2, sizeof($values));


    }


    public function testGetMultipleObjectsByPrimaryKeyReturnsBlankArrayIfNoKeysPassed() {
        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());


        // Now allow a couple of valid ones and check that exception still
        // raised if at least one does not exist.
        TestPersistenceEngine::$returnMap [9900] = array("name" => "Piper", "age" => 56, "shoeSize" => 7, "id" => 9900);
        TestPersistenceEngine::$returnMap [8706] =
            array("name" => "Biggles", "age" => 56, "shoeSize" => 7, "id" => 8706);

        $values = $coordinator->getMultipleObjectsByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array());
        $this->assertTrue(is_array($values));
        $this->assertEquals(0, sizeof($values));
    }


    public function testCanGetMultipleSimpleObjectsByPrimaryKeyUsingInstalledRetrievalEngine() {
        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());

        // Programme in the values we want back
        TestPersistenceEngine::$returnMap [80] = array("name" => "Piper", "age" => 56, "shoeSize" => 7, "id" => 80);
        TestPersistenceEngine::$returnMap [81] = array("name" => "Peter", "age" => 14, "shoeSize" => 6, "id" => 81);
        TestPersistenceEngine::$returnMap [76] = array("name" => "Paul", "age" => 25, "shoeSize" => 9, "id" => 76);
        TestPersistenceEngine::$returnMap [13] = array("name" => "Pinky", "age" => 13, "shoeSize" => 4, "id" => 13);

        $values = $coordinator->getMultipleObjectsByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array(81, 76, 13, 80));

        $this->assertEquals(4, sizeof($values));
        $this->assertEquals(new ObjectWithId ("Piper", 56, 7, 80), $values ["Kinikit\Persistence\UPF\Framework\ObjectWithId:80"]);
        $this->assertEquals(new ObjectWithId ("Peter", 14, 6, 81), $values ["Kinikit\Persistence\UPF\Framework\ObjectWithId:81"]);
        $this->assertEquals(new ObjectWithId ("Paul", 25, 9, 76), $values ["Kinikit\Persistence\UPF\Framework\ObjectWithId:76"]);
        $this->assertEquals(new ObjectWithId ("Pinky", 13, 4, 13), $values ["Kinikit\Persistence\UPF\Framework\ObjectWithId:13"]);

        // Also check that the values were added in the order we wanted
        $orderedValues = array_values($values);
        $this->assertEquals(new ObjectWithId ("Piper", 56, 7, 80), $orderedValues [3]);
        $this->assertEquals(new ObjectWithId ("Peter", 14, 6, 81), $orderedValues [0]);
        $this->assertEquals(new ObjectWithId ("Paul", 25, 9, 76), $orderedValues [1]);
        $this->assertEquals(new ObjectWithId ("Pinky", 13, 4, 13), $orderedValues [2]);

    }

    public function testNoneExistentValuesAreIgnoredIfBooleanPassedWhenGettingMultipleSimpleObjectsByPK() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());

        // Now allow a couple of valid ones and check that exception still
        // raised if at least one does not exist.
        TestPersistenceEngine::$returnMap [9900] = array("name" => "Piper", "age" => 56, "shoeSize" => 7, "id" => 9900);
        TestPersistenceEngine::$returnMap [8706] =
            array("name" => "Biggles", "age" => 56, "shoeSize" => 7, "id" => 8706);

        // Check no exception raised but the two matches are still returned.
        $values = $coordinator->getMultipleObjectsByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array(9900, 9901, 8706, 7776), true);
        $this->assertEquals(2, sizeof($values));
        $this->assertEquals(new ObjectWithId ("Piper", 56, 7, 9900), $values ["Kinikit\Persistence\UPF\Framework\ObjectWithId:9900"]);
        $this->assertEquals(new ObjectWithId ("Biggles", 56, 7, 8706), $values ["Kinikit\Persistence\UPF\Framework\ObjectWithId:8706"]);

    }


    public function testIfARelationshipIsEncounteredWithNoRelatedObjectClassDefinedOnRetrievalAnExceptionIsThrown() {

        $relationship =
            new ObjectRelationship ("shoeSize", null, false, false, false, true, ObjectRelationship::MASTER_PARENT);
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        // Programme in the value we want
        TestPersistenceEngine::$returnMap [45] = array("name" => "Piper", "age" => 56, "shoeSize" => 17, "id" => 45);

        // Check for exception raised
        try {
            $value = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 45);
            $this->fail("Should have thrown here");
        } catch (InvalidFieldRelationshipException $e) {
            // Success
        }

        $this->assertTrue(true);
    }

    public function testIfASingleObjectRelationshipIsEncounteredForAFieldWhichHasARetrievedValueOfNullNullIsReturnedForTheField() {

        $relationship =
            new ObjectRelationship ("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId", false, false, false, true, ObjectRelationship::MASTER_PARENT);
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        // Programme in the value we want
        TestPersistenceEngine::$returnMap [45] = array("name" => "Piper", "age" => 56, "shoeSize" => null, "id" => 45);

        // Grab a value by primary key.
        $value = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 45);

        // Check that the object data was correctly mapped to a real object
        $this->assertTrue($value instanceof ObjectWithId);
        $this->assertEquals(new ObjectWithId ("Piper", 56, null, 45), $value);

    }

    public function testIfASingleObjectRelationshipIsEncounteredForAFieldWhichHasANonNullRetrievedValueItIsAttemptedToBePulledUsingDefinedRelatedFields() {

        $relationship = new ObjectRelationship ("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId", false, false, false, true);
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        // Programme in the value we want
        TestPersistenceEngine::$returnMap [45] = array("name" => "Piper", "age" => 56, "shoeSize" => 77, "id" => 45);
        TestPersistenceEngine::$dataForFieldValues ["Kinikit\Persistence\UPF\Framework\NewObjectWithId"] [56] =
            array(array("name" => "PlayDough", "postcode" => 14, "mobile" => "067767 87878", "id" => 77));

        // Grab a value by primary key.
        $value = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 45);

        // Check that the object data was correctly mapped to a real object
        $this->assertTrue($value instanceof ObjectWithId);
        $this->assertEquals(new ObjectWithId ("Piper", 56, new NewObjectWithId ("PlayDough", 14, "067767 87878", 77), 45), $value);

    }


    public function testGetOperationsUseTheFirstAvailablePersistenceEngineEnabledForAMapper() {

        // Configure different return values for testing
        TestPersistenceEngine::$returnMap [20] = array("name" => "Bobby", "age" => 12, "shoeSize" => 23, "id" => 20);
        TestAnotherPersistenceEngine::$returnMap [20] =
            array("name" => "Mary", "age" => 15, "shoeSize" => 25, "id" => 20);

        // Try default scenario
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId");
        $mapperManager = new ObjectMapperManager (array($mapper));
        $coordinator = new ObjectPersistenceCoordinator (array(new TestPersistenceEngine (),
            new TestAnotherPersistenceEngine ()), $mapperManager);

        $returnedValue = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 20);
        $this->assertEquals(new ObjectWithId ("Bobby", 12, 23, 20), $returnedValue);

        // Try one where second mapper is enabled only.
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", null, null, "John");
        $mapperManager = new ObjectMapperManager (array($mapper));
        $coordinator = new ObjectPersistenceCoordinator (array(new TestPersistenceEngine ("Mark"),
            new TestAnotherPersistenceEngine ("John")), $mapperManager);

        $returnedValue = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 20);
        $this->assertEquals(new ObjectWithId ("Mary", 15, 25, 20), $returnedValue);

    }

    public function testExceptionRaisedIfNoEngineAvailableForRetrievingAnObject() {

        // Try one where both mappers disabled.
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", null, null, null, "Mark,John");
        $mapperManager = new ObjectMapperManager (array($mapper));
        $coordinator = new ObjectPersistenceCoordinator (array(new TestPersistenceEngine ("Mark"),
            new TestAnotherPersistenceEngine ("John")), $mapperManager);

        try {
            $returnedValue = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 20);
            $this->fail("Should have thrown here.");
        } catch (NoEnabledEngineException $e) {
            // Success
        }

        $this->assertTrue(true);

    }


    public function testTransactionStartAndSuccessFunctionIsCalledOnEngineWhenItemIsCorrectlySavedOrDeleted() {

        $testEngine = new TestPersistenceEngine ();
        $coordinator = new ObjectPersistenceCoordinator ($testEngine);

        $testObject = new ObjectWithId ("Bob", 5, 7, 1);
        $coordinator->saveObject($testObject);

        $this->assertEquals(1, $testEngine->transactionStarted);
        $this->assertEquals(1, $testEngine->transactionSucceeded);
        $this->assertEquals(0, $testEngine->transactionFailed);

        $coordinator->removeObject($testObject);
        $this->assertEquals(2, $testEngine->transactionStarted);
        $this->assertEquals(2, $testEngine->transactionSucceeded);
        $this->assertEquals(0, $testEngine->transactionFailed);

    }

    public function testTransactionStartAndFailFunctionIsCalledOnEngineWhenItemFails() {

        $testEngine = new TestPersistenceEngine ("test", true);
        $coordinator = new ObjectPersistenceCoordinator ($testEngine);

        $testObject = new ObjectWithId ("Bob", 5, 7, 1);

        try {
            $coordinator->saveObject($testObject);
            $this->fail("Should have thrown");
        } catch (\Exception $e) {
            // Success
        }

        $this->assertEquals(1, $testEngine->transactionStarted);
        $this->assertEquals(0, $testEngine->transactionSucceeded);
        $this->assertEquals(1, $testEngine->transactionFailed);

        try {
            $coordinator->removeObject($testObject);
            $this->fail("Should have thrown");
        } catch (\Exception $e) {
            // Success
        }

        $this->assertEquals(2, $testEngine->transactionStarted);
        $this->assertEquals(0, $testEngine->transactionSucceeded);
        $this->assertEquals(2, $testEngine->transactionFailed);

    }

    public function testCallingQueryWithAQueryObjectAndNoSpecifiedEngineWillPassTheQueryObjectToTheFirstIdentifiedEngineForAGivenObjectMapping() {

        $testEngine = new TestPersistenceEngine ("test", true);
        $testAnotherEngine = new TestAnotherPersistenceEngine ("anothertest", true);
        $coordinator = new ObjectPersistenceCoordinator (array($testEngine, $testAnotherEngine));
        $testEngine->queryResults =
            array(array("id" => 3, "name" => "Janet Jackson", "postcode" => "JJ10 5YY", "mobile" => "07676 878787"),
                array("id" => 13, "name" => "Robbie Robson", "postcode" => "AX4 7YY", "mobile" => "05656 878787"));
        $testAnotherEngine->queryResults = array(array("id" => 13, "name" => "Bob Smith", "age" => 15, "shoeSize" => 8),
            array("id" => 19, "name" => "David Jones", "age" => 23, "shoeSize" => 11));

        $results = $coordinator->query("Kinikit\Persistence\UPF\Framework\NewObjectWithId", "Blaah Blaah Blaah");
        $this->assertEquals(array(new NewObjectWithId ("Janet Jackson", "JJ10 5YY", "07676 878787", 3),
            new NewObjectWithId ("Robbie Robson", "AX4 7YY", "05656 878787", 13)), $results);

        $this->assertEquals(array("Kinikit\Persistence\UPF\Framework\NewObjectWithId", "Blaah Blaah Blaah"), $testEngine->lastQueryObject);
        $this->assertNull($testAnotherEngine->lastQueryObject);

        // Now reverse the engine order and check that the first one was called.
        $testEngine = new TestPersistenceEngine ("test", true);
        $testAnotherEngine = new TestAnotherPersistenceEngine ("anothertest", true);
        $coordinator = new ObjectPersistenceCoordinator (array($testAnotherEngine, $testEngine));
        $testEngine->queryResults =
            array(array("id" => 3, "name" => "Janet Jackson", "postcode" => "JJ10 5YY", "mobile" => "07676 878787"),
                array("id" => 13, "name" => "Robbie Robson", "postcode" => "AX4 7YY", "mobile" => "05656 878787"));
        $testAnotherEngine->queryResults = array(array("id" => 13, "name" => "Bob Smith", "age" => 15, "shoeSize" => 8),
            array("id" => 19, "name" => "David Jones", "age" => 23, "shoeSize" => 11));

        $results = $coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", 176.89);
        $this->assertEquals(array(new ObjectWithId ("Bob Smith", 15, 8, 13),
            new ObjectWithId ("David Jones", 23, 11, 19)), $results);

        $this->assertNull($testEngine->lastQueryObject);
        $this->assertEquals(array("Kinikit\Persistence\UPF\Framework\ObjectWithId", 176.89), $testAnotherEngine->lastQueryObject);

    }


    public function testAllDefinedEnginesAreUsedWhenSavingObjectsByDefault() {

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId");
        $mapperManager = new ObjectMapperManager (array($mapper));
        $coordinator = new ObjectPersistenceCoordinator (array(new TestPersistenceEngine ("Mark"),
            new TestAnotherPersistenceEngine ("John")), $mapperManager);


        $newObject = new ObjectWithId("Monkey", "34", "12", "255");
        $coordinator->saveObject($newObject);

        $this->assertEquals(1, TestPersistenceEngine::$writes);
        $this->assertEquals(1, TestAnotherPersistenceEngine::$writes);

        $this->assertEquals(array("id" => 255, "name" => "Monkey", "age" => 34,
            "shoeSize" => 12), TestPersistenceEngine::$storedValues["Kinikit\Persistence\UPF\Framework\ObjectWithId"][255]);


        $this->assertEquals(array("id" => 255, "name" => "Monkey", "age" => 34,
            "shoeSize" => 12), TestAnotherPersistenceEngine::$storedValues["Kinikit\Persistence\UPF\Framework\ObjectWithId"][255]);


    }


    public function testSelectiveDefinedEnginesAreUsedWhenSavingObjectMapperWithEnabledEnginesSet() {
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", null, null, "John");
        $mapperManager = new ObjectMapperManager (array($mapper));
        $coordinator = new ObjectPersistenceCoordinator (array(new TestPersistenceEngine ("Mark"),
            new TestAnotherPersistenceEngine ("John")), $mapperManager);


        $newObject = new ObjectWithId("Monkey", "34", "12", "255");
        $coordinator->saveObject($newObject);

        $this->assertEquals(0, TestPersistenceEngine::$writes);
        $this->assertEquals(1, TestAnotherPersistenceEngine::$writes);

        $this->assertFalse(isset(TestPersistenceEngine::$storedValues["Kinikit\Persistence\UPF\Framework\ObjectWithId"][255]));


        $this->assertEquals(array("id" => 255, "name" => "Monkey", "age" => 34,
            "shoeSize" => 12), TestAnotherPersistenceEngine::$storedValues["Kinikit\Persistence\UPF\Framework\ObjectWithId"][255]);
    }


    public function testIfPersistenceEngineFailsSaveByDefaultThisWillThrowExceptionsAndStop() {
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", null, null);
        $mapperManager = new ObjectMapperManager (array($mapper));

        $testPersistenceEngine = new TestPersistenceEngine ("Mark");
        $testBrokenPersistenceEngine = new TestPersistenceEngine ("Bad", true);

        $coordinator = new ObjectPersistenceCoordinator (array($testPersistenceEngine,
            $testBrokenPersistenceEngine), $mapperManager);


        $newObject = new ObjectWithId("Monkey", "34", "12", "255");

        try {
            $coordinator->saveObject($newObject);
            $this->fail("Should have thrown exception here");
        } catch (\Exception $e) {
            // Success
        }


        $this->assertEquals(1, $testPersistenceEngine->transactionStarted);
        $this->assertEquals(1, $testPersistenceEngine->transactionFailed);
        $this->assertEquals(0, $testPersistenceEngine->transactionSucceeded);


        $this->assertEquals(1, $testBrokenPersistenceEngine->transactionStarted);
        $this->assertEquals(1, $testBrokenPersistenceEngine->transactionFailed);
        $this->assertEquals(0, $testBrokenPersistenceEngine->transactionSucceeded);


    }


    public function testIfPersistenceEngineMarkedWithIgnoreFailuresFailingSavesWillBeIgnored() {
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", null, null);
        $mapperManager = new ObjectMapperManager (array($mapper));

        $testPersistenceEngine = new TestPersistenceEngine ("Mark");
        $testBrokenPersistenceEngine = new TestPersistenceEngine ("Bad", true, true);

        $coordinator = new ObjectPersistenceCoordinator (array($testPersistenceEngine,
            $testBrokenPersistenceEngine), $mapperManager);


        $newObject = new ObjectWithId("Monkey", "34", "12", "255");

        $coordinator->saveObject($newObject);


        $this->assertEquals(1, $testPersistenceEngine->transactionStarted);
        $this->assertEquals(0, $testPersistenceEngine->transactionFailed);
        $this->assertEquals(1, $testPersistenceEngine->transactionSucceeded);


        $this->assertEquals(1, $testBrokenPersistenceEngine->transactionStarted);
        $this->assertEquals(0, $testBrokenPersistenceEngine->transactionFailed);
        $this->assertEquals(1, $testBrokenPersistenceEngine->transactionSucceeded);
    }


    public function testIfPersistenceEngineMarkedWithIgnoreFailuresFailingDeletesWillBeIgnored() {
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", null, null);
        $mapperManager = new ObjectMapperManager (array($mapper));

        $testPersistenceEngine = new TestPersistenceEngine ("Mark");
        $testBrokenPersistenceEngine = new TestPersistenceEngine ("Bad", true, true);

        $coordinator = new ObjectPersistenceCoordinator (array($testPersistenceEngine,
            $testBrokenPersistenceEngine), $mapperManager);


        $newObject = new ObjectWithId("Monkey", "34", "12", "255");

        $coordinator->removeObject($newObject);


        $this->assertEquals(1, $testPersistenceEngine->transactionStarted);
        $this->assertEquals(0, $testPersistenceEngine->transactionFailed);
        $this->assertEquals(1, $testPersistenceEngine->transactionSucceeded);


        $this->assertEquals(1, $testBrokenPersistenceEngine->transactionStarted);
        $this->assertEquals(0, $testBrokenPersistenceEngine->transactionFailed);
        $this->assertEquals(1, $testBrokenPersistenceEngine->transactionSucceeded);
    }


    public function testIfAnOptimisticLockingProviderIsInjectedIntoTheCoordinatorTheGetLockingDataMethodIsCalledForEveryRetrievedObjectAndInjected() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());
        $lockingProvider = new TestLockingProvider ();
        $coordinator->setOptimisticLockingProvider($lockingProvider);

        // Programme in the values we want
        TestPersistenceEngine::$returnMap [1] = array("name" => "Bodger", "address" => "The Horizon Centre", "id" => 1);
        $lockingProvider->lockingData ["Kinikit\Persistence\UPF\Framework\LockableObject1"] = "Some Test Locking Data";

        // Now pull the object
        $lockableObject = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\LockableObject", 1);
        $this->assertEquals("Bodger", $lockableObject->getName());
        $this->assertEquals("The Horizon Centre", $lockableObject->getAddress());
        $this->assertEquals("Some Test Locking Data", $lockableObject->getLockingData());

    }

    public function testIfAnOptimisticLockingProviderIsInjectedIntoCoordinatorLockingIsCheckedAsPartOfObjectSave() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());
        $lockingProvider = new TestLockingProvider ();
        $coordinator->setOptimisticLockingProvider($lockingProvider);

        $lockableObject = new LockableObject ("Bob Jones", "3 The Lane", 1);
        $lockableObject->setLockingData("LockMe");

        // Set the test lock behaviour
        $lockingProvider->lockedStatus ["Kinikit\Persistence\UPF\Framework\LockableObject1LockMe"] = true;

        try {
            $coordinator->saveObject($lockableObject);
            $this->fail("Should have thrown an exception here");
        } catch (OptimisticLockingException $e) {
            // Success
        }
        $this->assertTrue(true);

    }

    public function testIfAnOptimisticLockingProviderIsInjectedIntoCoordinatorLockingIsCheckedAsPartOfObjectRemove() {
        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());
        $lockingProvider = new TestLockingProvider ();
        $coordinator->setOptimisticLockingProvider($lockingProvider);

        $lockableObject = new LockableObject ("Bob Jones", "3 The Lane", 1);
        $lockableObject->setLockingData("LockMe");

        // Set the test lock behaviour
        $lockingProvider->lockedStatus ["Kinikit\Persistence\UPF\Framework\LockableObject1LockMe"] = true;

        try {
            $coordinator->removeObject($lockableObject);
            $this->fail("Should have thrown an exception here");
        } catch (OptimisticLockingException $e) {
            // Success
        }

        $this->assertTrue(true);
    }

    public function testIfAMapperDefinesACustomLockingDataFieldItIsUsedInPreferenceToTheDefaultLockingDataField() {
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\DynamicLockableObject");
        $mapper->setLockingDataField("customLockingFieldData");

        $mapperManager = new ObjectMapperManager (array($mapper));
        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine (), $mapperManager);
        $lockingProvider = new TestLockingProvider ();
        $coordinator->setOptimisticLockingProvider($lockingProvider);

        // Programme in the values we want
        TestPersistenceEngine::$returnMap [1] = array("name" => "Bodger", "address" => "The Horizon Centre", "id" => 1);
        $lockingProvider->lockingData ["Kinikit\Persistence\UPF\Framework\DynamicLockableObject1"] = "Some Test Locking Data";
        $lockingProvider->lockedStatus ["Kinikit\Persistence\UPF\Framework\DynamicLockableObject1Some Test Locking Data"] = true;

        // Now pull the object
        $lockableObject = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DynamicLockableObject", 1);

        $this->assertEquals("Bodger", $lockableObject->getName());
        $this->assertEquals("The Horizon Centre", $lockableObject->getAddress());
        $this->assertEquals("Some Test Locking Data", $lockableObject->getCustomLockingFieldData());
        $this->assertNull($lockableObject->getLockingData());

        try {
            $coordinator->saveObject($lockableObject);
            $this->fail("Should have thrown an exception here");
        } catch (OptimisticLockingException $e) {
            // Success
        }

    }

    public function testIfAMapperDefinesLockableAsFalseNoLockingIsEnforcedEvenIfALockingProviderIsPresent() {

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\DynamicLockableObject");
        $mapper->setLockingDataField("customLockingFieldData");
        $mapper->setLocking(false);

        $mapperManager = new ObjectMapperManager (array($mapper));
        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine (), $mapperManager);
        $lockingProvider = new TestLockingProvider ();
        $coordinator->setOptimisticLockingProvider($lockingProvider);

        // Programme in the values we want
        TestPersistenceEngine::$returnMap [1] = array("name" => "Bodger", "address" => "The Horizon Centre", "id" => 1);
        $lockingProvider->lockingData ["Kinikit\Persistence\UPF\Framework\DynamicLockableObject1"] = "Some Test Locking Data";
        $lockingProvider->lockedStatus ["Kinikit\Persistence\UPF\Framework\DynamicLockableObject1Some Test Locking Data"] = true;

        // Now pull the object
        $lockableObject = $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DynamicLockableObject", 1);

        $this->assertEquals("Bodger", $lockableObject->getName());
        $this->assertEquals("The Horizon Centre", $lockableObject->getAddress());
        $this->assertNull($lockableObject->getCustomLockingFieldData());
        $this->assertNull($lockableObject->getLockingData());

        // Check that save is fine too.
        $coordinator->saveObject($lockableObject);

    }

    public function testUpdateLockingDataIsCalledForAnObjectOnASuccessfulSaveOrDeleteIfLockingProviderAttached() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());
        $lockingProvider = new TestLockingProvider ();
        $coordinator->setOptimisticLockingProvider($lockingProvider);

        $lockableObject = new LockableObject ("Bob Jones", "3 The Lane", 1);
        $lockableObject->setLockingData("LockMe");
        $coordinator->saveObject($lockableObject);

        $this->assertEquals(1, $lockingProvider->updatesRecorded ["Kinikit\Persistence\UPF\Framework\LockableObject1"]);
        $this->assertEquals("UpdatedLockingData", $lockableObject->getLockingData());

        $coordinator->saveObject($lockableObject);

        $this->assertEquals(2, $lockingProvider->updatesRecorded ["Kinikit\Persistence\UPF\Framework\LockableObject1"]);

        $coordinator->removeObject($lockableObject);

        $this->assertEquals(3, $lockingProvider->updatesRecorded ["Kinikit\Persistence\UPF\Framework\LockableObject1"]);

    }

    public function testTransactionHooksAreCalledCorrectlyOnTheLockingProvider() {

        $coordinator = new ObjectPersistenceCoordinator (new TestPersistenceEngine ());
        $lockingProvider = new TestLockingProvider ();
        $coordinator->setOptimisticLockingProvider($lockingProvider);

        $lockableObject = new LockableObject ("Bob Jones", "3 The Lane", 1);
        $lockableObject->setLockingData("LockMe");
        $coordinator->saveObject($lockableObject);

        $this->assertEquals(1, $lockingProvider->transactionsStarted);
        $this->assertEquals(1, $lockingProvider->transactionsSucceeded);
        $this->assertEquals(0, $lockingProvider->transactionsFailed);

        $lockingProvider->lockedStatus ["Kinikit\Persistence\UPF\Framework\LockableObject1LockMe"] = true;
        $lockableObject->setLockingData("LockMe");

        try {
            $coordinator->saveObject($lockableObject);
            $this->fail("Should have thrown an exception here");
        } catch (OptimisticLockingException $e) {
            // Success
        }

        $this->assertEquals(2, $lockingProvider->transactionsStarted);
        $this->assertEquals(1, $lockingProvider->transactionsSucceeded);
        $this->assertEquals(1, $lockingProvider->transactionsFailed);

        try {
            $coordinator->removeObject($lockableObject);
            $this->fail("Should have thrown an exception here");
        } catch (OptimisticLockingException $e) {
            // Success
        }

        $this->assertEquals(3, $lockingProvider->transactionsStarted);
        $this->assertEquals(1, $lockingProvider->transactionsSucceeded);
        $this->assertEquals(2, $lockingProvider->transactionsFailed);

        $lockingProvider->lockedStatus ["Kinikit\Persistence\UPF\Framework\LockableObject1LockMe"] = false;

        $coordinator->removeObject($lockableObject);

        $this->assertEquals(4, $lockingProvider->transactionsStarted);
        $this->assertEquals(2, $lockingProvider->transactionsSucceeded);
        $this->assertEquals(2, $lockingProvider->transactionsFailed);

    }

    public function testCanConstructCoordinatorFromXMLConfigurationFileUsingStaticCall() {

        // Attempt to construct our simple persistence xml
        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence.xml");

        $installedEngines = $persistenceCoordinator->getEngines();
        $this->assertEquals(2, sizeof($installedEngines));

        $this->assertEquals(new ObjectIndexPersistenceEngine (new MySQLDatabaseConnection ("myhost", "bongo", "bigone", "littleone", 3306), "sql"), $installedEngines [0]);
        $this->assertEquals(new ObjectIndexPersistenceEngine (new MySQLDatabaseConnection ("yourhost", "ping", "ooa", "ooa", 3306), "sql-slave"), $installedEngines [1]);

        $mapperManager = $persistenceCoordinator->getMapperManager();

        $addressMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Address");

        $this->assertEquals(new ObjectPersistableField ("streetAddress", true), $addressMapper->getField("streetAddress"));
        $this->assertEquals(new ObjectPersistableField ("city", false, true), $addressMapper->getField("city"));

        $contactMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact");
        $this->assertEquals(new ObjectPersistableField ("name", true), $contactMapper->getField("name"));
        $this->assertEquals(new ObjectPersistableField ("telephone", true, true), $contactMapper->getField("telephone"));
    }

    public function testCanConstructCoordinatorFromXMLConfigurationFileWhereArrayItemsOnlyHaveSingleValue() {

        // Attempt to construct our simple persistence xml
        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/singleitempersistence.xml");

        $installedEngines = $persistenceCoordinator->getEngines();
        $this->assertEquals(1, sizeof($installedEngines));

        $this->assertEquals(new ObjectIndexPersistenceEngine (new MySQLDatabaseConnection ("myhost", "bongo", "bigone", "littleone", 3306), "sql"), $installedEngines [0]);

        $mapperManager = $persistenceCoordinator->getMapperManager();

        $contactMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact");
        $this->assertEquals(new ObjectPersistableField ("name", true), $contactMapper->getField("name"));
        $this->assertEquals(new ObjectPersistableField ("telephone", true, true), $contactMapper->getField("telephone"));

    }

    public function testCanConstructCoordinatorFromXMLConfigurationFileAndRetrieveAUPFObjectInterceptorEvaluator() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();
        $contactMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact");
        $interceptorEvaluator = $contactMapper->getInterceptorEvaluator();
        $this->assertNotNull($interceptorEvaluator);
        $this->assertTrue($interceptorEvaluator instanceof UPFObjectInterceptorEvaluator);
        $testObjectInterceptor4 = new TestObjectInterceptor2 ();
        $testObjectInterceptor4->setObjectType("Kinikit\Persistence\UPF\Framework\Contact");
        $interceptors = $interceptorEvaluator->getInterceptors();
        $this->assertEquals($testObjectInterceptor4, $interceptors [0]);
    }

    public function testCanConstructCoordinatorFromXMLConfigurationFileAndRetrieveAUPFObjectInterceptorEvaluatorForManyInterceptors() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();
        $contactMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact");
        $interceptorEvaluator = $contactMapper->getInterceptorEvaluator();
        $this->assertNotNull($interceptorEvaluator);
        $this->assertTrue($interceptorEvaluator instanceof UPFObjectInterceptorEvaluator);
        $testObjectInterceptor2 = new TestObjectInterceptor2 ();
        $testObjectInterceptor2->setObjectType("Kinikit\Persistence\UPF\Framework\Contact");
        $this->assertEquals(array($testObjectInterceptor2), $interceptorEvaluator->getInterceptors());
    }

    public function testWillSaveAnObjectIfNoInterceptorsArePlacedOnIt() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence2.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();

        $addressMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Address");
        $addressObject = new Address ();

        $persistenceCoordinator->setEngines(new TestPersistenceEngine ());
        $persistenceCoordinator->saveObject($addressObject);

        $this->assertTrue(true);

    }

    public function testWillNotSaveAnObjectIfInterceptorsArePlacedOnItThatReturnFalseAndAnExceptionIsThrown() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence2.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();

        $addressMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact");
        $contactObject = new Contact ();

        $persistenceCoordinator->setEngines(new TestPersistenceEngine ());

        try {
            $persistenceCoordinator->saveObject($contactObject);
            $this->fail("Should have thrown here");
        } catch (UPFObjectSaveVetoedException $e) {
            //success
        }

        $this->assertTrue(true);

    }

    public function testWillDeleteObjectIfNoInterceptorsArePlacedOnIt() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence2.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();

        $addressMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Address");
        $addressObject = new Address ();

        $persistenceCoordinator->setEngines(new TestPersistenceEngine ());

        $persistenceCoordinator->saveObject($addressObject);
        $persistenceCoordinator->removeObject($addressObject);

        $this->assertTrue(true);

    }

    public function testWillNotDeleteAnObjectIfInterceptorsArePlacedOnItThatReturnFalseAndAnExceptionIsThrown() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence2.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();


        $contactMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact");
        $contactObject = new Contact ();
        $contactObject->setName("bob");

        $persistenceCoordinator->setEngines(new TestPersistenceEngine ());
        $persistenceCoordinator->saveObject($contactObject);

        try {
            $persistenceCoordinator->removeObject($contactObject);
            $this->fail("Should have thrown here");
        } catch (UPFObjectDeleteVetoedException $e) {
            //success
        }

        $this->assertTrue(true);

    }

    public function testWillMapAnObjectIfNoInterceptorsArePlacedOnIt() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence2.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();

        $addressMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Address");

        $persistenceCoordinator->setEngines(new TestPersistenceEngine ());
        $addressObject =
            $persistenceCoordinator->mapObjectDataToObject($addressMapper, array("streetAddress" => "street",
                "city" => "City"));

        $this->assertNotNull($addressObject);
        $this->assertEquals($addressObject->getStreetAddress(), "street");
        $this->assertEquals($addressObject->getCity(), "City");

    }

    public function testWillNotMapAnObjectIfInterceptorsArePlacedOnItThatReturnFalse() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence2.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();

        $contactMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact");

        $persistenceCoordinator->setEngines(new TestPersistenceEngine ());
        $contactObject = $persistenceCoordinator->mapObjectDataToObject($contactMapper, array("name" => "Matthew",
            "telephone" => "07987654321", "address" => "MyAddress", "friends" => "billyNoMates"));

        $this->assertNull($contactObject);

    }

    public function testWillMapAnObjectToAGivenClassIfInterceptorsArePlacedOnItThatReturnsSaidClass() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence2.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();

        $contactMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact");

        $persistenceCoordinator->setEngines(new TestPersistenceEngine ());
        $contactObject = $persistenceCoordinator->mapObjectDataToObject($contactMapper, array("name" => "Mark",
            "telephone" => "07987654321", "address" => "MyAddress", "friends" => "billyNoMates"));

        $this->assertNotNull($contactObject);
        $this->assertTrue($contactObject instanceof ContactVariant);
        $this->assertEquals($contactObject->getName(), "Mark");
        $this->assertEquals($contactObject->getTelephone(), "07987654321");

    }

    public function testWillReturnAnObjectAfterMappingfNoInterceptorsArePlacedOnIt() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence2.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();

        $addressMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Address");

        $persistenceCoordinator->setEngines(new TestPersistenceEngine ());
        $addressObject =
            $persistenceCoordinator->mapObjectDataToObject($addressMapper, array("streetAddress" => "street",
                "city" => "City"));

        $this->assertNotNull($addressObject);
        $this->assertEquals($addressObject->getStreetAddress(), "street");
        $this->assertEquals($addressObject->getCity(), "City");
    }

    public function testWillNotReturnAnObjectAfterMappingfInterceptorsArePlacedOnItThatReturnFalse() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence2.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();

        $contactMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\ContactVariant");

        $persistenceCoordinator->setEngines(new TestPersistenceEngine ());
        $contactObject = $persistenceCoordinator->mapObjectDataToObject($contactMapper, array("name" => "bob",
            "telephone" => "07987654321", "address" => "MyAddress", "friends" => "billyNoMates"));

        $this->assertNull($contactObject);

    }

    public function testWillNotReturnAnObjectAfterMappingfInterceptorsArePlacedOnItThatReturnFalseIfClassChangesInPreMappingInterceptorsAreEvaluated() {

        $persistenceCoordinator =
            ObjectPersistenceCoordinator::createFromConfigFile("UPF/Framework/simplepersistence2.xml");
        $mapperManager = $persistenceCoordinator->getMapperManager();

        $contactMapper = $mapperManager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact");

        $persistenceCoordinator->setEngines(new TestPersistenceEngine ());
        $contactObject = $persistenceCoordinator->mapObjectDataToObject($contactMapper, array("name" => "bob",
            "telephone" => "07987654321", "address" => "MyAddress", "friends" => "billyNoMates"));

        $this->assertNull($contactObject);

    }


    public function testAttachedFieldFormatterInstancesAreUsedWhenRetrievingFieldsWithFormatterAttached() {

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", new ObjectPersistableField ("age", false, false, false, "dateformat"),
                new ObjectPersistableField ("shoeSize", false, false, false, "datetimeformat")))));

        // Define 2 formatters
        $dateFormatter = new DateFieldFormatter ("dateformat");
        $dateTimeFormatter = new DateFieldFormatter ("datetimeformat", "d/m/Y H:i", "Y-m-d H:i");

        $coordinator->setFieldFormatters(array($dateFormatter, $dateTimeFormatter));

        // Set up some sample return data
        TestPersistenceEngine::$returnMap [5] =
            array("id" => 5, "name" => "Bob", "age" => "2010-05-06", "shoeSize" => "2011-01-01 10:35");
        TestPersistenceEngine::$returnMap [9] =
            array("id" => 9, "name" => "Mary", "age" => "2009-12-10", "shoeSize" => "2009-11-11 08:00");


        // Check that the formatter was invoked
        $this->assertEquals(new ObjectWithId ("Bob", "06/05/2010", "01/01/2011 10:35", 5), $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 5));
        $this->assertEquals(new ObjectWithId ("Mary", "10/12/2009", "11/11/2009 08:00", 9), $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 9));

    }

    public function testAttachedFieldFormatterInstancesAreUsedWhenSavingFieldsWithFormatterAttached() {

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", new ObjectPersistableField ("age", false, false, false, "dateformat"),
                new ObjectPersistableField ("shoeSize", false, false, false, "datetimeformat")))));

        // Define 2 formatters
        $dateFormatter = new DateFieldFormatter ("dateformat");
        $dateTimeFormatter = new DateFieldFormatter ("datetimeformat", "d/m/Y H:i", "Y-m-d H:i");

        $coordinator->setFieldFormatters(array($dateFormatter, $dateTimeFormatter));

        $object1 = new ObjectWithId ("Bob", "11/07/2009", "12/08/2010 01:13", 10);
        $object2 = new ObjectWithId ("Mary", "19/01/2001", "02/03/2015 09:00", 12);

        $coordinator->saveObject($object1);
        $coordinator->saveObject($object2);

        $this->assertEquals("2009-07-11", TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"] [10] ["age"]);
        $this->assertEquals("2010-08-12 01:13", TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"] [10] ["shoeSize"]);

        $this->assertEquals("2001-01-19", TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"] [12] ["age"]);
        $this->assertEquals("2015-03-02 09:00", TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"] [12] ["shoeSize"]);

        // Check that the original object is still correct.
        $this->assertEquals(new ObjectWithId ("Bob", "11/07/2009", "12/08/2010 01:13", 10), $object1);
        $this->assertEquals(new ObjectWithId ("Mary", "19/01/2001", "02/03/2015 09:00", 12), $object2);

    }

    public function testFormattedPrimaryKeysAreCorrectlyConvertedOnSaveAndRetrieve() {

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", new ObjectPersistableField ("age", false, true, false, "dateformat"),
                new ObjectPersistableField ("shoeSize", false, true, false, "datetimeformat")))));

        // Define 2 formatters
        $dateFormatter = new DateFieldFormatter ("dateformat");
        $dateTimeFormatter = new DateFieldFormatter ("datetimeformat", "d/m/Y H:i", "Y-m-d H:i");

        $coordinator->setFieldFormatters(array($dateFormatter, $dateTimeFormatter));

        $object1 = new ObjectWithId ("Bob", "11/07/2009", "12/08/2010 01:13", 17);
        $coordinator->saveObject($object1);

        $this->assertEquals("2009-07-11", TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"] ["2009-07-11||2010-08-12 01:13"] ["age"]);
        $this->assertEquals("2010-08-12 01:13", TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"] ["2009-07-11||2010-08-12 01:13"] ["shoeSize"]);

        // Set up some sample return data
        TestPersistenceEngine::$returnMap ["2009-07-11||2010-08-12 01:13"] =
            array("id" => 17, "name" => "Bob", "age" => "2009-07-11", "shoeSize" => "2010-08-12 01:13");

        // Check we get it back translated.
        $this->assertEquals(new ObjectWithId ("Bob", "11/07/2009", "12/08/2010 01:13", 17), $coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("11/07/2009",
            "12/08/2010 01:13")));

        // Check that the original object was left alone as well.
        $this->assertEquals(new ObjectWithId ("Bob", "11/07/2009", "12/08/2010 01:13", 17), $object1);

    }

    public function testFormattedPrimaryKeysAreCorrectlyConvertedOnRemoveOperations() {

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", new ObjectPersistableField ("age", false, true, false, "dateformat"),
                new ObjectPersistableField ("shoeSize", false, true, false, "datetimeformat")))));

        // Define 2 formatters
        $dateFormatter = new DateFieldFormatter ("dateformat");
        $dateTimeFormatter = new DateFieldFormatter ("datetimeformat", "d/m/Y H:i", "Y-m-d H:i");

        $coordinator->setFieldFormatters(array($dateFormatter, $dateTimeFormatter));

        $object1 = new ObjectWithId ("Bob", "11/07/2009", "12/08/2010 01:13", 17);
        $coordinator->saveObject($object1);

        $coordinator->removeObject($object1);

        $lastRemovedId = array_pop(TestPersistenceEngine::$removedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"]);
        $this->assertEquals("2009-07-11||2010-08-12 01:13", $lastRemovedId);

    }


    public function testCanSynchroniseRelationshipsForAnExistingObject() {

        $relationship = new ObjectRelationship ("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId", false, false, false, true);
        $relationship->setRelatedFields(array(new ObjectRelatedField ("age", "mobile")));

        $coordinator =
            new ObjectPersistenceCoordinator (new TestPersistenceEngine (), new ObjectMapperManager (new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id",
                "name", "age"), array($relationship))));

        // Programme in the value we want
        TestPersistenceEngine::$returnMap [45] = array("name" => "Piper", "age" => 56, "shoeSize" => 77, "id" => 45);
        TestPersistenceEngine::$dataForFieldValues ["NewObjectWithId"] [56] =
            array(array("name" => "PlayDough", "postcode" => 14, "mobile" => "067767 87878", "id" => 77));


        // Create a new object
        $newObject = new ObjectWithId("Piper", 56);

        // Synchronise any relationship data for the new object
        $coordinator->synchroniseRelationships($newObject);

        $this->assertEquals(new ObjectWithId ("Piper", 56, new NewObjectWithId ("PlayDough", 14, "067767 87878", 77)), $newObject);
    }


    public function testValidationExceptionRaisedWhenAttemptingToSaveObjectWithValidationErrors() {


        $coordinator = new ObjectPersistenceCoordinator(new TestPersistenceEngine(), new ObjectMapperManager(new ObjectMapper("Kinikit\Persistence\UPF\Framework\ObjectWithValidation", array("id", "name", "age"))));

        $newTestObject = new ObjectWithValidation();
        $newTestObject->setId(10);

        try {
            $coordinator->saveObject($newTestObject);
            $this->fail("Should have thrown an exception here");
        } catch (ValidationException $e) {
            $this->assertEquals(2, sizeof($e->getValidationErrors()));
            $this->assertTrue(isset($e->getValidationErrors()["age"]["required"]));
            $this->assertTrue(isset($e->getValidationErrors()["name"]["required"]));
        }

        $newTestObject->setName("Bingo");
        $newTestObject->setAge(77);

        try {
            $coordinator->saveObject($newTestObject);
            $this->fail("Should have thrown an exception here");
        } catch (ValidationException $e) {
            $this->assertEquals(1, sizeof($e->getValidationErrors()));
            $this->assertTrue(isset($e->getValidationErrors()["age"]["range"]));
        }

        $newTestObject->setName("Bingo");
        $newTestObject->setAge(65);

        $coordinator->saveObject($newTestObject);
        $this->assertEquals("10", TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithValidation"] [10] ["id"]);
        $this->assertEquals(65, TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithValidation"] [10] ["age"]);
        $this->assertEquals("Bingo", TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithValidation"] [10] ["name"]);

    }


    public function testValidationExceptionNotRaisedWhenAttemptingToSaveObjectWithValidationErrorsAndNoValidateOnSaveAttributeSet() {

        $coordinator = new ObjectPersistenceCoordinator(new TestPersistenceEngine());

        $newTestObject = new ObjectWithSuppressedValidation();
        $newTestObject->setId(10);

        $coordinator->saveObject($newTestObject);

        $this->assertEquals("10", TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithSuppressedValidation"] [10] ["id"]);
        $this->assertEquals(null, TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithSuppressedValidation"] [10] ["age"]);
        $this->assertEquals(null, TestPersistenceEngine::$storedValues ["Kinikit\Persistence\UPF\Framework\ObjectWithSuppressedValidation"] [10] ["name"]);


    }


}

?>
