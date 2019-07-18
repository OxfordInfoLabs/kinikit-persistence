<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Object\SerialisableObject;

class Address extends SerialisableObject {

	protected $id;
	private $streetAddress;
	private $city;

	public function __construct($streetAddress = null, $city = null, $id = null) {
		$this->streetAddress = $streetAddress;
		$this->city = $city;
		$this->id = $id;
	}
	/**
	 * @return the $id
	 */
	public function getId(){
	return $this->id;
	}

	/**
	 * @return the $streetAddress
	 */
	public function getStreetAddress(){
	return $this->streetAddress;
	}

	/**
	 * @return the $city
	 */
	public function getCity(){
	return $this->city;
	}

	/**
	 * @param $id the $id to set
	 */
	public function setId($id){
	$this->id = $id;
	}

	/**
	 * @param $streetAddress the $streetAddress to set
	 */
	public function setStreetAddress($streetAddress){
	$this->streetAddress = $streetAddress;
	}

	/**
	 * @param $city the $city to set
	 */
	public function setCity($city){
	$this->city = $city;
	}

}

?>