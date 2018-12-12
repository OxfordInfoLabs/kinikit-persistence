<?php

namespace Kinikit\Persistence\UPF\Engines\ObjectIndex;

use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\UPF\Exception\ObjectNotFoundException;
use Kinikit\Persistence\UPF\Framework\Contact;
use Kinikit\Persistence\UPF\Framework\NewObjectWithId;
use Kinikit\Persistence\UPF\Framework\ObjectMapper;
use Kinikit\Persistence\UPF\Framework\ObjectPersistableField;
use Kinikit\Persistence\UPF\Framework\ObjectPersistenceCoordinator;
use Kinikit\Persistence\UPF\Framework\ObjectRelatedField;
use Kinikit\Persistence\UPF\Framework\ObjectRelationship;
use Kinikit\Persistence\UPF\Framework\ObjectWithId;


include_once "autoloader.php";

/**
 * Test cases for the Object Index Persistence engine.
 * @author mark
 *
 */
class ObjectIndexPersistenceEngineTest extends \PHPUnit\Framework\TestCase {

    private $connection;


    /**
     * @var ObjectPersistenceCoordinator
     */
    private $coordinator;

    /**
     * @var ObjectIndexPersistenceEngine
     */
    private $engine;

    /**
     * Set up method
     */
    public function setUp() {


        parent::setUp();

        $this->connection = DefaultDB::instance();

        $this->connection->executeScript(file_get_contents("../sql/objectindexengine.sql"));
        $this->connection->executeScript(file_get_contents("../sql/sqllockingprovider.sql"));
        $this->connection->query("DROP TABLE IF EXISTS kinikit_sequence");


        $this->coordinator = new ObjectPersistenceCoordinator (array(new ObjectIndexPersistenceEngine ($this->connection, "index")));
        $this->engine = $this->coordinator->getInstalledEngineByIdentifier("index");

    }

