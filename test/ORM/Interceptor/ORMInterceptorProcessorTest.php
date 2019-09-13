<?php

namespace Kinikit\Persistence\ORM\Interceptor;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Reflection\ClassInspectorProvider;
use Kinikit\Core\Testing\MockObject;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\ORM\Address;
use Kinikit\Persistence\ORM\AltAddress;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

/**
 * Test cases for the ORM Interceptor processor.
 *
 * Class ORMInterceptorProcessorTest
 */
class ORMInterceptorProcessorTest extends TestCase {

    /**
     * @var MockObject
     */
    private $globalORMInterceptor;

    /**
     * @var MockObject
     */
    private $configFileORMInterceptor;


    /**
     * @var MockObject
     */
    private $inlineORMInterceptor;


    /**
     * @var ORMInterceptorProcessor
     */
    private $interceptorProcessor;

    /**
     * @var Address[]
     */
    private $addresses;

    /**
     * @var AltAddress[]
     */
    private $altAddresses;

    public function setUp(): void {

        parent::setUp();

        /**
         * @var MockObjectProvider $mockObjectProvider
         */
        $mockObjectProvider = Container::instance()->get(MockObjectProvider::class);

        $this->globalORMInterceptor = $mockObjectProvider->getMockInstance(GlobalORMInterceptor::class);
        Container::instance()->set(GlobalORMInterceptor::class, $this->globalORMInterceptor);


        $this->configFileORMInterceptor = $mockObjectProvider->getMockInstance(ConfigFileORMInterceptor::class);
        Container::instance()->set(ConfigFileORMInterceptor::class, $this->configFileORMInterceptor);

        $this->inlineORMInterceptor = $mockObjectProvider->getMockInstance(InlineORMInterceptor::class);
        Container::instance()->set(InlineORMInterceptor::class, $this->inlineORMInterceptor);

        // Create new interceptor processor.
        $this->interceptorProcessor = new ORMInterceptorProcessor(Container::instance()->get(ClassInspectorProvider::class));

        $this->addresses = [
            new Address(1, "Mark", "2 My Lane", "Oxford", "01865 787878", "GB"),
            new Address(2, "John", "3 My Lane", "Didcot", "01865 111111", "GB"),
            new Address(3, "Claire", "4 My Lane", "Banbury", "01865 123434", "GB"),
        ];

        $this->altAddresses = [
            new AltAddress(1, "Mark", "2 My Lane", "Oxford", "01865 787878", "GB"),
            new AltAddress(2, "John", "3 My Lane", "Didcot", "01865 111111", "GB"),
            new AltAddress(3, "Claire", "4 My Lane", "Banbury", "01865 123434", "GB"),
        ];
    }


