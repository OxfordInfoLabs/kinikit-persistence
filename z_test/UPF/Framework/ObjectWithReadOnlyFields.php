<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\SerialisableObject;

/**
 * Test object with some fields read only
 */
class ObjectWithReadOnlyFields extends SerialisableObject {

	protected $id;
	private $name;

	private $applicationName;
	private $applicationVersion;


	public function __construct($name = null, $applicationName = null, $applicationVersion = null, $id = null) {
		$this->name = $name;
		$this->applicationName = $applicationName;
		$this->applicationVersion = $applicationVersion;
		$this->id = $id;
	}

	/**
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param mixed $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * @return mixed
	 */
	public function getApplicationName() {
		return $this->applicationName;
	}

	/**
	 * @param mixed $applicationName
	 */
	public function setApplicationName($applicationName) {
		$this->applicationName = $applicationName;
	}

	/**
	 * @return mixed
	 */
	public function getApplicationVersion() {
		return $this->applicationVersion;
	}

	/**
	 * @param mixed $applicationVersion
	 */
	public function setApplicationVersion($applicationVersion) {
		$this->applicationVersion = $applicationVersion;
	}


}