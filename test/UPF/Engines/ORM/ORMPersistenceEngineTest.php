<?php

namespace Kinikit\Persistence\UPF\Engines\ORM;

use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMAmbiguousMapperSourceDefinitionException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMColumnDoesNotExistException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMFullQueryRequiredException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMObjectNotWritableException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMTableDoesNotExistException;
use Kinikit\Persistence\UPF\Engines\ORM\Query\SQLQuery;
use Kinikit\Persistence\UPF\Exception\UnsupportedEngineQueryException;
use Kinikit\Persistence\UPF\Framework\AggregateObject;
use Kinikit\Persistence\UPF\Framework\ChildObject;
use Kinikit\Persistence\UPF\Framework\DeepObject;
use Kinikit\Persistence\UPF\Framework\NewObjectWithId;
use Kinikit\Persistence\UPF\Framework\NewObjectWithIdExtended;
use Kinikit\Persistence\UPF\Framework\ObjectMapper;
use Kinikit\Persistence\UPF\Framework\ObjectOrderingField;
use Kinikit\Persistence\UPF\Framework\ObjectPersistableField;
use Kinikit\Persistence\UPF\Framework\ObjectPersistenceCoordinator;
use Kinikit\Persistence\UPF\Framework\ObjectRelatedField;
use Kinikit\Persistence\UPF\Framework\ObjectRelationship;
use Kinikit\Persistence\UPF\Framework\ObjectWithId;
use Kinikit\Persistence\UPF\Framework\ObjectWithMultiPK;
use Kinikit\Persistence\UPF\Framework\ObjectWithReadOnlyFields;

include_once "autoloader.php";


/**
 * Test cases for the ORM Persistence Engine.
 *
 * @author mark
 *
 */
class ORMPersistenceEngineTest extends \PHPUnit\Framework\TestCase {

    private $connection;


    /**
     * @var ObjectPersistenceCoordinator
     */
    private $coordinator;

    public function setUp() {
        $this->connection = DefaultDB::instance();
        $this->connection->query("DROP TABLE IF EXISTS object_with_id");
        $this->connection->query("CREATE TABLE object_with_id (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), age INTEGER, shoe_size INTEGER)");

        $this->connection->query("DROP TABLE IF EXISTS deep_object");
        $this->connection->query("CREATE TABLE deep_object (id INTEGER PRIMARY KEY AUTOINCREMENT, sub_object_id VARCHAR(255), sub_object_id2 VARCHAR(255))");


        $this->connection->query("DROP TABLE IF EXISTS new_object_with_id");
        $this->connection->query("CREATE TABLE new_object_with_id (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), postcode VARCHAR(255), mobile VARCHAR(255))");

        $this->connection->query("DROP TABLE IF EXISTS object_with_id_new_object_with_id");
        $this->connection->query("CREATE TABLE object_with_id_new_object_with_id (object_with_id_id INTEGER, new_object_with_id_id INTEGER)");

        $this->connection->query("DROP TABLE IF EXISTS child_object");
        $this->connection->query("CREATE TABLE child_object (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INTEGER, name VARCHAR(255), postcode VARCHAR(255), telephone_number VARCHAR(255), category VARCHAR(255), order_index INTEGER)");


        $this->connection->query("DROP TABLE IF EXISTS object_with_multi_pk");
        $this->connection->query("CREATE TABLE object_with_multi_pk (element1 INTEGER, element2 VARCHAR(50), element3 VARCHAR(10), message TEXT, PRIMARY KEY (element1, element2, element3))");

        $this->connection->query("DROP TABLE IF EXISTS object_with_read_only_fields");
        $this->connection->query("CREATE TABLE object_with_read_only_fields (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255))");

        $this->connection->query("DROP VIEW IF EXISTS object_with_read_only_fields_view");
        $this->connection->query("CREATE VIEW object_with_read_only_fields_view AS SELECT o.*, 'Test Application' application_name, date('now') application_version FROM object_with_read_only_fields o");


        $this->coordinator = new ObjectPersistenceCoordinator (array(new ORMPersistenceEngine ($this->connection)));

    }

    public function testExceptionRaisedIfTableDoesNotExistForPersistingObject() {
        $this->connection->query("DROP TABLE IF EXISTS object_with_id");

        $object = new ObjectWithId ("Peter Smith", 25, 13);

        try {
            $result = $this->coordinator->saveObject($object);
            $this->fail("Should have thrown here");
        } catch (ORMTableDoesNotExistException $e) {
            // Success
        }

        $this->assertTrue(true);
    }

    public function testExceptionRaisedIfColumnDoesNotExistOnTableForPersistingObjectField() {

        // Remove the age field.
        $this->connection->query("DROP TABLE IF EXISTS object_with_id");
        $this->connection->query("CREATE TABLE object_with_id (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), shoe_size INTEGER)");

        $object = new ObjectWithId ("Peter Smith", 25, 13);

        try {
            $result = $this->coordinator->saveObject($object);
            $this->fail("Should have thrown here");
        } catch (ORMColumnDoesNotExistException $e) {
            // Success
        }

        $this->assertTrue(true);
    }


