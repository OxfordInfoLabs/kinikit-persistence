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
     * Fetch all data for all rows and return as an array of arrays
     * where each item represents a row.
     *
     * @return mixed[][]
     */
    public function fetchAll();

    /**
     * Close the record set in the manner required by child information.
     *
     */
    public function close();
}

?>
