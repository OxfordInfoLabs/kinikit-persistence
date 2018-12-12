<?php

namespace Kinikit\Persistence\Database\Connection\MySQL;
use Kinikit\Persistence\Database\Connection\TableColumn;

/**
 * MySQL Table Column.
 */
class MySQLTableColumn extends TableColumn {

    private $databaseConnection;

    /**
     * Column with connection
     *
     * MySQLTableColumn constructor.
     * @param MySQLDatabaseConnection $databaseConnection
     */
    public function __construct($databaseConnection, $name, $type, $length, $defaultValue = "") {
        $this->databaseConnection = $databaseConnection;
        parent::__construct($name, $type, $length, $defaultValue);
    }


    // Get the SQL Value
    public function getSQLValue($inputValue) {

        $columnNumeric = $this->isNumeric();

        // If the column is numeric do appropriate
        if ($inputValue === null) {
            $inputValue = "NULL";
        } else if ($columnNumeric) {
            if (!is_numeric($inputValue)) {
                $inputValue = "'" . $this->databaseConnection->escapeString($inputValue) . "'";
            } else if ($inputValue == '') {
                $inputValue = 0;
            }
        } else {
            $inputValue = "'" . $this->databaseConnection->escapeString($inputValue) . "'";
        }

        return $inputValue;

    }


}