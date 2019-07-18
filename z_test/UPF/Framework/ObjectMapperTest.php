<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Persistence\UPF\Object\TestActiveRecordContainer;

include_once "autoloader.php";

class ObjectMapperTest extends \PHPUnit\Framework\TestCase {

    public function testCanDetermineWhetherOrNotAMapperIsEnabledForAParticularEngine() {

        // Start with a mapper with no disabled or enabled engines.  This should return true for any engine
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId");
        $this->assertTrue($mapper->isEnabledForEngine("MyEngine"));
        $this->assertTrue($mapper->isEnabledForEngine("Nonsense"));
        $this->assertTrue($mapper->isEnabledForEngine("AnythingGoes"));
        $this->assertTrue($mapper->isEnabledForEngine("Bongo"));
        $this->assertTrue($mapper->isEnabledForEngine("Bingo"));

        // Now create a mapper with some enabled engines defined
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId", null, null, "MyEngine, Nonsense,Bingo");
        $this->assertTrue($mapper->isEnabledForEngine("MyEngine"));
        $this->assertTrue($mapper->isEnabledForEngine("Nonsense"));
        $this->assertFalse($mapper->isEnabledForEngine("AnythingGoes"));
        $this->assertFalse($mapper->isEnabledForEngine("Bongo"));
        $this->assertTrue($mapper->isEnabledForEngine("Bingo"));

        // Now try a mapper with some disabled engined defined.
        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\NewObjectWithId", null, null, null, "AnythingGoes, Bingo");
        $this->assertTrue($mapper->isEnabledForEngine("MyEngine"));
        $this->assertTrue($mapper->isEnabledForEngine("Nonsense"));
        $this->assertFalse($mapper->isEnabledForEngine("AnythingGoes"));
        $this->assertTrue($mapper->isEnabledForEngine("Bongo"));
        $this->assertFalse($mapper->isEnabledForEngine("Bingo"));
    }


    public function testWillReturnAUPFObjectInterceptorEvaluator() {

        $mapper = new ObjectMapper ("Kinikit\Persistence\UPF\Framework\ObjectWithId");
        $interceptorEvaluator = $mapper->setInterceptors();
        $interceptorEvaluator = $mapper->getInterceptorEvaluator();
        $this->assertTrue($interceptorEvaluator instanceof UPFObjectInterceptorEvaluator);


    }


    public function testClassWithoutDefinedXMLMapperCanReadClassAnnotations() {

        $mapper = new ObjectMapper("Kinikit\Persistence\UPF\Object\TestActiveRecordContainer");

        $mapper->getPersistableFieldValueMapForObject(new TestActiveRecordContainer());
        $this->assertEquals("active_record_container", $mapper->getOrmTable());
        $this->assertTrue( $mapper->getInterceptorEvaluator()->getInterceptors()[0] instanceof TestObjectInterceptor1);
        $this->assertTrue( $mapper->getInterceptorEvaluator()->getInterceptors()[1] instanceof TestObjectInterceptor2);


        $this->assertEquals(2, sizeof($mapper->getFields()));
        $tag = $mapper->getField("tag");
        $this->assertTrue($tag->getPrimaryKey());
        $this->assertEquals("tag_name", $tag->getOrmColumn());

        $description = $mapper->getField("description");
        $this->assertTrue($description instanceof ObjectPersistableField);

        $this->assertEquals(1, sizeof($mapper->getRelationships()));
        $relationship = $mapper->getRelationships()[0];
        $this->assertTrue($relationship->getIsMultiple());
        $this->assertEquals("TestActiveRecord", $relationship->getRelatedClassName());
        $relatedFields = $relationship->getRelatedFields();
        $this->assertEquals(2, sizeof($relatedFields));
        $this->assertEquals(new ObjectRelatedField("tag", "containerTag"), $relatedFields[0]);
        $this->assertEquals(new ObjectRelatedField(null, "staticValue", "PIGGY"), $relatedFields[1]);
        $orderingFields = $relationship->getOrderingFields();
        $this->assertEquals(2, sizeof($orderingFields));
        $this->assertEquals(new ObjectOrderingField("name", "ASC"), $orderingFields[0]);
        $this->assertEquals(new ObjectOrderingField("id", "DESC"), $orderingFields[1]);
    }


}

?>