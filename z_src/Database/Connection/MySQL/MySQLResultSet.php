<?php

namespace Kinikit\Persistence\Database\Connection\MySQL;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

/**
 * Extension of the Result Set entity
 *
 */
class MySQLResultSet implements ResultSet {

    private $results;

    public function __construct($results) {
        $this->results = $results;
    }

    /**
     * Get the list of columns
     *
     */
    public function getColumnNames() {
        $columns = $this->results->fetch_fields();
        $columnNames = array();
        foreach ($columns as $column) {
            $columnNames [] = $column->name;
        }
        return $columnNames;
    }

    /**
     * Get the next record from this record set or null if no more data available.
     *
     */
    public function nextRow() {
        $assoc = $this->results->fetch_assoc();
        return $assoc;
    }

    /**
     * Close the record set in the manner required by child information.
     *
     */
    public function close() {
        // Nothing required here
    }

}

?>
