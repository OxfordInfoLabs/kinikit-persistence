<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\SerialisableObject;

/**
 * Base class for UPF object interceptors for trapping calls to database if required.
 *
 * @author matthew
 *
 */
abstract class UPFObjectInterceptorBase extends SerialisableObject {
	
	protected $objectType;
	protected $object;
	protected $succeed;
	
	/**
	 * Get the object which this interceptor relates to.
	 *
	 * @return the $object
	 */
	public function getObject() {
		return $this->object;
	}
	
	/**
	 * Set the object which this interceptor relates to.
	 *
	 * @param $object the $object to set
	 */
	public function setObject($object) {
		$this->object = $object;
	}
	
	/**
	 * @return the $succeed
	 */
	public function getSucceed() {
		return $this->succeed;
	}
	
	/**
	 * @param $succeed the $succeed to set
	 */
	public function setSucceed($succeed) {
		$this->succeed = $succeed;
	}
	
	/**
	 * @return the $objectType
	 */
	public function getObjectType() {
		return $this->objectType;
	}
	
	/**
	 * @param $objectType the $objectType to set
	 */
	public function setObjectType($objectType) {
		$this->objectType = $objectType;
	}
	
	public function preMap($proposedObject = null, $arrayOfFieldValues = null, $upfInstance = null) {
		return true;
	}
	
	public function postMap($object = null, $upfInstance = null) {
		return true;
	}
	
	public function preSave($object = null, $upfInstance = null) {
		return true;
	}
	
	public function preDelete($object = null, $upfInstance = null) {
		return true;
	}
	
	public function postSave($object = null, $upfInstance = null) {
		return true;
	}
	
	public function postDelete($object = null, $upfInstance = null) {
		return true;
	}

}

?>