<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Object\ProtectedSerialisable;
use Kinikit\Core\Object\PublicGetterSerialisable;

include_once "autoloader.php";

/**
 * Test cases for the mapper manager wrapper object which acts as the master lookup object passed around to the
 * child worker objects.
 *
 * @author mark
 *
 */
class ObjectMapperManagerTest extends \PHPUnit\Framework\TestCase {

    public function testCanAddMappersToTheManagerAndRetrieveThemIntact() {

        $manager = new ObjectMapperManager ();

        $mapper1 = new ObjectMapper ("Kinikit\Core\Object\ProtectedSerialisable");
        $mapper2 = new ObjectMapper ("Kinikit\Core\Object\PublicGetterSerialisable", array("name", "telephone"));

        $manager->addMapper($mapper1);
        $manager->addMapper($mapper2);

        // Try access by class
        $this->assertEquals($mapper1, $manager->getMapperForClass("Kinikit\Core\Object\ProtectedSerialisable"));
        $this->assertEquals($mapper2, $manager->getMapperForClass("Kinikit\Core\Object\PublicGetterSerialisable"));

        // Now make an object of each type and test access by object instance.
        $this->assertEquals($mapper1, $manager->getMapperForObject(new ProtectedSerialisable ()));
        $this->assertEquals($mapper2, $manager->getMapperForObject(new PublicGetterSerialisable ()));

    }

    public function testNewBoilerplateMapperIsMadeOnRetrievalIfMapperHasNotBeenAddedForClassType() {

        $manager = new ObjectMapperManager ();

        $this->assertEquals(new ObjectMapper ("Kinikit\Core\Object\ProtectedSerialisable"), $manager->getMapperForClass("Kinikit\Core\Object\ProtectedSerialisable"));
        $this->assertEquals(new ObjectMapper ("Kinikit\Core\Object\PublicGetterSerialisable"), $manager->getMapperForClass("Kinikit\Core\Object\PublicGetterSerialisable"));

        $manager = new ObjectMapperManager ();

        $this->assertEquals(new ObjectMapper ("Kinikit\Core\Object\ProtectedSerialisable"), $manager->getMapperForObject(new ProtectedSerialisable ()));
        $this->assertEquals(new ObjectMapper ("Kinikit\Core\Object\PublicGetterSerialisable"), $manager->getMapperForObject(new PublicGetterSerialisable ()));

    }


    public function testMapperForParentClassIsReturnedIfMapperForUnmappedSubClassIsRequested() {

        $manager = new ObjectMapperManager();
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\Contact");
        $manager->addMapper($mapper);

        $this->assertEquals($mapper, $manager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact"));
        $this->assertEquals($mapper, $manager->getMapperForClass("Kinikit\Persistence\UPF\Framework\ContactVariant"));
        $this->assertEquals($mapper, $manager->getMapperForClass("Kinikit\Persistence\UPF\Framework\ContactSubVariant"));

    }

    public function testCreatesNewMapperIfNoneExists() {

        $manager = new ObjectMapperManager();
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\Contact");

        $this->assertEquals($mapper, $manager->getMapperForClass("Kinikit\Persistence\UPF\Framework\Contact"));

    }

    public function testCanAddMappersWhichExtendOtherMappersAndTheMapperDefinitionGetsMerged() {

        $manager = new ObjectMapperManager();
        $mapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\Contact", array(new ObjectPersistableField("Bingo"), new ObjectPersistableField("Bongo")),
            array(new ObjectRelationship("Biggles", "Address")), "Raspberries");

        // Add some adhoc data
        $mapper->setTestProperty("Hello world");

        $manager->addMapper($mapper);

        $subMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ContactVariant", array(new ObjectPersistableField("Bungle")), array(new ObjectRelationship("Funky", "Telephone")));
        $subMapper->setExtends("Kinikit\Persistence\UPF\Framework\Contact");

        $manager->addMapper($subMapper);


        // Grab the submapper and check that it has now been decorated
        $reMapper = $manager->getMapperForClass("Kinikit\Persistence\UPF\Framework\ContactVariant");

     
        $this->assertEquals("Kinikit\Persistence\UPF\Framework\ContactVariant", $reMapper->getClassName());
        $this->assertEquals(array(new ObjectPersistableField("Bingo"), new ObjectPersistableField("Bongo"), new ObjectPersistableField("Bungle")), $reMapper->getFields());
        $this->assertEquals(array(new ObjectRelationship("Biggles", "Address"), new ObjectRelationship("Funky", "Telephone")), $reMapper->getRelationships());
        $this->assertEquals("Raspberries", $reMapper->getEnabledEngines());

        // Check adhoc data also gets attached.
        $this->assertEquals("Hello world", $reMapper->getTestProperty());

    }