    public function testProcessPostMapInterceptorsCallsAllDefinedInterceptorsForClassWithConfiguredInterceptors() {

        $this->globalORMInterceptor->returnValue("postMap", true);
        $this->configFileORMInterceptor->returnValue("postMap", true);
        $this->inlineORMInterceptor->returnValue("postMap", true);

        $results = $this->interceptorProcessor->processPostMapInterceptors(Address::class, $this->addresses);
        $this->assertEquals($this->addresses, $results);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postMap", [$this->addresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postMap", [$this->addresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postMap", [$this->addresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postMap", [$this->addresses[0]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postMap", [$this->addresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postMap", [$this->addresses[2]]));

        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("postMap", [$this->addresses[0]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("postMap", [$this->addresses[1]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("postMap", [$this->addresses[2]]));


    }


    public function testProcessPostMapInterceptorsCallsAllDefinedInterceptorsForClassWithInlineInterceptors() {

        $this->globalORMInterceptor->returnValue("postMap", true);
        $this->configFileORMInterceptor->returnValue("postMap", true);
        $this->inlineORMInterceptor->returnValue("postMap", true);

        $results = $this->interceptorProcessor->processPostMapInterceptors(AltAddress::class, $this->altAddresses);
        $this->assertEquals($this->altAddresses, $results);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[0]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[2]]));

        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[0]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[1]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[2]]));

    }


    public function testProcessPostMapCorrectlyRemovesObjectsWhenFalseIsReturnedFromPostMap() {

        $this->globalORMInterceptor->returnValue("postMap", true);
        $this->globalORMInterceptor->returnValue("postMap", false, [$this->altAddresses[1]]);
        $this->configFileORMInterceptor->returnValue("postMap", true);
        $this->inlineORMInterceptor->returnValue("postMap", true);

        $results = $this->interceptorProcessor->processPostMapInterceptors(AltAddress::class, $this->altAddresses);
        $this->assertEquals([0 => $this->altAddresses[0], 2=> $this->altAddresses[2]], $results);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[0]]));
        $this->assertFalse($this->configFileORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[2]]));

        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[0]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[1]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postMap", [$this->altAddresses[2]]));


    }


    public function testPreSaveCorrectlyCalledForAllDefinedInterceptors() {

        $this->interceptorProcessor->processPreSaveInterceptors(Address::class, $this->addresses);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preSave", [$this->addresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preSave", [$this->addresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preSave", [$this->addresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preSave", [$this->addresses[0]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preSave", [$this->addresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preSave", [$this->addresses[2]]));

        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("preSave", [$this->addresses[0]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("preSave", [$this->addresses[1]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("preSave", [$this->addresses[2]]));


        $this->interceptorProcessor->processPreSaveInterceptors(AltAddress::class, $this->altAddresses);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preSave", [$this->altAddresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preSave", [$this->altAddresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preSave", [$this->altAddresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preSave", [$this->altAddresses[0]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preSave", [$this->altAddresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preSave", [$this->altAddresses[2]]));

        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("preSave", [$this->altAddresses[0]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("preSave", [$this->altAddresses[1]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("preSave", [$this->altAddresses[2]]));


    }


    public function testPostSaveCorrectlyCalledForAllDefinedInterceptors() {

        $this->interceptorProcessor->processPostSaveInterceptors(Address::class, $this->addresses);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postSave", [$this->addresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postSave", [$this->addresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postSave", [$this->addresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postSave", [$this->addresses[0]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postSave", [$this->addresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postSave", [$this->addresses[2]]));

        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("postSave", [$this->addresses[0]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("postSave", [$this->addresses[1]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("postSave", [$this->addresses[2]]));


        $this->interceptorProcessor->processPostSaveInterceptors(AltAddress::class, $this->altAddresses);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postSave", [$this->altAddresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postSave", [$this->altAddresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postSave", [$this->altAddresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postSave", [$this->altAddresses[0]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postSave", [$this->altAddresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postSave", [$this->altAddresses[2]]));

        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postSave", [$this->altAddresses[0]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postSave", [$this->altAddresses[1]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postSave", [$this->altAddresses[2]]));


    }

    public function testPreDeleteCorrectlyCalledForAllDefinedInterceptors() {

        $this->interceptorProcessor->processPreDeleteInterceptors(Address::class, $this->addresses);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preDelete", [$this->addresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preDelete", [$this->addresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preDelete", [$this->addresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preDelete", [$this->addresses[0]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preDelete", [$this->addresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preDelete", [$this->addresses[2]]));

        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("preDelete", [$this->addresses[0]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("preDelete", [$this->addresses[1]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("preDelete", [$this->addresses[2]]));


        $this->interceptorProcessor->processPreDeleteInterceptors(AltAddress::class, $this->altAddresses);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preDelete", [$this->altAddresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preDelete", [$this->altAddresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("preDelete", [$this->altAddresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preDelete", [$this->altAddresses[0]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preDelete", [$this->altAddresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("preDelete", [$this->altAddresses[2]]));

        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("preDelete", [$this->altAddresses[0]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("preDelete", [$this->altAddresses[1]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("preDelete", [$this->altAddresses[2]]));


    }

    public function testPostDeleteCorrectlyCalledForAllDefinedInterceptors() {

        $this->interceptorProcessor->processPostDeleteInterceptors(Address::class, $this->addresses);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postDelete", [$this->addresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postDelete", [$this->addresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postDelete", [$this->addresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postDelete", [$this->addresses[0]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postDelete", [$this->addresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postDelete", [$this->addresses[2]]));

        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("postDelete", [$this->addresses[0]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("postDelete", [$this->addresses[1]]));
        $this->assertFalse($this->inlineORMInterceptor->methodWasCalled("postDelete", [$this->addresses[2]]));


        $this->interceptorProcessor->processPostDeleteInterceptors(AltAddress::class, $this->altAddresses);

        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postDelete", [$this->altAddresses[0]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postDelete", [$this->altAddresses[1]]));
        $this->assertTrue($this->globalORMInterceptor->methodWasCalled("postDelete", [$this->altAddresses[2]]));

        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postDelete", [$this->altAddresses[0]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postDelete", [$this->altAddresses[1]]));
        $this->assertTrue($this->configFileORMInterceptor->methodWasCalled("postDelete", [$this->altAddresses[2]]));

        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postDelete", [$this->altAddresses[0]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postDelete", [$this->altAddresses[1]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postDelete", [$this->altAddresses[2]]));


    }


    public function testCanAddInterceptorManually() {

        $this->interceptorProcessor->addInterceptor("*", InlineORMInterceptor::class);

        $this->interceptorProcessor->processPostDeleteInterceptors(Address::class, $this->addresses);

        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postDelete", [$this->addresses[0]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postDelete", [$this->addresses[1]]));
        $this->assertTrue($this->inlineORMInterceptor->methodWasCalled("postDelete", [$this->addresses[2]]));


    }

}
