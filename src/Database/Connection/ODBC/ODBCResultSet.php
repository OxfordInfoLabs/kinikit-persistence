<?php

namespace Kinikit\Persistence\Database\Connection\ODBC;
use Kinikit\Persistence\Database\Connection\ResultSet;

/**
 * ODBC Implementation of the generic result set implementation.
 *
 */
class ODBCResultSet implements ResultSet {
	
	private $results;
	
	/**
	 * Construct with an ODBC Result set
	 *
	 * @param resource $result
	 * @return ODBCResultSet
	 */
	public function __construct($results) {
		$this->results = $results;
	}
	
	/**
	 * @see ResultSet::close()
	 *
	 */
	public function close() {
		odbc_free_result ( $this->results );
	}
	
	/**
	 * Get the list of columns.  Currently not implemented.
	 *
	 */
	public function getColumnNames() {
		return null;
	}
	
	/**
	 * 
	 * Return the next row from the result set.  Convert to associative array keyed in by column.
	 * 
	 * @see ResultSet::nextRow()
	 *
	 */
	public function nextRow() {
		$array = array ();
		if (! ($cols = odbc_fetch_into ( $this->results, $result_array ))) {
			return false;
		}
		for($i = 1; $i <= $cols; $i ++) {
			$array [odbc_field_name ( $this->results, $i )] = $result_array [$i - 1];
		}
		return $array;
	}

}

?>