    public function testCanSaveNewSimpleObjectToBackingTableUsingConventionsForTableNameAndColumnNames() {

        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);
        $this->assertEquals(1, $object->getId());
        $this->assertEquals("1||Peter Smith||25||13", $this->connection->queryForSingleValue("SELECT id || '||' || name || '||' || age || '||' || shoe_size FROM object_with_id WHERE id = 1"));

    }

    public function testCanSaveNewSimpleObjectToAlternativeBackingTableIfTableNameSuppliedAsMapperAttribute() {

        $this->connection->query("DROP TABLE IF EXISTS differenttable");
        $this->connection->query("CREATE TABLE differenttable (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), age INTEGER, shoe_size INTEGER)");

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId");
        $mapper->setOrmTable("differenttable");
        $this->coordinator->setObjectMappers(array($mapper));
        $object = new ObjectWithId ("Jane Bond", 15, 12);

        $this->coordinator->saveObject($object);
        $this->assertEquals("1||Jane Bond||15||12", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || age || '||' || shoe_size FROM differenttable WHERE id = 1"));

    }

    public function testCanSaveNewSimpleObjectToAlternativeColumnsIfAlternativeColumnNamesSuppliedInPersistenceFields() {

        $this->connection->query("DROP TABLE IF EXISTS differenttable");
        $this->connection->query("CREATE TABLE differenttable (id INTEGER PRIMARY KEY AUTOINCREMENT, bob VARCHAR(255), joe INTEGER, jeeves INTEGER)");

        $bob = new ObjectPersistableField ("name");
        $bob->setOrmColumn("bob");
        $joe = new ObjectPersistableField ("age");
        $joe->setOrmColumn("joe");
        $jeeves = new ObjectPersistableField ("shoeSize");
        $jeeves->setOrmColumn("jeeves");
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id", $bob, $joe, $jeeves));
        $mapper->setOrmTable("differenttable");
        $this->coordinator->setObjectMappers(array($mapper));
        $object = new ObjectWithId ("Jane Bond", 15, 12);

        $this->coordinator->saveObject($object);
        $this->assertEquals("1||Jane Bond||15||12", $this->connection->queryForSingleValue("SELECT  id || '||' || bob || '||' || joe || '||' || jeeves FROM differenttable WHERE id = 1"));

    }

    public function testCanUpdateExistingSimpleObjects() {

        $object = new ObjectWithId ("Peter Smith", 25, 13);

        $this->coordinator->saveObject($object);
        $this->assertEquals("1||Peter Smith||25||13", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || age || '||' || shoe_size FROM object_with_id WHERE id = 1"));

        $object->setName("Bobby Shaftoe");
        $object->setAge(37);
        $object->setShoeSize(8);
        $this->coordinator->saveObject($object);
        $this->assertEquals("1||Bobby Shaftoe||37||8", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || age || '||' || shoe_size FROM object_with_id WHERE id = 1"));

    }


    public function testCanUpdateAndRetrieveObjectsWithMultiPrimaryKey() {

        $fields = array(new ObjectPersistableField ("element1", true, true),
            new ObjectPersistableField ("element2", true, true), new ObjectPersistableField ("element3", true, true),
            "message");
        $mapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ObjectWithMultiPK", $fields);
        $this->coordinator->setObjectMappers(array($mapper));

        $newObject = new ObjectWithMultiPK(1, 'Monkey', 'Gorilla', 'Philip Likes This');
        $this->coordinator->saveObject($newObject);

        $reObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithMultiPK", array(1, "Monkey", "Gorilla"));
        $this->assertEquals($newObject, $reObject);


        // Check for blanks in the PK as this should be allowed too.
        $newObject2 = new ObjectWithMultiPK(2, "", "", "My Null Entry");
        $this->coordinator->saveObject($newObject2);

        $reObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithMultiPK", array(2, "", ""));
        $this->assertEquals($newObject2, $reObject);


    }


    public function testSimpleOneToOneRelationshipsArePersistedCorrectlyWithSingleRelationalKey() {

        $object = new DeepObject (new NewObjectWithId ("Marie Claire", "OX4 8UU", "01787 989898"));
        $objectRelationship = new ObjectRelationship("subObject", "Kinikit\Persistence\UPF\Framework\NewObjectWithId");
        $objectRelationship->setRelatedFields(array(new ObjectRelatedField("subObjectId", "id")));
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\DeepObject", array("id", "subObjectId"), array($objectRelationship));

        $this->coordinator->setObjectMappers(array($mapper));
        $this->coordinator->saveObject($object);

        $this->assertEquals("1||1", $this->connection->queryForSingleValue("SELECT  id || '||' || sub_object_id FROM deep_object WHERE id = 1"));
        $this->assertEquals("1||Marie Claire||OX4 8UU||01787 989898", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' || mobile FROM new_object_with_id WHERE id = 1"));


        // Now pull the data and check accordingly
        $deepObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DeepObject", 1);
        $this->assertEquals($object, $deepObject);


    }


    public function testSimpleOneToOneRelationshipsArePersistedCorrectlyWithMultipleRelationalKeys() {

        $object = new DeepObject (new NewObjectWithId ("Marie Claire", "OX4 8UU", "01787 989898"));
        $objectRelationship = new ObjectRelationship("subObject", "Kinikit\Persistence\UPF\Framework\NewObjectWithId");
        $objectRelationship->setRelatedFields(array(new ObjectRelatedField("subObjectId", "name"),
            new ObjectRelatedField("subObjectId2", "postcode")));
        $mapper =
            new ObjectMapper ("Kinikit\Persistence\UPF\Framework\DeepObject", array("id", "subObjectId", "subObjectId2"), array($objectRelationship));
        $newMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\NewObjectWithId", array("id", "name", "postcode", "mobile"));


        $this->coordinator->setObjectMappers(array($mapper, $newMapper));
        $this->coordinator->saveObject($object);

        $this->assertEquals("1||Marie Claire||OX4 8UU", $this->connection->queryForSingleValue("SELECT  id || '||' || sub_object_id|| '||' || sub_object_id2 FROM deep_object WHERE id = 1"));
        $this->assertEquals("1||Marie Claire||OX4 8UU||01787 989898", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' ||mobile FROM new_object_with_id WHERE id = 1"));


        // Now pull the data and check accordingly
        $deepObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DeepObject", 1);
        $this->assertEquals($object, $deepObject);

    }


    public function testSingleObjectRelationshipUsingExtendedRelationshipClassIsPersistedCorrectly() {

        $object = new DeepObject (new NewObjectWithIdExtended ("Marie Claire", "OX4 8UU", "01787 989898"));
        $objectRelationship = new ObjectRelationship("subObject", "Kinikit\Persistence\UPF\Framework\NewObjectWithIdExtended");
        $objectRelationship->setRelatedFields(array(new ObjectRelatedField("subObjectId", "name"),
            new ObjectRelatedField("subObjectId2", "postcode")));
        $mapper =
            new ObjectMapper ("Kinikit\Persistence\UPF\Framework\DeepObject", array("id", "subObjectId", "subObjectId2"), array($objectRelationship));
        $newMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\NewObjectWithId", array("id", "name", "postcode", "mobile"));
        $newMapper->setOrmTable("new_object_with_id");
        $newMapperExtended = new ObjectMapper("Kinikit\Persistence\UPF\Framework\NewObjectWithIdExtended");
        $newMapperExtended->setExtends("Kinikit\Persistence\UPF\Framework\NewObjectWithId");


        $this->coordinator->setObjectMappers(array($mapper, $newMapper, $newMapperExtended));
        $this->coordinator->saveObject($object);


        // Now pull the data and check accordingly
        $deepObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DeepObject", 1);
        $this->assertEquals($object, $deepObject);

    }


    public function testMultipleRelationshipsArePersistedCorrectly() {

        $object = new DeepObject(array(new ChildObject("Marie Claire", "CB4 2JL", "01223 355931"),
            new ChildObject("Andrew Smith", "OX4 7YY", "01865 784294"),
            new ChildObject("Jane Bond", "OX4 7YY", "01869 767677")));

        $objectRelationship =
            new ObjectRelationship("subObject", "Kinikit\Persistence\UPF\Framework\ChildObject", true, false, false, true, ObjectRelationship::MASTER_PARENT);
        $objectRelationship->setRelatedFields(array(new ObjectRelatedField("id", "parentId")));

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\DeepObject", array("id"), array($objectRelationship));
        $childMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ChildObject", array("id", "parentId", "name", "postcode", "telephoneNumber"));

        $this->coordinator->setObjectMappers(array($mapper, $childMapper));
        $this->coordinator->saveObject($object);

        // Check master record
        $this->assertEquals(1, $this->connection->queryForSingleValue("SELECT id FROM deep_object WHERE id = 1"));

        // Check child records saved correctly
        $this->assertEquals("1||Marie Claire||CB4 2JL||01223 355931", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' || telephone_number FROM child_object WHERE id = 1"));
        $this->assertEquals("2||Andrew Smith||OX4 7YY||01865 784294", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' ||telephone_number FROM child_object WHERE id = 2"));
        $this->assertEquals("3||Jane Bond||OX4 7YY||01869 767677", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' || telephone_number FROM child_object WHERE id = 3"));


        $reObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DeepObject", 1);
        $this->assertEquals($object, $reObject);


    }


    public function testMultipleObjectRelationshipUsingExtendedRelationshipClassIsPersistedCorrectly() {

        $object = new DeepObject (array(new NewObjectWithIdExtended ("Marie Claire", "OX4 8UU", "01787 989898"), new NewObjectWithIdExtended("Bobby Ball", "OX3 7EW", "06767 898989")));
        $object->setSubObjectId(12345);

        $objectRelationship = new ObjectRelationship("subObject", "Kinikit\Persistence\UPF\Framework\NewObjectWithIdExtended", true, false, false, true, ObjectRelationship::MASTER_PARENT);
        $objectRelationship->setRelatedFields(array(new ObjectRelatedField("subObjectId", "postcode")));

        $mapper =
            new ObjectMapper ("Kinikit\Persistence\UPF\Framework\DeepObject", array("id", "subObjectId", "subObjectId2"), array($objectRelationship));
        $newMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\NewObjectWithId", array("id", "name", "postcode", "mobile"));
        $newMapper->setOrmTable("new_object_with_id");
        $newMapperExtended = new ObjectMapper("Kinikit\Persistence\UPF\Framework\NewObjectWithIdExtended");
        $newMapperExtended->setExtends("Kinikit\Persistence\UPF\Framework\NewObjectWithId");


        $this->coordinator->setObjectMappers(array($mapper, $newMapper, $newMapperExtended));
        $this->coordinator->saveObject($object);


        // Now pull the data and check accordingly
        $deepObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DeepObject", 1);
        $this->assertEquals($object, $deepObject);


    }


    public function testOrderingEnforcedForMultipleRelationshipsWithOrderingFields() {

        $object1 = new ChildObject("Marie Claire", "CB4 2JL", "01223 355931");
        $object2 = new ChildObject("Andrew Smith", "OX4 7YY", "01865 784294");
        $object3 = new ChildObject("Jane Bond", "OX4 7YY", "01869 767677");

        $object = new DeepObject(array($object1, $object2, $object3));

        $objectRelationship =
            new ObjectRelationship("subObject", "Kinikit\Persistence\UPF\Framework\ChildObject", true, false, false, true, ObjectRelationship::MASTER_PARENT);
        $objectRelationship->setRelatedFields(array(new ObjectRelatedField("id", "parentId")));

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\DeepObject", array("id"), array($objectRelationship));
        $childMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ChildObject", array("id", "parentId", "name", "postcode", "telephoneNumber"));

        $this->coordinator->setObjectMappers(array($mapper, $childMapper));
        $this->coordinator->saveObject($object);

        // Set an ordering
        $objectRelationship->setOrderingFields(array(new ObjectOrderingField("name")));

        // Check master record
        $reObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DeepObject", 1);
        $this->assertEquals(array($object2, $object3, $object1), $reObject->getSubObject());


        // Set a reverse ordering
        $objectRelationship->setOrderingFields(array(new ObjectOrderingField("name", ObjectOrderingField::DIRECTION_DESC)));

        // Check master record
        $reObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DeepObject", 1);
        $this->assertEquals(array($object1, $object3, $object2), $reObject->getSubObject());


        // Set a compound ordering
        $objectRelationship->setOrderingFields(array(new ObjectOrderingField("postcode"),
            new ObjectOrderingField("name", ObjectOrderingField::DIRECTION_DESC)));

        // Check master record
        $reObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DeepObject", 1);
        $this->assertEquals(array($object1, $object3, $object2), $reObject->getSubObject());


    }

    public function testRelatedObjectFieldUpdatedWithIndexNumberInChildArrayOnSaveIfAutoIndexSetOnOrderingField() {

        $object1 = new ChildObject("Marie Claire", "CB4 2JL", "01223 355931");
        $object2 = new ChildObject("Andrew Smith", "OX4 7YY", "01865 784294");
        $object3 = new ChildObject("Jane Bond", "OX4 7YY", "01869 767677");

        $object = new DeepObject(array($object1, $object2, $object3));

        $objectRelationship =
            new ObjectRelationship("subObject", "Kinikit\Persistence\UPF\Framework\ChildObject", true, false, false, true, ObjectRelationship::MASTER_PARENT);
        $objectRelationship->setRelatedFields(array(new ObjectRelatedField("id", "parentId")));
        $objectRelationship->setOrderingFields(array(new ObjectOrderingField("orderIndex", ObjectOrderingField::DIRECTION_ASC, true)));

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\DeepObject", array("id"), array($objectRelationship));
        $childMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ChildObject", array("id", "parentId", "name", "postcode", "telephoneNumber",
            "orderIndex"));

        // Save and check
        $this->coordinator->setObjectMappers(array($mapper, $childMapper));
        $this->coordinator->saveObject($object);


        $this->assertEquals("1||Marie Claire||CB4 2JL||01223 355931||0", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' || telephone_number || '||' || order_index FROM child_object WHERE id = 1"));
        $this->assertEquals("2||Andrew Smith||OX4 7YY||01865 784294||1", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' || telephone_number || '||' || order_index FROM child_object WHERE id = 2"));
        $this->assertEquals("3||Jane Bond||OX4 7YY||01869 767677||2", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' || telephone_number || '||' || order_index FROM child_object WHERE id = 3"));


        $object->setSubObject(array($object2, $object1, $object3));
        $this->coordinator->saveObject($object);

        $this->assertEquals("1||Marie Claire||CB4 2JL||01223 355931||1", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' || telephone_number || '||' || order_index FROM child_object WHERE id = 1"));
        $this->assertEquals("2||Andrew Smith||OX4 7YY||01865 784294||0", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' || telephone_number || '||' || order_index FROM child_object WHERE id = 2"));
        $this->assertEquals("3||Jane Bond||OX4 7YY||01869 767677||2", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || postcode || '||' || telephone_number || '||' || order_index FROM child_object WHERE id = 3"));


    }


    public function testStaticValueRelationshipsAreCorrectlyHandled() {

        $object1 = new ChildObject("Marie Claire", "CB4 2JL", "01223 355931");
        $object2 = new ChildObject("Andrew Smith", "OX4 7YY", "01865 784294");
        $object3 = new ChildObject("Jane Bond", "OX4 7YY", "01869 767677");

        $object = new DeepObject(array($object1, $object2), array($object3));

        $objectRelationship =
            new ObjectRelationship("subObject", "Kinikit\Persistence\UPF\Framework\ChildObject", true, false, false, true, ObjectRelationship::MASTER_PARENT);
        $objectRelationship->setRelatedFields(array(new ObjectRelatedField("id", "parentId"),
            new ObjectRelatedField(null, "category", "ANIMAL")));

        $objectRelationship2 =
            new ObjectRelationship("subObject2", "Kinikit\Persistence\UPF\Framework\ChildObject", true, false, false, true, ObjectRelationship::MASTER_PARENT);
        $objectRelationship2->setRelatedFields(array(new ObjectRelatedField("id", "parentId"),
            new ObjectRelatedField(null, "category", "PLANT")));


        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\DeepObject", array("id"), array($objectRelationship, $objectRelationship2));
        $childMapper =
            new ObjectMapper("Kinikit\Persistence\UPF\Framework\ChildObject", array("id", "parentId", "name", "postcode", "telephoneNumber", "orderIndex",
                "category"));

        // Save and check
        $this->coordinator->setObjectMappers(array($mapper, $childMapper));
        $this->coordinator->saveObject($object);


        // Now retrieve the object and check the related objects
        $reObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\DeepObject", $object->getId());

        $childObjects1 = $reObject->getSubObject();
        $childObjects2 = $reObject->getSubObject2();

        $this->assertEquals(2, sizeof($childObjects1));
        $this->assertEquals("Marie Claire", $childObjects1[0]->getName());
        $this->assertEquals("ANIMAL", $childObjects1[0]->getCategory());
        $this->assertEquals("Andrew Smith", $childObjects1[1]->getName());
        $this->assertEquals("ANIMAL", $childObjects1[1]->getCategory());


        $this->assertEquals(1, sizeof($childObjects2));
        $this->assertEquals("Jane Bond", $childObjects2[0]->getName());
        $this->assertEquals("PLANT", $childObjects2[0]->getCategory());


    }


    public function testCanRemoveSimpleObjects() {

        $object = new ObjectWithId ("Peter Smith", 25, 13);

        $this->coordinator->saveObject($object);
        $this->assertEquals("1||Peter Smith||25||13", $this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || age || '||' || shoe_size FROM object_with_id WHERE id = 1"));

        $this->coordinator->removeObject($object);
        $this->assertNull($this->connection->queryForSingleValue("SELECT  id || '||' || name || '||' || age || '||' || shoe_size FROM object_with_id WHERE id = 1"));

    }

    public function testCanGetSimpleObjectsBySinglePrimaryKey() {

        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);
        $this->assertEquals($object, $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 1));

    }

    /*
     public function testCanGetDeepObjectsBySinglePrimaryKey() {

         $this->connection->query ( "DROP TABLE IF EXISTS object_with_id" );
         $this->connection->query ( "CREATE TABLE object_with_id (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), age INTEGER)" );

         $objectMapper = new ObjectMapper ( "ObjectWithId", array ("id", "name", new ObjectPersistableField ( "age", false, false, false, new ObjectPersistableFieldRelationship ( "NewObjectWithId" ) ), new ObjectPersistableField ( "shoeSize", false, false, false, new ObjectPersistableFieldRelationship ( "NewObjectWithId", true ) ) ) );
         $ageObject = new NewObjectWithId ( "Baby Boy", "YY6 7UU", "10767 878787" );
         $shoeSize1 = new NewObjectWithId ( "Marie Claire", null, "01787 989898" );
         $shoeSize2 = new NewObjectWithId ( "Baby Face", null, "01276 787878" );
         $shoeSize3 = new NewObjectWithId ( "John Doe", null, "01278 988222" );
         $shoeSizeObjects = array ($shoeSize1, $shoeSize2, $shoeSize3 );
         $object = new ObjectWithId ( "Peter Smith", $ageObject, $shoeSizeObjects );
         $this->coordinator->setObjectMappers ( array ($objectMapper ) );

         $this->coordinator->saveObject ( $object );

         $this->assertNotNull ( $ageObject->getId () );
         $this->assertNotNull ( $shoeSize1->getId () );
         $this->assertNotNull ( $shoeSize1->getId () );
         $this->assertNotNull ( $shoeSize1->getId () );

         // Now pull the object
         $reObject = $this->coordinator->getObjectByPrimaryKey ( "ObjectWithId", 1 );

         // Check that the full monty is returned.
         $this->assertEquals ( new ObjectWithId ( "Peter Smith", $ageObject, $shoeSizeObjects, 1 ), $reObject );

     }*/

    public function testCanGetMultipleObjectsBySinglePrimaryKey() {

        $object1 = new ObjectWithId ("Peter Smith", 25, 13);
        $object2 = new ObjectWithId ("Jane Jones", 15, 10);
        $object3 = new ObjectWithId ("Walter Winters", 33, 12);
        $this->coordinator->saveObject($object1);
        $this->coordinator->saveObject($object2);
        $this->coordinator->saveObject($object3);

        $this->assertEquals(array("Kinikit\Persistence\UPF\Framework\ObjectWithId:3" => $object3, "Kinikit\Persistence\UPF\Framework\ObjectWithId:1" => $object1,
            "Kinikit\Persistence\UPF\Framework\ObjectWithId:2" => $object2), $this->coordinator->getMultipleObjectsByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array(3,
            1, 2)));

    }

    public function testCanGetSimpleObjectsByCompoundPrimaryKey() {

        $this->connection->query("DROP TABLE IF EXISTS object_with_id");
        $this->connection->query("CREATE TABLE object_with_id (id INTEGER, name VARCHAR(255), age INTEGER, shoe_size INTEGER, PRIMARY KEY(name, shoe_size))");

        $objectMapper =
            new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id", new ObjectPersistableField ("name", false, true), "age",
                new ObjectPersistableField ("shoeSize", false, true)));
        $this->coordinator->setObjectMappers(array($objectMapper));

        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);
        $this->assertEquals($object, $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("Peter Smith",
            13)));

    }

    /*
     public function testCanGetDeepObjectsByCompoundPrimaryKey() {

         $this->connection->query ( "DROP TABLE IF EXISTS object_with_id" );
         $this->connection->query ( "CREATE TABLE object_with_id (id INTEGER, name VARCHAR(255), age INTEGER, shoe_size INTEGER, PRIMARY KEY(id, name))" );

         $this->connection->query ( "DROP TABLE IF EXISTS object_with_id_new_object_with_id" );
         $this->connection->query ( "CREATE TABLE object_with_id_new_object_with_id (object_with_id_id INTEGER, object_with_id_name VARCHAR(255),  new_object_with_id_id INTEGER)" );

         $objectMapper = new ObjectMapper ( "ObjectWithId", array (new ObjectPersistableField ( "id", false, true ), new ObjectPersistableField ( "name", false, true ), new ObjectPersistableField ( "age", false, false, false, new ObjectPersistableFieldRelationship ( "NewObjectWithId" ) ), new ObjectPersistableField ( "shoeSize", false, false, false, new ObjectPersistableFieldRelationship ( "NewObjectWithId", true ) ) ) );
         $ageObject = new NewObjectWithId ( "Baby Boy", "YY6 7UU", "10767 878787" );
         $shoeSize1 = new NewObjectWithId ( "Marie Claire", null, "01787 989898" );
         $shoeSize2 = new NewObjectWithId ( "Baby Face", null, "01276 787878" );
         $shoeSize3 = new NewObjectWithId ( "John Doe", null, "01278 988222" );
         $shoeSizeObjects = array ($shoeSize1, $shoeSize2, $shoeSize3 );
         $object = new ObjectWithId ( "Peter Smith", $ageObject, $shoeSizeObjects, 56 );
         $this->coordinator->setObjectMappers ( array ($objectMapper ) );

         $this->coordinator->saveObject ( $object );

         $this->assertNotNull ( $ageObject->getId () );
         $this->assertNotNull ( $shoeSize1->getId () );
         $this->assertNotNull ( $shoeSize1->getId () );
         $this->assertNotNull ( $shoeSize1->getId () );

         // Now pull the object
         $reObject = $this->coordinator->getObjectByPrimaryKey ( "ObjectWithId", array (56, "Peter Smith" ) );

         // Check that the full monty is returned.
         $this->assertEquals ( new ObjectWithId ( "Peter Smith", $ageObject, $shoeSizeObjects, 56 ), $reObject );

     } */

    public function testCanGetMultipleObjectsByCompoundPrimaryKey() {

        $this->connection->query("DROP TABLE IF EXISTS object_with_id");
        $this->connection->query("CREATE TABLE object_with_id (id INTEGER, name VARCHAR(255), age INTEGER, shoe_size INTEGER, PRIMARY KEY(name, shoe_size))");

        $objectMapper =
            new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id", new ObjectPersistableField ("name", false, true), "age",
                new ObjectPersistableField ("shoeSize", false, true)));
        $this->coordinator->setObjectMappers(array($objectMapper));

        $object1 = new ObjectWithId ("Peter Smith", 25, 13);
        $object2 = new ObjectWithId ("Jane Jones", 15, 10);
        $object3 = new ObjectWithId ("Walter Winters", 33, 12);
        $this->coordinator->saveObject($object1);
        $this->coordinator->saveObject($object2);
        $this->coordinator->saveObject($object3);

        $this->assertEquals(array("Kinikit\Persistence\UPF\Framework\ObjectWithId:Walter Winters||12" => $object3,
            "Kinikit\Persistence\UPF\Framework\ObjectWithId:Jane Jones||10" => $object2,
            "Kinikit\Persistence\UPF\Framework\ObjectWithId:Peter Smith||13" => $object1), $this->coordinator->getMultipleObjectsByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array(array("Walter Winters",
            12), array("Jane Jones", 10), array("Peter Smith", 13))));

    }

    public function testIfNoneCompatibleQueryObjectPassedAsQueryObjectAnUnsupportedEngineQueryExceptionIsRaised() {

        try {
            $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", new ObjectWithId ());
            $this->fail("Should have thrown here");
        } catch (UnsupportedEngineQueryException $e) {
            // Success
        }

        $this->assertTrue(true);
    }

    public function testCanExecuteArbitraryFullQueryForObjectsUsingStringQueryObject() {

        $this->connection->query("INSERT INTO object_with_id (name, age, shoe_size) VALUES ('Paul', 33, 7), ('Patrick', 12, 9), ('John', 30, 10)");
        $this->connection->query("INSERT INTO new_object_with_id (name, postcode, mobile) VALUES ('Bob', 'OX6 UYY', '06767 878787'), ('Bunny', 'RTU 7II', '8787 8787877')");

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "SELECT * FROM object_with_id");

        $this->assertEquals(3, sizeof($results));

        $this->assertEquals(new ObjectWithId ("Paul", 33, 7, 1), $results[0]);
        $this->assertEquals(new ObjectWithId ("Patrick", 12, 9, 2), $results[1]);
        $this->assertEquals(new ObjectWithId ("John", 30, 10, 3), $results[2]);

        $results =
            $this->coordinator->query("Kinikit\Persistence\UPF\Framework\NewObjectWithId", "SELECT * FROM new_object_with_id WHERE name like 'b%'");

        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new NewObjectWithId ("Bob", "OX6 UYY", "06767 878787", 1), $results[0]);
        $this->assertEquals(new NewObjectWithId ("Bunny", 'RTU 7II', '8787 8787877', 2), $results [1]);

        $results =
            array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "SELECT * FROM object_with_id WHERE name like 'Pa%' ORDER BY id DESC"));

        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new ObjectWithId ("Patrick", 12, 9, 2), $results [0]);
        $this->assertEquals(new ObjectWithId ("Paul", 33, 7, 1), $results [1]);

    }

    public function testCanExecuteArbitraryWhereClauseOnlyQueryForObjectsUsingStringQueryObject() {

        $this->connection->query("INSERT INTO object_with_id (name, age, shoe_size) VALUES ('Paul', 33, 7), ('Patrick', 12, 9), ('John', 30, 10)");
        $this->connection->query("INSERT INTO new_object_with_id (name, postcode, mobile) VALUES ('Bob', 'OX6 UYY', '06767 878787'), ('Bunny', 'RTU 7II', '8787 8787877')");

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "");

        $this->assertEquals(3, sizeof($results));
        $this->assertEquals(new ObjectWithId ("Paul", 33, 7, 1), $results [0]);
        $this->assertEquals(new ObjectWithId ("Patrick", 12, 9, 2), $results [1]);
        $this->assertEquals(new ObjectWithId ("John", 30, 10, 3), $results [2]);

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\NewObjectWithId", "WHERE name like 'b%'");

        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new NewObjectWithId ("Bob", "OX6 UYY", "06767 878787", 1), $results [0]);
        $this->assertEquals(new NewObjectWithId ("Bunny", 'RTU 7II', '8787 8787877', 2), $results [1]);

        $results = array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "WHERE name like 'Pa%' ORDER BY id DESC"));

        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new ObjectWithId ("Patrick", 12, 9, 2), $results [0]);
        $this->assertEquals(new ObjectWithId ("Paul", 33, 7, 1), $results [1]);

    }


    public function testCanUsePlaceholdersInArbitraryQueryAndPassAdditionalParams() {

        $this->connection->query("INSERT INTO object_with_id (name, age, shoe_size) VALUES ('Paul', 33, 7), ('Patrick', 12, 9), ('John', 30, 10)");
        $this->connection->query("INSERT INTO new_object_with_id (name, postcode, mobile) VALUES ('Bob', 'OX6 UYY', '06767 878787'), ('Bunny', 'RTU 7II', '8787 8787877')");

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\NewObjectWithId", "WHERE name like ?", "b%");

        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new NewObjectWithId ("Bob", "OX6 UYY", "06767 878787", 1), $results [0]);
        $this->assertEquals(new NewObjectWithId ("Bunny", 'RTU 7II', '8787 8787877', 2), $results [1]);

        $results = array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "WHERE name like ? AND age > ? ORDER BY id DESC", "Pa%", 30));

        $this->assertEquals(1, sizeof($results));
        $this->assertEquals(new ObjectWithId ("Paul", 33, 7, 1), $results [0]);
    }


    public function testSQLExceptionIsRaisedIfNonsenseQueryIsAttemptedToBeExecuted() {

        try {
            $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\NewObjectWithId", "WHERE bobbins like 'b%'");
            $this->fail("Should have thrown here");
        } catch (SQLException $e) {
            // Success
        }

        $this->assertTrue(true);
    }

    public function testCanExecuteFullAndWhereOnlyQueriesUsingSQLQueryObjects() {

        $this->connection->query("INSERT INTO object_with_id (name, age, shoe_size) VALUES ('Paul', 33, 7), ('Patrick', 12, 9), ('John', 30, 10)");
        $this->connection->query("INSERT INTO new_object_with_id (name, postcode, mobile) VALUES ('Bob', 'OX6 UYY', '06767 878787'), ('Bunny', 'RTU 7II', '8787 8787877')");

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", new SQLQuery ("SELECT * FROM object_with_id"));

        $this->assertEquals(3, sizeof($results));
        $this->assertEquals(new ObjectWithId ("Paul", 33, 7, 1), $results [0]);
        $this->assertEquals(new ObjectWithId ("Patrick", 12, 9, 2), $results [1]);
        $this->assertEquals(new ObjectWithId ("John", 30, 10, 3), $results [2]);

        $results =
            $this->coordinator->query("Kinikit\Persistence\UPF\Framework\NewObjectWithId", new SQLQuery ("SELECT * FROM new_object_with_id WHERE name like ?", "b%"));

        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new NewObjectWithId ("Bob", "OX6 UYY", "06767 878787", 1), $results [0]);
        $this->assertEquals(new NewObjectWithId ("Bunny", 'RTU 7II', '8787 8787877', 2), $results [1]);

        $results =
            array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", new SQLQuery ("SELECT * FROM object_with_id WHERE name like ? ORDER BY id DESC", "Pa%")));

        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new ObjectWithId ("Patrick", 12, 9, 2), $results [0]);
        $this->assertEquals(new ObjectWithId ("Paul", 33, 7, 1), $results [1]);

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", new SQLQuery (""));

        $this->assertEquals(3, sizeof($results));
        $this->assertEquals(new ObjectWithId ("Paul", 33, 7, 1), $results [0]);
        $this->assertEquals(new ObjectWithId ("Patrick", 12, 9, 2), $results [1]);
        $this->assertEquals(new ObjectWithId ("John", 30, 10, 3), $results [2]);

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\NewObjectWithId", new SQLQuery ("WHERE name like ?", 'b%'));

        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new NewObjectWithId ("Bob", "OX6 UYY", "06767 878787", 1), $results [0]);
        $this->assertEquals(new NewObjectWithId ("Bunny", 'RTU 7II', '8787 8787877', 2), $results [1]);

        $results =
            array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", new SQLQuery ("WHERE name like ? ORDER BY id DESC", 'Pa%')));

        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new ObjectWithId ("Patrick", 12, 9, 2), $results [0]);
        $this->assertEquals(new ObjectWithId ("Paul", 33, 7, 1), $results [1]);

    }

    public function testCanQueryByPKForObjectsDefinedByORMViewSQL() {

        $this->connection->query("INSERT INTO object_with_id (id , name , age , shoe_size) VALUES (1, 'Mark', 23, 7)");
        $this->connection->query("INSERT INTO new_object_with_id (id , name , postcode , mobile) VALUES (1, 'Bob', 'OX6 7TT', '07656 878787')");

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\AggregateObject", array(new ObjectPersistableField ("id1", true, true)));
        $mapper->setOrmViewSQL("SELECT o.id id1, o.name name1, o.age, o.shoe_size, n.id id2, n.name name2, n.postcode, n.mobile FROM object_with_id o, new_object_with_id n WHERE o.id = n.id");
        $this->coordinator->setObjectMappers(array($mapper));

        $aggregate = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\AggregateObject", 1);
        $this->assertEquals(new AggregateObject (1, 1, "Mark", "Bob", 23, 7, "OX6 7TT", "07656 878787"), $aggregate);

    }

    public function testCanExecuteWhereClauseSQLQueryAgainstAnObjectDefinedByORMViewSQL() {

        $this->connection->query("INSERT INTO object_with_id (id , name , age , shoe_size) VALUES (1, 'Mark', 23, 7), (2, 'Luke', 24, 8), (3, 'Mary', 29, 3)");
        $this->connection->query("INSERT INTO new_object_with_id (id , name , postcode , mobile) VALUES (1, 'Bob', 'OX6 7TT', '07656 878787'), (2, 'Jane', 'OE6 7TT', '06767 989898'), (3, 'Peter', 'OX4 7YY', '07565 878787')");

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\AggregateObject", array(new ObjectPersistableField ("id1", true, true)));
        $mapper->setOrmViewSQL("SELECT o.id id1, o.name name1, o.age, o.shoe_size, n.id id2, n.name name2, n.postcode, n.mobile FROM object_with_id o, new_object_with_id n WHERE o.id = n.id");
        $this->coordinator->setObjectMappers(array($mapper));

        $results = array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\AggregateObject", "ORDER BY name1"));
        $this->assertEquals(3, sizeof($results));
        $this->assertEquals(new AggregateObject (2, 2, "Luke", "Jane", 24, 8, "OE6 7TT", "06767 989898"), $results [0]);
        $this->assertEquals(new AggregateObject (1, 1, "Mark", "Bob", 23, 7, "OX6 7TT", "07656 878787"), $results [1]);
        $this->assertEquals(new AggregateObject (3, 3, "Mary", "Peter", 29, 3, "OX4 7YY", "07565 878787"), $results [2]);

        $results = array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\AggregateObject", "WHERE name2 LIKE 'J%'"));
        $this->assertEquals(1, sizeof($results));
        $this->assertEquals(new AggregateObject (2, 2, "Luke", "Jane", 24, 8, "OE6 7TT", "06767 989898"), $results [0]);

    }


    public function testCanExecuteFullQueryAgainstAnObjectDefinedByORMViewSQLUsingViewPlaceholder() {


        $this->connection->query("INSERT INTO object_with_id (id , name , age , shoe_size) VALUES (1, 'Mark', 23, 7), (2, 'Luke', 24, 8), (3, 'Mary', 29, 3)");
        $this->connection->query("INSERT INTO new_object_with_id (id , name , postcode , mobile) VALUES (1, 'Bob', 'OX6 7TT', '07656 878787'), (2, 'Jane', 'OE6 7TT', '06767 989898'), (3, 'Peter', 'OX4 7YY', '07565 878787')");

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\AggregateObject", array(new ObjectPersistableField ("id1", true, true)));
        $mapper->setOrmViewSQL("SELECT o.id id1, o.name name1, o.age, o.shoe_size, n.id id2, n.name name2, n.postcode, n.mobile FROM object_with_id o, new_object_with_id n WHERE o.id = n.id");
        $this->coordinator->setObjectMappers(array($mapper));

        $results =
            array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\AggregateObject", "SELECT * FROM #VIEW ORDER BY name1 LIMIT 2"));
        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new AggregateObject (2, 2, "Luke", "Jane", 24, 8, "OE6 7TT", "06767 989898"), $results [0]);
        $this->assertEquals(new AggregateObject (1, 1, "Mark", "Bob", 23, 7, "OX6 7TT", "07656 878787"), $results [1]);

        $results =
            array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\AggregateObject", "SELECT * FROM #VIEW WHERE name2 LIKE 'J%'"));
        $this->assertEquals(1, sizeof($results));
        $this->assertEquals(new AggregateObject (2, 2, "Luke", "Jane", 24, 8, "OE6 7TT", "06767 989898"), $results [0]);


    }

    public function testExceptionRaisedIfAnAttemptToSaveOrDeleteAnObjectWithORMViewSQLDefined() {

        $this->connection->query("INSERT INTO object_with_id (id , name , age , shoe_size) VALUES (1, 'Mark', 23, 7)");
        $this->connection->query("INSERT INTO new_object_with_id (id , name , postcode , mobile) VALUES (1, 'Bob', 'OX6 7TT', '07656 878787')");

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\AggregateObject", array(new ObjectPersistableField ("id1", true, true)));
        $mapper->setOrmViewSQL("SELECT o.id id1, o.name name1, o.age, o.shoe_size, n.id id2, n.name name2, n.postcode, n.mobile FROM object_with_id o, new_object_with_id n WHERE o.id = n.id");
        $this->coordinator->setObjectMappers(array($mapper));

        $aggregate = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\AggregateObject", 1);
        $aggregate->setName2("Henry");

        try {
            $this->coordinator->saveObject($aggregate);
            $this->fail("Should have thrown here");
        } catch (ORMObjectNotWritableException $e) {
            // Success
        }


        try {
            $this->coordinator->removeObject($aggregate);
            $this->fail("Should have thrown here");
        } catch (ORMObjectNotWritableException $e) {
            // Success
        }

        $this->assertTrue(true);

    }


    public function testSaveIsIgnoredIfAnAttemptToSaveOrDeleteAnObjectWithORMViewSQLDefinedIfAllowRelationshipPersistenceDefined() {


        $this->connection->query("INSERT INTO object_with_id (id , name , age , shoe_size) VALUES (1, 'Mark', 23, 7)");
        $this->connection->query("INSERT INTO new_object_with_id (id , name , postcode , mobile) VALUES (1, 'Bob', 'OX6 7TT', '07656 878787')");

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\AggregateObject", array(new ObjectPersistableField ("id1", true, true)));
        $mapper->setOrmViewSQL("SELECT o.id id1, o.name name1, o.age, o.shoe_size, n.id id2, n.name name2, n.postcode, n.mobile FROM object_with_id o, new_object_with_id n WHERE o.id = n.id");
        $mapper->setOrmAllowRelationshipPersistence(1);
        $this->coordinator->setObjectMappers(array($mapper));

        // Get original value
        $aggregate = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\AggregateObject", 1);

        // Get one and change it
        $aggregate2 = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\AggregateObject", 1);
        $aggregate2->setName2("Henry");


        $this->coordinator->saveObject($aggregate);
        $this->coordinator->removeObject($aggregate);


        // Check that the object was never changed.
        $aggregate3 = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\AggregateObject", 1);
        $this->assertEquals($aggregate, $aggregate3);


    }


    public function testCanSpecifyNoBackingObjectAttributeForAMapperWithNoBackingTableOrViewAndThisStillAllowsFullSelectQueriesToBeRunAgainstIt() {

        $this->connection->query("INSERT INTO object_with_id (id , name , age , shoe_size) VALUES (1, 'Mark', 23, 7), (2, 'Luke', 24, 8), (3, 'Mary', 29, 3)");
        $this->connection->query("INSERT INTO new_object_with_id (id , name , postcode , mobile) VALUES (1, 'Bob', 'OX6 7TT', '07656 878787'), (2, 'Jane', 'OE6 7TT', '06767 989898'), (3, 'Peter', 'OX4 7YY', '07565 878787')");

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\AggregateObject", array(new ObjectPersistableField ("id1", true, true)));
        $mapper->setOrmNoBackingObject(1);
        $this->coordinator->setObjectMappers(array($mapper));

        $results =
            array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\AggregateObject", "SELECT o.id id1, o.name name1, o.age, o.shoe_size, n.id id2, n.name name2, n.postcode, n.mobile FROM object_with_id o, new_object_with_id n WHERE o.id = n.id ORDER BY name1 LIMIT 2"));
        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(new AggregateObject (2, 2, "Luke", "Jane", 24, 8, "OE6 7TT", "06767 989898"), $results [0]);
        $this->assertEquals(new AggregateObject (1, 1, "Mark", "Bob", 23, 7, "OX6 7TT", "07656 878787"), $results [1]);

        $results =
            array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\AggregateObject", "SELECT o.id id1, o.name name1, o.age, o.shoe_size, n.id id2, n.name name2, n.postcode, n.mobile FROM object_with_id o, new_object_with_id n WHERE o.id = n.id AND n.name LIKE 'J%'"));
        $this->assertEquals(1, sizeof($results));
        $this->assertEquals(new AggregateObject (2, 2, "Luke", "Jane", 24, 8, "OE6 7TT", "06767 989898"), $results [0]);

    }

    public function testFullQueryRequiredExceptionRaisedIfWhereClauseOnlySuppliedToQueryForMapperWithNoBackingObject() {

        $this->connection->query("INSERT INTO object_with_id (id , name , age , shoe_size) VALUES (1, 'Mark', 23, 7), (2, 'Luke', 24, 8), (3, 'Mary', 29, 3)");
        $this->connection->query("INSERT INTO new_object_with_id (id , name , postcode , mobile) VALUES (1, 'Bob', 'OX6 7TT', '07656 878787'), (2, 'Jane', 'OE6 7TT', '06767 989898'), (3, 'Peter', 'OX4 7YY', '07565 878787')");

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\AggregateObject", array(new ObjectPersistableField ("id1", true, true)));
        $mapper->setOrmNoBackingObject(1);
        $this->coordinator->setObjectMappers(array($mapper));

        try {
            $results =
                array_values($this->coordinator->query("Kinikit\Persistence\UPF\Framework\AggregateObject", "WHERE o.id = n.id AND n.name LIKE 'J%'"));
            $this->fail("Should have thrown here");
        } catch (ORMFullQueryRequiredException $e) {
            // Success
        }


        $this->assertTrue(true);

    }

    public function testAmbiguousMapperSourceDefinitionExceptionRaisedIfORMTableOrViewSQLDefinedWhenNoORMBackingObjectIsSet() {


        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\AggregateObject", array(new ObjectPersistableField ("id1", true, true)));
        $mapper->setOrmViewSQL("SELECT o.id id1, o.name name1, o.age, o.shoe_size, n.id id2, n.name name2, n.postcode, n.mobile FROM object_with_id o, new_object_with_id n WHERE o.id = n.id");
        $mapper->setOrmNoBackingObject(1);
        $this->coordinator->setObjectMappers(array($mapper));

        try {
            $aggregate = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\AggregateObject", 1);
            $this->fail("Should have thrown here");
        } catch (ORMAmbiguousMapperSourceDefinitionException $e) {
            // Success
        }

        try {
            $aggregate = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\AggregateObject", "WHERE name1 = 'mark'");
            $this->fail("Should have thrown here");
        } catch (ORMAmbiguousMapperSourceDefinitionException $e) {
            // Success
        }

        $newAggregate = new AggregateObject (88);
        try {
            $this->coordinator->saveObject($newAggregate);
            $this->fail("Should have thrown here");
        } catch (ORMAmbiguousMapperSourceDefinitionException $e) {
            // Success
        }

        try {
            $this->coordinator->removeObject($newAggregate);
            $this->fail("Should have thrown here");
        } catch (ORMAmbiguousMapperSourceDefinitionException $e) {
            // Success
        }


        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\AggregateObject", array(new ObjectPersistableField ("id1", true, true)));
        $mapper->setOrmNoBackingObject(1);
        $mapper->setOrmTable("aggregate_object");
        $this->coordinator->setObjectMappers(array($mapper));

        try {
            $aggregate = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\AggregateObject", 1);
            $this->fail("Should have thrown here");
        } catch (ORMAmbiguousMapperSourceDefinitionException $e) {
            // Success
        }

        try {
            $aggregate = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\AggregateObject", "WHERE name1 = 'mark'");
            $this->fail("Should have thrown here");
        } catch (ORMAmbiguousMapperSourceDefinitionException $e) {
            // Success
        }

        $newAggregate = new AggregateObject (88);
        try {
            $this->coordinator->saveObject($newAggregate);
            $this->fail("Should have thrown here");
        } catch (ORMAmbiguousMapperSourceDefinitionException $e) {
            // Success
        }

        try {
            $this->coordinator->removeObject($newAggregate);
            $this->fail("Should have thrown here");
        } catch (ORMAmbiguousMapperSourceDefinitionException $e) {
            // Success
        }


        $this->assertTrue(true);

    }


    public function testCanDefineObjectMappingWithDifferentORMViewAndORMTablePropertiesAndReadOnlyFields() {

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithReadOnlyFields", array(new ObjectPersistableField ("id", true, true, true), new ObjectPersistableField("name", true, false),
            new ObjectPersistableField("applicationName", true, false, false, null, true), new ObjectPersistableField("applicationVersion", true, false, false, null, true)));

        $mapper->setOrmView("object_with_read_only_fields_view");
        $mapper->setOrmTable("object_with_read_only_fields");

        $this->coordinator->setObjectMappers(array($mapper));


        $objectWithReadOnlyFields = new ObjectWithReadOnlyFields("Bobby");
        $this->coordinator->saveObject($objectWithReadOnlyFields);

        $reObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithReadOnlyFields", 1);
        $this->assertEquals(new ObjectWithReadOnlyFields("Bobby", "Test Application", date('Y-m-d'), 1), $reObject);

    }


    public function testDatabaseTransactionIsRolledBackIfProblemOccursDuringSave() {

        // Remove the age field.
        $this->connection->query("DROP TABLE IF EXISTS object_with_id");
        $this->connection->query("CREATE TABLE object_with_id (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), shoe_size INTEGER)");

        $object = new ObjectWithId ("Peter Smith", 25, new NewObjectWithId ("Bobby", "Ox6 788", "01678 987897"));

        try {
            $result = $this->coordinator->saveObject($object);
            $this->fail("Should have thrown here");
        } catch (ORMColumnDoesNotExistException $e) {
            // Success
        }

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM object_with_id"));
        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM new_object_with_id"));

    }


    public function testCanQueryForObjectsCount() {

        $this->connection->query("INSERT INTO object_with_id (name, age, shoe_size) VALUES ('Paul', 33, 7), ('Patrick', 12, 9), ('John', 30, 10)");
        $this->connection->query("INSERT INTO new_object_with_id (name, postcode, mobile) VALUES ('Bob', 'OX6 UYY', '06767 878787'), ('Bunny', 'RTU 7II', '8787 8787877')");


        $count = $this->coordinator->count("Kinikit\Persistence\UPF\Framework\ObjectWithId", "");
        $this->assertEquals(3, $count);

        $count = $this->coordinator->count("Kinikit\Persistence\UPF\Framework\ObjectWithId", "WHERE name LIKE 'p%'");
        $this->assertEquals(2, $count);

        $count = $this->coordinator->count("Kinikit\Persistence\UPF\Framework\ObjectWithId", "WHERE age < 30");
        $this->assertEquals(1, $count);


        $count = $this->coordinator->count("Kinikit\Persistence\UPF\Framework\NewObjectWithId", "");
        $this->assertEquals(2, $count);


    }

}

?>