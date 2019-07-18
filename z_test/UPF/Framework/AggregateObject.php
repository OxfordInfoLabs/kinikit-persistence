<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\SerialisableObject;

/**
 * Test aggregate object designed to back an ORM View SQL configured object
 * 
 * @author mark
 *
 */
class AggregateObject extends SerialisableObject {
	
	private $id1;
	private $id2;
	private $name1;
	private $age;
	private $shoeSize;
	private $name2;
	private $postcode;
	private $mobile;
	
	public function __construct($id1 = null, $id2 = null, $name1 = null, $name2 = null, $age = null, $shoeSize = null, $postcode = null, $mobile = null) {
		$this->id1 = $id1;
		$this->id2 = $id2;
		$this->name1 = $name1;
		$this->name2 = $name2;
		$this->age = $age;
		$this->shoeSize = $shoeSize;
		$this->postcode = $postcode;
		$this->mobile = $mobile;
	}
	
	/**
	 * @return the $id1
	 */
	public function getId1() {
		return $this->id1;
	}
	
	/**
	 * @return the $id2
	 */
	public function getId2() {
		return $this->id2;
	}
	
	/**
	 * @return the $name1
	 */
	public function getName1() {
		return $this->name1;
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
	 * @return the $name2
	 */
	public function getName2() {
		return $this->name2;
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
	 * @param field_type $id1
	 */
	public function setId1($id1) {
		$this->id1 = $id1;
	}
	
	/**
	 * @param field_type $id2
	 */
	public function setId2($id2) {
		$this->id2 = $id2;
	}
	
	/**
	 * @param field_type $name1
	 */
	public function setName1($name1) {
		$this->name1 = $name1;
	}
	
	/**
	 * @param field_type $age
	 */
	public function setAge($age) {
		$this->age = $age;
	}
	
	/**
	 * @param field_type $shoeSize
	 */
	public function setShoeSize($shoeSize) {
		$this->shoeSize = $shoeSize;
	}
	
	/**
	 * @param field_type $name2
	 */
	public function setName2($name2) {
		$this->name2 = $name2;
	}
	
	/**
	 * @param field_type $postcode
	 */
	public function setPostcode($postcode) {
		$this->postcode = $postcode;
	}
	
	/**
	 * @param field_type $mobile
	 */
	public function setMobile($mobile) {
		$this->mobile = $mobile;
	}

}

?>