<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Object\SerialisableObject;

class ObjectWithId extends SerialisableObject {


	protected $id;
	private $name;
	private $age;
	private $shoeSize;

	
	public function __construct($name = null, $age = null, $shoeSize = null, $id = null) {
		$this->name = $name;
		$this->age = $age;
		$this->shoeSize = $shoeSize;
		$this->id = $id;
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
	 * @return the $age
	 */
	public function getAge() {
		return $this->age;
	}
	
	/**
	 * @return the $shoeSize
	 */
	public function getShoeSize() {
		return $this->shoeSize;
	}
	
	/**
	 * @param $name the $name to set
	 */
	public function setName($name) {
		$this->name = $name;
	}
	
	/**
	 * @param $age the $age to set
	 */
	public function setAge($age) {
		$this->age = $age;
	}
	
	/**
	 * @param $shoeSize the $shoeSize to set
	 */
	public function setShoeSize($shoeSize) {
		$this->shoeSize = $shoeSize;
	}




}

?>