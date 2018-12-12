<?php

namespace Kinikit\Persistence\UPF\Framework;


class BlockingInterceptor extends UPFObjectInterceptorBase {
	
	public function preMap($proposedObject = null, $arrayOfFieldValues = null) {
		return false;
	}
	
	public function postMap($object = null) {
		return false;
	}
	
	public function preSave($object = null) {
		return false;
	}
	
	public function preDelete($object = null) {
		return false;
	}

}

?>