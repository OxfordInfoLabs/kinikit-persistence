<?php

namespace Kinikit\Persistence\Database\Connection\SQLite3;

use Kinikit\Persistence\Database\Connection\ResultSet;

class SQLite3ResultSet implements ResultSet {

    private $results;
    private $columnArray;

    /**
     * Construct a result set for sqlite 3 results.
     *
     * @return SQLite3ResultSet
     */
    public function __construct($results) {
        $this->results = $results;

        // Bind columns to column array
        for ($i = 0; $i < $this->results->columnCount(); $i++) {
            $columnMeta = $this->results->getColumnMeta($i);
            $pdoType = $columnMeta ["pdo_type"];
            $name = $columnMeta ["name"];
            $this->results->bindColumn($i + 1, $this->columnArray [$name], $pdoType);
        }

    }

    /**
     * @see ResultSet::close()
     *
     */
    public function close() {
        $this->results->closeCursor();
    }

    /**
     * @see ResultSet::getColumnNames()
     *
     */
    public function getColumnNames() {
        $columnNames = array();
        for ($i = 0; $i < $this->results->columnCount(); $i++) {
            $columnMeta = $this->results->getColumnMeta($i);
            $columnNames [] = $columnMeta ["name"];
        }
        return $columnNames;
    }

    /**
     * @see ResultSet::nextRow()
     *
     */
    public function nextRow() {
        $results = $this->results->fetch();

        if ($results)
            return $this->columnArray;

        else
            $this->close();
    }

}

?>