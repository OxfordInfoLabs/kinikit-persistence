<?php
namespace Kinikit\Persistence\UPF\Framework;

include_once "autoloader.php";

/**
 * Test the facade object
 *
 * @author mark
 *
 */
class ObjectFacadeTest extends \PHPUnit\Framework\TestCase {

    public function testCanreturnRealObjectBackFromAConfiguredFacadeOnCall() {

        TestPersistenceEngine::$dataForFieldValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"][33] = array(array("name" => "Steven", "age" => 32, "shoeSize" => 6, "id" => 33));
        $objectFacade = new ObjectFacade ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("33"), "Bob", new ObjectPersistenceCoordinator (array(new TestPersistenceEngine ("Bob"))));
        $realObject = $objectFacade->returnRealObject();


        $this->assertEquals(new ObjectWithId ("Steven", 32, 6, 33), $realObject);

    }

    public function testRealObjectIsCachedForSubsequentCallsForEfficiencyUnlessNoCacheBooleanIsSupplied() {

        TestPersistenceEngine::$dataForFieldValues ["Kinikit\Persistence\UPF\Framework\ObjectWithId"][33] = array(array("name" => "Steven", "age" => 32, "shoeSize" => 6, "id" => 33));
        $objectFacade = new ObjectFacade ("Kinikit\Persistence\UPF\Framework\ObjectWithId", array("33"), "Bob", new ObjectPersistenceCoordinator (array(new TestPersistenceEngine ("Bob"))));
        $realObject = $objectFacade->returnRealObject();

        $this->assertEquals(new ObjectWithId ("Steven", 32, 6, 33), $realObject);

        // Doctor it a little to test
        $realObject->setName("Steggles");

        // Check that subsequent calls return the same instance
        $this->assertEquals($realObject, $objectFacade->returnRealObject());
        $this->assertEquals($realObject, $objectFacade->returnRealObject());

        // Now pass a nocache boolean
        $newInstance = $objectFacade->returnRealObject(true);
        $this->assertNotEquals($newInstance, $realObject);

        // Check original data is returned.
        $this->assertEquals(new ObjectWithId ("Steven", 32, 6, 33), $newInstance);

    }

}

?>