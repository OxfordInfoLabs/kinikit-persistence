<?php

namespace Kinikit\Persistence\ORM\Interceptor;

use Kiniauth\Services\Security\ActiveRecordInterceptor;
use Kinikit\Core\Configuration\ConfigFile;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Reflection\ClassInspectorProvider;

/**
 * Process ORM interceptors for a given class.
 *
 * Class ORMInterceptorProcessor
 * @noProxy
 */
class ORMInterceptorProcessor {

    /**
     * @var ClassInspectorProvider
     */
    private $classInspectorProvider;

    /**
     * Array of interceptor objects keyed in by pattern.
     *
     * @var ORMInterceptor[string]
     */
    private $interceptorsByClassNamePattern = [];


    /**
     * Cached array of all interceptors defined by class
     *
     * @var ORMInterceptor[string]
     */
    private $interceptorsByClass = [];


    /**
     * @var bool
     */
    private $enabled = true;


    /**
     * Construct with injected dependencies
     *
     * ORMInterceptorProcessor constructor.
     * @param ClassInspectorProvider $classInspectorProvider
     */
    public function __construct($classInspectorProvider) {
        $this->classInspectorProvider = $classInspectorProvider;
        $this->loadConfiguredInterceptors();
    }

    /**
     * Process all post map interceptors for a supplied class and array of objects
     *
     * @param string $className
     * @param mixed[] $objects
     */
    public function processPostMapInterceptors($className, $objects) {

        if (!$this->enabled) return $objects;

        $interceptors = $this->getInterceptorsForClass($className);

        $returnObjects = [];
        foreach ($objects as $key => $object) {
            $map = true;
            foreach ($interceptors as $interceptor) {
                if (!$interceptor->postMap($object)) {
                    $map = false;
                    break;
                }
            }

            if ($map) $returnObjects[$key] = $object;

        }

        return $returnObjects;


    }


    /**
     * Process all pre save interceptors for a supplied class and array of objects
     *
     * @param string $className
     * @param mixed[] $objects
     */
    public function processPreSaveInterceptors($className, $objects, $databaseConnection = null) {

        if (!$this->enabled) return;

        $interceptors = $this->getInterceptorsForClass($className);
        foreach ($objects as $object) {
            foreach ($interceptors as $interceptor) {
                if (is_a($interceptor, ActiveRecordInterceptor::class)) {
                    $interceptor->setDatabaseConnection($databaseConnection);
                }
                $interceptor->preSave($object);
            }
        }
    }


    /**
     * Process all post save interceptors for a supplied class and array of objects
     *
     * @param string $className
     * @param mixed[] $objects
     */
    public function processPostSaveInterceptors($className, $objects) {

        if (!$this->enabled) return;


        $interceptors = $this->getInterceptorsForClass($className);
        foreach ($objects as $object) {
            foreach ($interceptors as $interceptor) {
                $interceptor->postSave($object);
            }
        }
    }

    /**
     * Process all pre delete interceptors for a supplied class and array of objects
     *
     * @param string $className
     * @param mixed[] $objects
     */
    public function processPreDeleteInterceptors($className, $objects) {
        $interceptors = $this->getInterceptorsForClass($className);
        foreach ($objects as $object) {
            foreach ($interceptors as $interceptor) {
                $interceptor->preDelete($object);
            }
        }
    }


    /**
     * Process all post delete interceptors for a supplied class and array of objects
     *
     * @param string $className
     * @param mixed[] $objects
     */
    public function processPostDeleteInterceptors($className, $objects) {

        if (!$this->enabled) return;


        $interceptors = $this->getInterceptorsForClass($className);
        foreach ($objects as $object) {
            foreach ($interceptors as $interceptor) {
                $interceptor->postDelete($object);
            }
        }
    }


    /**
     * Add another interceptor matching a class pattern for an interceptor name.
     *
     * @param $classPattern
     * @param $interceptorClassName
     */
    public function addInterceptor($classPattern, $interceptorClassName) {

        // Reset interceptors by class to ensure we don't have caching issues.
        $this->interceptorsByClass = [];

        if (!isset($this->interceptorsByClassNamePattern[$classPattern]))
            $this->interceptorsByClassNamePattern[$classPattern] = [];
        else if (!is_array($this->interceptorsByClassNamePattern[$classPattern]))
            $this->interceptorsByClassNamePattern[$classPattern] = [$this->interceptorsByClassNamePattern[$classPattern]];
        $this->interceptorsByClassNamePattern[$classPattern][] = $interceptorClassName;


    }

    /**
     * Enable or disable interceptors - used by the install test data functionality
     *
     * @param bool $enabled
     */
    public function setEnabled($enabled) {
        $this->enabled = $enabled;
    }


    // Get all interceptors defined for the passed class.
    private function getInterceptorsForClass($className) {

        if (!isset($this->interceptorsByClass[$className])) {
            $interceptors = [];

            // Grab the global configured ones
            foreach ($this->interceptorsByClassNamePattern as $pattern => $interceptorClasses) {

                if (!is_array($interceptorClasses)) $interceptorClasses = [$interceptorClasses];

                foreach ($interceptorClasses as $interceptorClass) {
                    $classPattern = str_replace(["*", "/", "\\"], [".*?", "\\/", "\\\\"], ltrim($pattern, "/"));
                    if (($pattern == $className) || preg_match("/^" . $classPattern . "$/", $className)) {
                        $interceptors[] = Container::instance()->get($interceptorClass);
                    }
                }
            }

            // Grab any inline attribute ones
            $classAnnotations = $this->classInspectorProvider->getClassInspector($className)->getClassAnnotations();
            $inlineInterceptors = $classAnnotations["interceptor"] ?? [];
            foreach ($inlineInterceptors as $inlineInterceptor) {
                $interceptors[] = Container::instance()->get($inlineInterceptor->getValue());
            }

            $this->interceptorsByClass[$className] = $interceptors;


        }

        return $this->interceptorsByClass[$className];

    }

    // Load configured interceptors.
    private function loadConfiguredInterceptors() {
        $configFile = new ConfigFile("Config/orminterceptors.txt");
        $this->interceptorsByClassNamePattern = $configFile->getAllParameters();
    }

}
