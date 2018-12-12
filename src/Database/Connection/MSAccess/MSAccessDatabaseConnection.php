<?php

namespace Kinikit\Persistence\Database\Connection\MSAccess;
use Kinikit\Persistence\Database\Connection\ODBC\ODBCDatabaseConnection;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Connection\TableMetaData;

/**
 * Extension of ODBC Database Connection for MS Access.  Predominently to manage the extraction of
 * meta data as this is being corrupted using the odbc call.
 *
 * Class MSAccessDatabaseConnection
 */
class MSAccessDatabaseConnection extends ODBCDatabaseConnection {

    /**
     * Get Table Meta Data for the supplied table from MS Access.
     *
     * @param string $tableName
     */
    public function getTableMetaData($tableName) {

        $query = "SELECT * FROM " . $tableName . " WHERE 1 = 2";

        $results = odbc_exec($this->getUnderlyingConnection(), $query);

        // Now create the table meta data
        $columns = array();
        for ($i = 0; $i < odbc_num_fields($results); $i++) {

            $fieldType = strtolower(odbc_field_type($results, $i + 1));

            $sqlType = TableColumn::SQL_UNKNOWN;
            switch ($fieldType) {
                case "bigint":
                    $sqlType = TableColumn::SQL_BIGINT;
                    break;
                case "varchar":
                case "text":
                    $sqlType = TableColumn::SQL_VARCHAR;
                    break;
                default:
                    $sqlType = $fieldType;
            }


            $columns[odbc_field_name($results, $i + 1)] = new TableColumn(odbc_field_name($results, $i + 1), $sqlType, odbc_field_len($results, $i + 1));
        }

        return new TableMetaData($tableName, $columns);

    }


}