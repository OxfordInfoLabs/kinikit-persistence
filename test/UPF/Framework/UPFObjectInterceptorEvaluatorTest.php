<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\MVC\Framework\ModelAndView;
use Kinikit\Persistence\UPF\Exception\InvalidObjectInterceptorException;

include_once "autoloader.php";

/**
 * Test cases for the UPF object interceptor evaluator.
 *
 * @author matthew
 *
 */
class UPFObjectInterceptorEvaluatorTest extends \PHPUnit\Framework\TestCase {

    public function testIfNoDefinedInterceptorsOrObjectTypeTheEvaluatorReturnsTrueForAnyPassedObject() {

        $interceptorEvaluator = new UPFObjectInterceptorEvaluator ();
        $this->assertNull($interceptorEvaluator->evaluateInterceptorsForPreMap());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPostMap());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreSave());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreDelete());

    }

    public function testIfInvalidInterceptorsPassedToSetInterceptorsAnExceptionIsRaised() {

        $interceptorEvaluator = new UPFObjectInterceptorEvaluator ("objectType");

        $testInterceptor1 = new TestObjectInterceptor1 ();
        $testInterceptor2 = new TestObjectInterceptor2 ();
        $testInterceptorBad = new ObjectWithId();

        try {
            $interceptorEvaluator->setInterceptors(array($testInterceptor1, $testInterceptor2, $testInterceptorBad));
            $this->fail("Should have thrown an exception here");
        } catch (InvalidObjectInterceptorException $e) {
            // Success
        }

        $this->assertTrue(true);

    }

    public function testIfAllDefinedInterceptorsForAPassedServiceReturnTrueTheEvaluatorReturnsTruePreMapReturnsSameObject() {

        $testInterceptor1 = new TestObjectInterceptor1 ();
        $testInterceptor2 = new TestObjectInterceptor2 ();

        $interceptorEvaluator =
            new UPFObjectInterceptorEvaluator ("object", array($testInterceptor1, $testInterceptor2));

        $newObject = $interceptorEvaluator->evaluateInterceptorsForPreMap("object");
        $this->assertEquals("object", $newObject);
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPostMap());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreSave());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreDelete());

    }

    public function testIfAnyOfTheDefinedInterceptorsReturnFalseTheEvaluatorReturnsFalse() {

        $testInterceptor1 = new TestObjectInterceptor1 ();
        $testInterceptor2 = new TestObjectInterceptor2 ();
        $testInterceptor3 = new TestObjectInterceptor3 ();

        $interceptorEvaluator = new UPFObjectInterceptorEvaluator ("object");

        $interceptorEvaluator->setInterceptors(array($testInterceptor1));
        $this->assertEquals("object", $interceptorEvaluator->evaluateInterceptorsForPreMap("object"));
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPostMap());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreSave());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreDelete());

        $interceptorEvaluator->setInterceptors(array($testInterceptor2, $testInterceptor1));
        $this->assertEquals("object", $interceptorEvaluator->evaluateInterceptorsForPreMap("object"));
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPostMap());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreSave());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreDelete());

        $interceptorEvaluator->setInterceptors(array($testInterceptor3));
        $this->assertFalse($interceptorEvaluator->evaluateInterceptorsForPreMap());
        $this->assertFalse($interceptorEvaluator->evaluateInterceptorsForPostMap());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreSave());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreDelete());

        $interceptorEvaluator->setInterceptors(array($testInterceptor1, $testInterceptor2, $testInterceptor3));
        $this->assertFalse($interceptorEvaluator->evaluateInterceptorsForPreMap());
        $this->assertFalse($interceptorEvaluator->evaluateInterceptorsForPostMap());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreSave());
        $this->assertTrue($interceptorEvaluator->evaluateInterceptorsForPreDelete());

    }

    public function testInterceptorsAreRunForAServiceInTheOrderSpecified() {

        TestObjectInterceptor1::$interceptorRuns = array();

        $testInterceptor1 = new TestObjectInterceptor1 ();
        $testInterceptor2 = new TestObjectInterceptor2 ();
        $testInterceptor3 = new TestObjectInterceptor3 ();

        $interceptorEvaluator = new UPFObjectInterceptorEvaluator ();
        $interceptorEvaluator->setInterceptors(array($testInterceptor1, $testInterceptor2, $testInterceptor3));

        $interceptorEvaluator->evaluateInterceptorsForPreMap();
        $this->assertEquals(array("TestObjectInterceptor1", "TestObjectInterceptor2",
                "TestObjectInterceptor3"), TestObjectInterceptor1::$interceptorRuns);

    }

    public function testNoSubsequentInterceptorsAreRunForAServiceIfOneFails() {

        TestObjectInterceptor1::$interceptorRuns = array();

        $testInterceptor1 = new TestObjectInterceptor1 ();
        $testInterceptor2 = new TestObjectInterceptor2 ();
        $testInterceptor3 = new TestObjectInterceptor3 ();

        $interceptorEvaluator = new UPFObjectInterceptorEvaluator ();
        $interceptorEvaluator->setInterceptors(array($testInterceptor1, $testInterceptor3, $testInterceptor2));

        $interceptorEvaluator->evaluateInterceptorsForPreMap();
        $this->assertEquals(array("TestObjectInterceptor1",
                "TestObjectInterceptor3"), TestObjectInterceptor1::$interceptorRuns);
    }

    public function testCanReturnTrueOrFalseDependingOnTheObjectType() {

        $testInterceptor1 = new TestObjectInterceptor1 ();
        $testInterceptor2 = new TestObjectInterceptor2 ();
        $testInterceptor3 = new TestObjectInterceptor3 ();

        $interceptorEvaluator =
            new UPFObjectInterceptorEvaluator ("testType1", array($testInterceptor1, $testInterceptor3,
                    $testInterceptor2));
        $result = $interceptorEvaluator->evaluateInterceptorsForPreMap();
        $this->assertEquals("testType1", $interceptorEvaluator->evaluateInterceptorsForPreMap("testType1"));

        $interceptorEvaluator =
            new UPFObjectInterceptorEvaluator ("testType2", array($testInterceptor1, $testInterceptor3,
                    $testInterceptor2));
        $result = $interceptorEvaluator->evaluateInterceptorsForPreMap();
        $this->assertFalse($result);

    }

    public function testCanReturnTrueOrFalseDependingOnTheObjectTypeAndObject() {

        $testInterceptor1 = new TestObjectInterceptor1 ();
        $testInterceptor2 = new TestObjectInterceptor2 ();
        $testInterceptor3 = new TestObjectInterceptor3 ();

        $interceptorEvaluator =
            new UPFObjectInterceptorEvaluator ("testType1", array($testInterceptor1, $testInterceptor3,
                    $testInterceptor2));
        $result = $interceptorEvaluator->evaluateInterceptorsForPostMap("testObject1");
        $this->assertTrue($result);

        $interceptorEvaluator =
            new UPFObjectInterceptorEvaluator ("testType2", array($testInterceptor1, $testInterceptor3,
                    $testInterceptor2));
        $result = $interceptorEvaluator->evaluateInterceptorsForPostMap("testObject1");
        $this->assertFalse($result);

    }


}

?>