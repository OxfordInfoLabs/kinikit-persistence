<?php

namespace Kinikit\Persistence\Database\Connection\MSSQL;

use Kinikit\Persistence\Database\Connection\TableColumn;

class MSSQLTableColumn extends TableColumn {
	
	private $primaryKey;
	
	public function __construct($name, $type, $length, $primaryKey = false) {
		parent::__construct ( $name, $type, $length );
		$this->setPrimaryKey ( $primaryKey );
	}
	
	/**
	 * @return the $primaryKey
	 */
	public function getPrimaryKey() {
		return $this->primaryKey;
	}
	
	/**
	 * @param field_type $primaryKey
	 */
	public function setPrimaryKey($primaryKey) {
		$this->primaryKey = $primaryKey;
	}

}

?>