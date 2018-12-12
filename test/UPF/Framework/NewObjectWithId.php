<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Object\SerialisableObject;

class NewObjectWithId extends SerialisableObject {
	
	private $id;
	private $name;
	private $postcode;
	private $mobile;
	
	public function __construct($name = null, $postcode = null, $mobile = null, $id = null) {
		$this->id = $id;
		$this->name = $name;
		$this->postcode = $postcode;
		$this->mobile = $mobile;
	}
	
	/**
	 * @return the $id
	 */
	public function getId() {
		return $this->id;
	}
	
	/**
	 * @return the $name
	 */
	public function getName() {
		return $this->name;
	}
	
	/**
	 * @return the $postcode
	 */
	public function getPostcode() {
		return $this->postcode;
	}
	
	/**
	 * @return the $mobile
	 */
	public function getMobile() {
		return $this->mobile;
	}
	
	/**
	 * @param $id the $id to set
	 */
	public function setId($id) {
		$this->id = $id;
	}
	
	/**
	 * @param $name the $name to set
	 */
	public function setName($name) {
		$this->name = $name;
	}
	
	/**
	 * @param $postcode the $postcode to set
	 */
	public function setPostcode($postcode) {
		$this->postcode = $postcode;
	}
	
	/**
	 * @param $mobile the $mobile to set
	 */
	public function setMobile($mobile) {
		$this->mobile = $mobile;
	}

}

?>