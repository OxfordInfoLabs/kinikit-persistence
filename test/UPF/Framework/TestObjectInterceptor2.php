<?php


namespace Kinikit\Persistence\UPF\Framework;


class TestObjectInterceptor2 extends UPFObjectInterceptorBase {
	
	public function __construct () {
	}
	
	public function preMap($proposedObject = null, $arrayOfFieldValues=null, $upfInstance = null) {
		TestObjectInterceptor1::$interceptorRuns [] = "TestObjectInterceptor2";
		if ( $proposedObject == "Kinikit\Persistence\UPF\Framework\Contact" ) {
			return false;
		}
		
		return $proposedObject;
	}	
	
}

?>