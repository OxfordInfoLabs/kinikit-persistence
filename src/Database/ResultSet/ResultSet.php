<?php

namespace Kinikit\Persistence\Database\ResultSet;

/**
 * Abstract Result set class
 *
 */
interface ResultSet {
	
	/**
	 * Get the list of result columns if available
	 *
	 */
	public function getColumnNames();
	
	/**
	 * Get the next record from this record set or null if no more data available.
	 *
	 */
	public function nextRow();
	
	/**
	 * Close the record set in the manner required by child information.
	 *
	 */
	public function close();
}

?>
