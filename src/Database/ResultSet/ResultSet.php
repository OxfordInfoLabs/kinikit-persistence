<?php

namespace Kinikit\Persistence\Database\ResultSet;

use Kinikit\Persistence\Database\MetaData\ResultSetColumn;

/**
 * Abstract Result set class
 *
 */
interface ResultSet {

    /**
     * Get the list of result column names if available
     *
     */
    public function getColumnNames();


    /**
     * Get an array of result set column objects for each column returned in this result set
     *
     * @return ResultSetColumn[]
     */
    public function getColumns();


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
