<?php


namespace Kinikit\Persistence\Database\Vendors\SQLite3;


use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\ResultSet\PDOResultSet;


class SQLite3ResultSet extends PDOResultSet {

    /**
     * Return columns
     *
     * @return ResultSetColumn[]
     */
    public function getColumns() {

        $columns = [];

        $statement = $this->statement;

        // If at least one column, check that we have meta
        if ($statement->columnCount() > 0) {
            try {
                $statement->getColumnMeta(0);
            } catch (\PDOException $e) {

                $queryString = explode("LIMIT", $statement->queryString)[0];
                $queryString = explode("OFFSET", $queryString)[0];

                $query = $queryString . " UNION SELECT NULL" . str_repeat(",NULL", $statement->columnCount() - 1);
                $results = $this->databaseConnection->query($query);
                return $results->getColumns();
            }
        }

        // Loop through the column meta data and extract bits
        for ($i = 0; $i < $statement->columnCount(); $i++) {

            // Get column meta from statement
            $meta = $statement->getColumnMeta($i);

            // Pull off the DECLARED type of format e.g. VARCHAR(200) or DECIMAL(5,3)
            $fullType = $meta["sqlite:decl_type"] ?? "VARCHAR";
            $exploded = explode("(", $fullType);

            // Type is LHS of explosion
            $type = trim($exploded[0]);
            $length = null;
            $precision = null;

            // Derive length and precision if required.
            if (sizeof($exploded) > 1) {
                $additional = explode(",", trim($exploded[1], ") "));
                $length = $additional[0];
                $precision = $additional[1] ?? null;
            }

            $columns[] = new ResultSetColumn($meta["name"], $type, $length, $precision);
        }

        return $columns;
    }
}