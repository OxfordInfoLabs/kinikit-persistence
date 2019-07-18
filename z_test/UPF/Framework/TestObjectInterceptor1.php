<?php


namespace Kinikit\Persistence\UPF\Framework;


class TestObjectInterceptor1 extends UPFObjectInterceptorBase {

    public static $interceptorRuns = array();

    public function __construct() {
    }

    public function preMap($proposedObject = null, $arrayOfFieldValues = null, $upfInstance = null) {
        TestObjectInterceptor1::$interceptorRuns [] = "TestObjectInterceptor1";
        if ($proposedObject == "Kinikit\Persistence\UPF\Framework\Contact") {
            if ($arrayOfFieldValues["name"]) {
                if ($arrayOfFieldValues["name"] == "Mark" || $arrayOfFieldValues["name"] == "bob") {
                    return "Kinikit\Persistence\UPF\Framework\ContactVariant";
                }
            }

            return $proposedObject;
        }

        return true;
    }

    public function postMap($object = null, $upfInstance = null) {

        if ($object instanceof ContactVariant) {
            if ($object->getName() == "bob") return false;
        }

        return true;
    }

    public function preSave($object = null, $upfInstance = null) {

        if ($this->objectType == "Kinikit\Persistence\UPF\Framework\Contact" && !$object->getName()) return false;

        return true;
    }

    public function preDelete($object = null, $upfInstance = null) {


        if ($this->objectType == "Kinikit\Persistence\UPF\Framework\Contact" && $object->getName() == "bob") return false;

        return true;
    }
}

?>