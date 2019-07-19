<?php

namespace Kinikit\Persistence\Database\Connection\MSSQL;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

/**
 * Extension of the Result Set entity
 */
class MSSQLResultSet implements ResultSet {

    private $results;
    private $driver;
    private $columnTypes;
    private $columnNames;

    public function __construct($results, $driver = MSSQLDatabaseConnection::DRIVER_SYBASE) {
        $this->driver = $driver;
        $this->results = $results;
        $this->calculateResultMetaData();
    }

    /**
     * Get the list of columns
     */
    public function getColumnNames() {
        return $this->columnNames;
    }

    /**
     * Get the next record from this record set or null if no more data
     * available.
     */
    public function nextRow() {

        if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE) {
            $data = mssql_fetch_assoc($this->results);
        } else
            $data = sqlsrv_fetch_array($this->results, SQLSRV_FETCH_ASSOC);

        if ($data && is_array($data)) {

            // Loop through each item, converting to UTF format if required
            foreach ($data as $key => $value) {
                $convert = (isset ($this->columnTypes [$key]) && ($this->columnTypes [$key] != "blob") && ($this->columnTypes [$key] != "image"));

                if ($convert) {
                    $data [$key] = mb_detect_encoding($data [$key], mb_detect_order(), true) === 'UTF-8' ? $data [$key] : mb_convert_encoding($data [$key], 'UTF-8');
                }

                if ($value === "\0")
                    $data [$key] = null;

                if ($value instanceof \DateTime) {
                    $format = $this->columnTypes[$key] == "datetime" ? "Y-m-d H:i" : "Y-m-d";
                    $data[$key] = $value->format($format);
                } 

            }
        }


        return $data;

    }

    /**
     * Close the record set in the manner required by child information.
     */
    public function close() {
        // Nothing required here
    }

    // Calculate result meta data if required
    private function calculateResultMetaData() {

        if (!$this->columnNames) {
            $this->columnNames = array();
            $this->columnTypes = array();


            if ($this->driver == MSSQLDatabaseConnection::DRIVER_SYBASE) {
                for ($i = 0; $i < mssql_num_fields($this->results); $i++) {

                    $field = mssql_fetch_field($this->results, $i);
                    $this->columnNames [] = $field->name;
                    $this->columnTypes [$field->name] = $field->type;
                }
            } else {

                $metaData = sqlsrv_field_metadata($this->results);
                foreach ($metaData as $field) {
                    $this->columnNames[] = $field["Name"];
                    $this->columnTypes[] = $field["Type"];
                }

            }

        }
    }

}

?>
