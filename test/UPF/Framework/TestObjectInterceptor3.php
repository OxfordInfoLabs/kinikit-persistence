<?php


namespace Kinikit\Persistence\UPF\Framework;


class TestObjectInterceptor3 extends UPFObjectInterceptorBase {

	public static $interceptorRuns = array ();

	public function __construct () {
	}

	public function preMap($proposedObject = null, $arrayOfFieldValues=null, $upfInstance = null) {
		TestObjectInterceptor1::$interceptorRuns [] = "TestObjectInterceptor3";
		if ( $this->objectType == "testType1" ){
			return true;
		} elseif ( $this->objectType == "testType2" ) {
			return false;
		} else {
			return false;
		}
	}

	public function postMap($object=null, $upfInstance = null) {
		if ( $this->objectType == "testType1" && $object == "testObject1" ){
			return true;
		} elseif ( $this->objectType == "testType2" && $object == "testObject2" ) {
			return true;
		} else {
			return false;
		}
	}
}

?>