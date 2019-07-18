<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\DynamicSerialisableObject;

class DynamicLockableObject extends DynamicSerialisableObject {
	
	private $id;
	private $name;
	private $address;
	
	public function __construct($name = null, $address = null, $id = null) {
		$this->id = $id;
		$this->address = $address;
		$this->name = $name;
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
	 * @return the $address
	 */
	public function getAddress() {
		return $this->address;
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
	 * @param $address the $address to set
	 */
	public function setAddress($address) {
		$this->address = $address;
	}

}

?>