    public function testCanSaveBrandNewBasicObjectDataToIndex() {

        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);
        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index"));
        $this->assertEquals("1", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'id' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("Peter Smith", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'name' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("25", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'age' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("13", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'shoeSize' AND value_class = 'PRIMITIVE'"));

        $this->assertEquals(1, $object->getId());

    }

    public function testCanUpdateExistingBasicObjectDataToIndex() {

        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);

        $object->setName("John Brown");
        $object->setAge(37);
        $object->setShoeSize(10);

        $this->coordinator->saveObject($object);

        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index"));
        $this->assertEquals("1", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'id' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("John Brown", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'name' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("37", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'age' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("10", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'shoeSize' AND value_class = 'PRIMITIVE'"));

    }

    public function testObjectDataWithNestedObjectReferencesGetWrittenCorrectlyIfRelationshipMapped() {

        $object = new ObjectWithId ("Peter Smith", null, new NewObjectWithId ("Paulio", "OX4 7UU", "07898 989898", 34));

        $newObjectRelationship = new ObjectRelationship("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId");
        $newObjectRelationship->setRelatedFields(array(new ObjectRelatedField("age", "id")));
        $objectMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("name", "age", "id"), array($newObjectRelationship));
        $this->coordinator->setObjectMappers(array($objectMapper));
        $this->coordinator->saveObject($object);

        $this->assertEquals(7, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index"));
        $this->assertEquals("1", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'id' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("Peter Smith", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'name' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("34", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'age' AND value_class = 'PRIMITIVE'"));

        $this->assertEquals(34, $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\NewObjectWithId' AND object_pk = 34 AND field_name = 'id' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("Paulio", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\NewObjectWithId' AND object_pk = 34 AND field_name = 'name' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("OX4 7UU", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\NewObjectWithId' AND object_pk = 34 AND field_name = 'postcode' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("07898 989898", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\NewObjectWithId' AND object_pk = 34 AND field_name = 'mobile' AND value_class = 'PRIMITIVE'"));

    }

    public function testObjectDataWithNestedArrayReferencesGetWrittenAsArrayValuesIfRelationshipMapped() {


        $newObjectRelationship = new ObjectRelationship("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId", true, false, false, false, ObjectRelationship::MASTER_PARENT);
        $newObjectRelationship->setRelatedFields(array(new ObjectRelatedField("id", "mobile")));
        $objectMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("name", "age", "id"), array($newObjectRelationship));
        $this->coordinator->setObjectMappers(array($objectMapper));

        $object = new ObjectWithId ("Peter Smith", 25, array(new NewObjectWithId ("Paulio", "OX4 7UU", "07898 989898", 34), new NewObjectWithId ("Jeeves", "OX7 7UU", "01212 787878", 29), new NewObjectWithId ("Jonah", "CB4 2JL", "01223 355931", 19)));
        $this->coordinator->saveObject($object);

        $this->assertEquals(15, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index"));
        $this->assertEquals("1", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'id' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("Peter Smith", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'name' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("25", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'age' AND value_class = 'PRIMITIVE'"));

        $this->assertEquals("1", $this->connection->queryForSingleValue("SELECT count(*) FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\NewObjectWithId' AND object_pk = 34 AND field_name = 'mobile' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("1", $this->connection->queryForSingleValue("SELECT count(*) FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\NewObjectWithId' AND object_pk = 29 AND field_name = 'mobile' AND value_class = 'PRIMITIVE'"));
        $this->assertEquals("1", $this->connection->queryForSingleValue("SELECT count(*) FROM kinikit_object_index WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\NewObjectWithId' AND object_pk = 19 AND field_name = 'mobile' AND value_class = 'PRIMITIVE'"));

    }

    public function testObjectsAlsoGetVersionedByDefaultWithIncrementalChanges() {

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);

        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

        $this->assertEquals("1", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'id' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL"));
        $this->assertEquals("Peter Smith", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'name' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL"));
        $this->assertEquals("25", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'age' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL"));
        $this->assertEquals("13", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'shoeSize' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL"));

        $object->setName("John Brown");
        $object->setAge(37);
        $this->coordinator->saveObject($object);

        $this->assertEquals(6, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

        $this->assertEquals(1, $this->connection->queryForSingleValue("SELECT count(*) FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'id' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL"));
        $this->assertEquals(1, $this->connection->queryForSingleValue("SELECT  count(*) FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'name' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL AND field_value = 'Peter Smith'"));
        $this->assertEquals(1, $this->connection->queryForSingleValue("SELECT  count(*) FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'age' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL AND field_value = 25"));
        $this->assertEquals(1, $this->connection->queryForSingleValue("SELECT  count(*) FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'shoeSize' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL"));

        $this->assertEquals(1, $this->connection->queryForSingleValue("SELECT  count(*) FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'name' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL AND field_value = 'John Brown'"));
        $this->assertEquals(1, $this->connection->queryForSingleValue("SELECT  count(*) FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'age' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL AND field_value = 37"));

    }


    public function testIfSessionRefProviderSetOnIndexPersistenceEngineItIsUsedToProvideSessionReferenceAndInsertedIntoHistoryTable() {


        $coordinator = new ObjectPersistenceCoordinator (array(new ObjectIndexPersistenceEngine ($this->connection, null, true, new TestSessionReferenceProvider("12345"))));


        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $coordinator->saveObject($object);

        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

        $this->assertEquals("1", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'id' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL AND session_ref = '12345'"));
        $this->assertEquals("Peter Smith", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'name' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL AND session_ref = '12345'"));
        $this->assertEquals("25", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'age' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL AND session_ref = '12345'"));
        $this->assertEquals("13", $this->connection->queryForSingleValue("SELECT field_value FROM kinikit_object_index_history WHERE object_class='Kinikit\\Persistence\\UPF\\Framework\\ObjectWithId' AND object_pk = 1 AND field_name = 'shoeSize' AND value_class = 'PRIMITIVE' AND version_timestamp IS NOT NULL AND session_ref = '12345'"));


    }


    public function testObjectsDoNotGetVersionedIfVersioningIsSwitchedOffForTheEngine() {

        $this->coordinator = new ObjectPersistenceCoordinator (array(new ObjectIndexPersistenceEngine ($this->connection, "myengine", false)));

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

        $object->setName("John Brown");
        $object->setAge(37);
        $object->setShoeSize(10);
        $this->coordinator->saveObject($object);

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

    }

    public function testObjectsDoNotGetVersionedIfNoVersioningIsSpecifiedForMapper() {

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $noVersionMapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId");
        $noVersionMapper->setVersioning(false);
        $this->coordinator->setObjectMappers(array($noVersionMapper));
        $this->coordinator->saveObject($object);

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

        $object->setName("John Brown");
        $object->setAge(37);
        $object->setShoeSize(10);
        $this->coordinator->saveObject($object);

        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index_history"));

    }

    public function testCanRemoveExistingBasicObjectDataFromIndex() {
        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);

        $this->assertEquals(4, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index"));
        $this->coordinator->removeObject($object);
        $this->assertEquals(0, $this->connection->queryForSingleValue("SELECT COUNT(*) FROM kinikit_object_index"));

    }

    public function testCanRetrieveExistingBasicObjectDataFromIndex() {

        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);

        $reObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 1);
        $this->assertEquals(new ObjectWithId ("Peter Smith", 25, 13, 1), $reObject);

    }

    public function testCanRetrieveDeepObjectDataFromIndex() {


        $newObjectRelationship = new ObjectRelationship("shoeSize", "Kinikit\Persistence\UPF\Framework\NewObjectWithId");
        $newObjectRelationship->setRelatedFields(array(new ObjectRelatedField("age", "id")));
        $objectMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("name", "age", "id"), array($newObjectRelationship));
        $this->coordinator->setObjectMappers(array($objectMapper));

        $object = new ObjectWithId ("Peter Smith", null, new NewObjectWithId ("Paulio", "OX4 7UU", "07898 989898", 34));
        $this->coordinator->saveObject($object);

        $reObject = $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 1);

        $this->assertEquals(new ObjectWithId ("Peter Smith", 34, new NewObjectWithId ("Paulio", "OX4 7UU", "07898 989898", 34), 1), $reObject);

    }

    public function testCanStoreAndRetrieveObjectsWithCompoundKeys() {

        $object = new ObjectWithId ("Peter Smith", 25, 13, 256);
        $this->coordinator->setObjectMappers(array(new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("id", new ObjectPersistableField ("name", true, true), new ObjectPersistableField ("age", true, true), "shoeSize"))));
        $this->coordinator->saveObject($object);

        // Check that Id is no longer the primary key
        try {
            $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", 1);
            $this->fail("Should have thrown");
        } catch (ObjectNotFoundException $e) {
            // Success
        }

        $this->assertEquals(new ObjectWithId ("Peter Smith", 25, 13, 256), $this->coordinator->getObjectByPrimaryKey("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("Peter Smith", 25)));

    }


    public function testCanObtainAllIndexedObjectClasses() {

        // Save an object
        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);

        $allIndexedClasses = $this->engine->getAllIndexedObjectClasses();
        $this->assertEquals(array("Kinikit\Persistence\UPF\Framework\ObjectWithId"), $allIndexedClasses);

        $object = new Contact(33, "Test Name", "07656 676767", "33 My Home Close, Oxford", "James, Ben, Paul");
        $this->coordinator->saveObject($object);

        $allIndexedClasses = $this->engine->getAllIndexedObjectClasses();
        $this->assertEquals(array("Kinikit\Persistence\UPF\Framework\Contact", "Kinikit\Persistence\UPF\Framework\ObjectWithId"), $allIndexedClasses);


    }


    public function testCanObtainAllFieldsForIndexedObjectClass() {

        // Save an object
        $object = new ObjectWithId ("Peter Smith", 25, 13);
        $this->coordinator->saveObject($object);

        $object = new Contact(33, "Test Name", "07656 676767", "33 My Home Close, Oxford", "James, Ben, Paul");
        $this->coordinator->saveObject($object);


        $indexedObjectFields = $this->engine->getAllFieldsForIndexedObjectClass("Kinikit\Persistence\UPF\Framework\ObjectWithId");
        $this->assertEquals(array("age", "id", "name", "shoeSize"), $indexedObjectFields);


        $indexedObjectFields = $this->engine->getAllFieldsForIndexedObjectClass("Kinikit\Persistence\UPF\Framework\Contact");
        $this->assertEquals(array("address", "friends", "id", "name", "telephone"), $indexedObjectFields);


    }


    public function testCanQueryForAllObjectsOfATypeWithNaturalOrdering() {

        $object1 = new ObjectWithId ("Peter Smith", 25, 3);
        $this->coordinator->saveObject($object1);

        $object2 = new ObjectWithId ("Paul Wright", 40, 5);
        $this->coordinator->saveObject($object2);

        $object3 = new ObjectWithId ("Jane Mary", 12, 7);
        $this->coordinator->saveObject($object3);

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "");
        $this->assertEquals(3, sizeof($results));
        $this->assertEquals(array($object1, $object2, $object3), array_values($results));

    }


    public function testCanQueryForAllObjectsWithOffsetAndLimiting() {

        $object1 = new ObjectWithId ("Peter Smith", 25, 3);
        $this->coordinator->saveObject($object1);

        $object2 = new ObjectWithId ("Paul Wright", 40, 5);
        $this->coordinator->saveObject($object2);

        $object3 = new ObjectWithId ("Jane Mary", 12, 7);
        $this->coordinator->saveObject($object3);

        $object4 = new ObjectWithId ("Alex Salmond", 54, 10);
        $this->coordinator->saveObject($object4);

        $object5 = new ObjectWithId ("Bobby Ball", 60, 11);
        $this->coordinator->saveObject($object5);

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "LIMIT 3");
        $this->assertEquals(3, sizeof($results));
        $this->assertEquals(array($object1, $object2, $object3), array_values($results));

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "LIMIT 4");
        $this->assertEquals(4, sizeof($results));
        $this->assertEquals(array($object1, $object2, $object3, $object4), array_values($results));

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "LIMIT 5");
        $this->assertEquals(5, sizeof($results));
        $this->assertEquals(array($object1, $object2, $object3, $object4, $object5), array_values($results));


        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "OFFSET 2");
        $this->assertEquals(3, sizeof($results));
        $this->assertEquals(array($object3, $object4, $object5), array_values($results));


        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "LIMIT 3 OFFSET 1");
        $this->assertEquals(3, sizeof($results));
        $this->assertEquals(array($object2, $object3, $object4), array_values($results));

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "LIMIT 2 OFFSET 2");
        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(array($object3, $object4), array_values($results));
    }


    public function testCanQueryForObjectsWithWildcardFilter() {

        $object1 = new ObjectWithId ("Peter Smith", 25, 3);
        $this->coordinator->saveObject($object1);

        $object2 = new ObjectWithId ("Paul Wright", 40, 5);
        $this->coordinator->saveObject($object2);

        $object3 = new ObjectWithId ("Jane Mary", 12, 7);
        $this->coordinator->saveObject($object3);

        $results = $this->coordinator->query("Kinikit\Persistence\UPF\Framework\ObjectWithId", "* = 'P'");

        $this->assertTrue(true);
    }


}

?>