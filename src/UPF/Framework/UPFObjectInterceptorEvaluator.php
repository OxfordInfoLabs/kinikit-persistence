<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\SerialisableObject;
use Kinikit\Persistence\UPF\Exception\InvalidObjectInterceptorException;

/**
 * Worker class to evaluate any UPF object interceptors which are defined for a given object.
 *
 * @author matthew
 *
 */
class UPFObjectInterceptorEvaluator extends SerialisableObject {

    private $interceptors;
    private $objectType;

    public function __construct($objectType = null, $interceptors = array()) {
        $this->objectType = $objectType;
        $this->setInterceptors($interceptors);
    }


    /**
     * @return the $interceptors
     */
    public function getInterceptors() {
        return $this->interceptors;
    }

    /**
     * @return the $objectType
     */
    public function getObjectType() {
        return $this->objectType;
    }

    /**
     * @param $interceptors the $interceptors to set
     */
    public function setInterceptors($interceptors = array()) {

        // Handle single objects (convert to arrays)
        if ($interceptors && !is_array($interceptors)) {
            $interceptors = array($interceptors);
        }

        foreach ($interceptors as $interceptor) {
            if (!($interceptor instanceof UPFObjectInterceptorBase)) throw new InvalidObjectInterceptorException (get_class($interceptor));
            $interceptor->setObjectType($this->objectType);
        }

        $this->interceptors = $interceptors;
    }

    /**
     * Evaluate all interceptors defined for a particular object type and method.
     * Return a boolean indicating whether all were successful or not.
     *
     * @param string $objectName
     * @return boolean
     */
    public function evaluateInterceptorsForPreMap($proposedObject = null, $arrayOfFieldData = null, $persistenceCoordinator = null) {

        // Return if no interceptors defined.
        if (!$this->interceptors) return $proposedObject;

        $upfInstance = $persistenceCoordinator ? new UPF($persistenceCoordinator) : UPF::instance();
        
        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->getObjectType() === $this->objectType) {
                $result = $interceptor->preMap($proposedObject, $arrayOfFieldData, $upfInstance);
                if ($result === true) continue; else if ($result === false) return false; else
                    $proposedObject = $result;
            }
        }
        return $proposedObject;
    }

    /**
     * Evaluate all interceptors defined for a particular object type and method.
     * Return a boolean indicating whether all were successful or not.
     *
     * @param string $objectName
     * @return boolean
     */
    public function evaluateInterceptorsForPostMap($object = null, $persistenceCoordinator = null) {

        // Return if no interceptors defined.
        if (!$this->interceptors) return true;

        $upfInstance = $persistenceCoordinator ? new UPF($persistenceCoordinator) : UPF::instance();


        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->getObjectType() == $this->objectType) {
                $result = $interceptor->postMap($object, $upfInstance);
                if ($result === true) continue;
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate all interceptors defined for a particular object type and method.
     * Return a boolean indicating whether all were successful or not.
     *
     * @param string $objectName
     * @return boolean
     */
    public function evaluateInterceptorsForPreSave($object = null, $persistenceCoordinator = null) {

        // Return if no interceptors defined.
        if (!$this->interceptors) return true;

        $upfInstance = $persistenceCoordinator ? new UPF($persistenceCoordinator) : UPF::instance();


        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->getObjectType() === $this->objectType) {
                $result = $interceptor->preSave($object, $upfInstance);
                if ($result === true) continue;
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate all interceptors defined for a particular object type and method.
     * Return a boolean indicating whether all were successful or not.
     *
     * @param string $objectName
     * @return boolean
     */
    public function evaluateInterceptorsForPostSave($object = null, $persistenceCoordinator = null) {

        // Return if no interceptors defined.
        if (!$this->interceptors) return true;

        $upfInstance = $persistenceCoordinator ? new UPF($persistenceCoordinator) : UPF::instance();


        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->getObjectType() === $this->objectType) {
                $result = $interceptor->postSave($object, $upfInstance);
                if ($result === true) continue;
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate all interceptors defined for a particular object type and method.
     * Return a boolean indicating whether all were successful or not.
     *
     * @param string $objectName
     * @return boolean
     */
    public function evaluateInterceptorsForPreDelete($object = null, $persistenceCoordinator = null) {

        // Return if no interceptors defined.
        if (!$this->interceptors) return true;

        $upfInstance = $persistenceCoordinator ? new UPF($persistenceCoordinator) : UPF::instance();


        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->getObjectType() === $this->objectType) {
                $result = $interceptor->preDelete($object, $upfInstance);
                if ($result === true) continue;
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate all interceptors defined for a particular object type and method.
     * Return a boolean indicating whether all were successful or not.
     *
     * @param string $objectName
     * @return boolean
     */
    public function evaluateInterceptorsForPostDelete($object = null, $persistenceCoordinator = null) {

        // Return if no interceptors defined.
        if (!$this->interceptors) return true;

        $upfInstance = $persistenceCoordinator ? new UPF($persistenceCoordinator) : UPF::instance();


        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->getObjectType() === $this->objectType) {
                $result = $interceptor->postDelete($object, $upfInstance);
                if ($result === true) continue;
                return false;
            }
        }
        return true;
    }

}

?>