    public function testCanExtendMappersUpTheChainAndTheMapperDefinitionIsMergedCorrectly() {

        $manager = new ObjectMapperManager();
        $mapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\Contact", array(new ObjectPersistableField("Bingo"), new ObjectPersistableField("Bongo")),
            array(new ObjectRelationship("Biggles", "Address")), "Raspberries");

        $manager->addMapper($mapper);

        $subMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ContactVariant", array(new ObjectPersistableField("Bungle")), array(new ObjectRelationship("Funky", "Telephone")));
        $subMapper->setExtends("Kinikit\Persistence\UPF\Framework\Contact");

        $manager->addMapper($subMapper);

        $subSubMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ContactSubVariant", array(new ObjectPersistableField("Bengo")), array(new ObjectRelationship("Happy", "Shopping")));
        $subSubMapper->setExtends("Kinikit\Persistence\UPF\Framework\ContactVariant");

        $manager->addMapper($subSubMapper);

        // Check the sub variant first
        $reMapper = $manager->getMapperForClass("Kinikit\Persistence\UPF\Framework\ContactSubVariant");
        $this->assertEquals("Kinikit\Persistence\UPF\Framework\ContactSubVariant", $reMapper->getClassName());
        $this->assertEquals(array(new ObjectPersistableField("Bingo"), new ObjectPersistableField("Bongo"), new ObjectPersistableField("Bungle"), new ObjectPersistableField("Bengo")), $reMapper->getFields());
        $this->assertEquals(array(new ObjectRelationship("Biggles", "Address"), new ObjectRelationship("Funky", "Telephone"), new ObjectRelationship("Happy", "Shopping")), $reMapper->getRelationships());


        // Check the variant is still intact.
        $reMapper = $manager->getMapperForClass("Kinikit\Persistence\UPF\Framework\ContactVariant");
        $this->assertEquals("Kinikit\Persistence\UPF\Framework\ContactVariant", $reMapper->getClassName());
        $this->assertEquals(array(new ObjectPersistableField("Bingo"), new ObjectPersistableField("Bongo"), new ObjectPersistableField("Bungle")), $reMapper->getFields());
        $this->assertEquals(array(new ObjectRelationship("Biggles", "Address"), new ObjectRelationship("Funky", "Telephone")), $reMapper->getRelationships());


    }

    public function testAdhocPropertiesIfOverloadedAreSetToChildRatherThanParentValue() {


        $manager = new ObjectMapperManager();
        $mapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\Contact", array(new ObjectPersistableField("Bingo"), new ObjectPersistableField("Bongo")),
            array(new ObjectRelationship("Biggles", "Address")), "Raspberries");
        $mapper->setTestProp("Bingo");

        $manager->addMapper($mapper);

        $subMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ContactVariant", array(new ObjectPersistableField("Bungle")), array(new ObjectRelationship("Funky", "Telephone")));
        $subMapper->setExtends("Kinikit\Persistence\UPF\Framework\Contact");
        $subMapper->setTestProp("Bongo");

        $manager->addMapper($subMapper);


        $reMapper = $manager->getMapperForClass("Kinikit\Persistence\UPF\Framework\ContactVariant");
        $this->assertEquals("Bongo", $reMapper->getTestProp());


    }


    public function testAdhocPropertiesOnlySetInChildAreRetainedInAnExtendedObject() {

        $manager = new ObjectMapperManager();
        $mapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\Contact", array(new ObjectPersistableField("Bingo"), new ObjectPersistableField("Bongo")),
            array(new ObjectRelationship("Biggles", "Address")), "Raspberries");
        $manager->addMapper($mapper);

        $subMapper = new ObjectMapper("Kinikit\Persistence\UPF\Framework\ContactVariant", array(new ObjectPersistableField("Bungle")), array(new ObjectRelationship("Funky", "Telephone")));
        $subMapper->setExtends("Kinikit\Persistence\UPF\Framework\Contact");
        $subMapper->setTestProp("Bongo");

        $manager->addMapper($subMapper);


        $reMapper = $manager->getMapperForClass("Kinikit\Persistence\UPF\Framework\ContactVariant");
        $this->assertEquals("Bongo", $reMapper->getTestProp());

    }


}

?>