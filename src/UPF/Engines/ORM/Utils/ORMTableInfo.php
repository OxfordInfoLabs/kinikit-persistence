<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Utils;

use Kinikit\Persistence\Database\Connection\TableMetaData;

/**
 * Data transfer class which holds relevant information about an ORM table for use within the persistence engine.
 * 
 * @author mark
 *
 */
class ORMTableInfo {
	
	private $tableMetaData;
	private $fieldColumnMappings;
	
	public function __construct($tableMetaData, $fieldColumnMappings) {
		$this->tableMetaData = $tableMetaData;
		$this->fieldColumnMappings = $fieldColumnMappings;
	}
	
	/**
	 * @return TableMetaData $tableMetaData
	 */
	public function getTableMetaData() {
		return $this->tableMetaData;
	}
	
	/**
	 * @return the $fieldColumnMappings
	 */
	public function getFieldColumnMappings() {
		return $this->fieldColumnMappings;
	}
	
	/**
	 * Get the array of field column name mappings.
	 * 
	 */
	public function getFieldColumnNameMappings() {
		$nameMappings = array ();
		foreach ( $this->fieldColumnMappings as $fieldName => $column ) {
			$nameMappings [$fieldName] = $column->getName ();
		}
		
		return $nameMappings;
	}
	
	/**
	 * @param $tableMetaData the $tableMetaData to set
	 */
	public function setTableMetaData($tableMetaData) {
		$this->tableMetaData = $tableMetaData;
	}
	
	/**
	 * @param $fieldColumnMappings the $fieldColumnMappings to set
	 */
	public function setFieldColumnMappings($fieldColumnMappings) {
		$this->fieldColumnMappings = $fieldColumnMappings;
	}

}

?>