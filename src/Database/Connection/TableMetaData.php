<?php

namespace Kinikit\Persistence\Database\Connection;

/**
 * Table meta data class
 *
 */
class TableMetaData {
	
	private $tableName;
	private $columns;
	
	/**
	 * Construct the table meta data using interesting data items 
	 *
	 * @param string $tableName
	 * @param array $columns
	 * @return TableMetaData
	 */
	public function __construct($tableName, $columns) {
		$this->tableName = $tableName;
		$this->columns = $columns;
	}
	
	/**
	 * Get the columns array
	 * 
	 * @return array
	 */
	public function getColumns() {
		return $this->columns;
	}
	
	/**
	 * Get a specific column by name
	 *
	 * @param unknown_type $columnName
	 * @return TableColumn
	 */
	public function getColumn($columnName) {
		return isset ( $this->columns [$columnName] ) ? $this->columns [$columnName] : null;
	}
	
	/**
	 * Get the table name for this meta data
	 * 
	 * @return string
	 */
	public function getTableName() {
		return $this->tableName;
	}

}

?>