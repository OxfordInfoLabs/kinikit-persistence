<?php

namespace Kinikit\Persistence\Database\Connection\MSSQL;
use Kinikit\Persistence\Database\Connection\QueryParser;

/**
 * Parser class for converting syntax for MSSQL from our standard syntax.
 */
class MSSQLQueryParser implements QueryParser {


    /**
     * Add standard rules we want to apply to passed queries to convert them to compliant SQL Server query format
     *
     * @param $sql
     *
     */
    public function parse($sql) {

        // ENABLE SUPPORT FOR MySQL style LIMIT, OFFSET syntax
        preg_match("/SELECT([\s\S]*)LIMIT\W*([0-9]*)/", $sql, $limitMatches);
        preg_match("/SELECT([\s\S]*)OFFSET\W*([0-9]*)/", $sql, $offsetMatches);

        $limit = sizeof($limitMatches) == 3 ? $limitMatches[2] : 0;
        $offset = sizeof($offsetMatches) == 3 ? $offsetMatches[2] : 0;

        if ($limit || $offset) {

            $newSQL = "";

            $queryPart = trim($limit ? $limitMatches[1] : $offsetMatches[1]);

            if (substr(strtolower($queryPart), 0, 8) == "distinct") {
                $distinct = true;
                $queryPart = substr($queryPart, 8);
            } else {
                $distinct = false;
            }


            $queryPart = preg_replace("/([\s\S]*)(ORDER\W*BY\W*)(.*)/", "row_number() OVER (ORDER BY $3) row,$1", $queryPart);

            // Replace any text conversions to varchar for except handling.
            $queryPart = str_replace("CONVERT (text", "CONVERT (varchar(5000)", $queryPart);


            if ($offset) {
                $newSQL = " EXCEPT SELECT " . ($distinct ? "DISTINCT " : "") . "TOP " . $offset . " " . $queryPart;
            }

            if ($limit) {
                $newSQL = "SELECT " . ($distinct ? "DISTINCT " : "") . "TOP " . ($limit + $offset) . " " . $queryPart . $newSQL;
            } else {
                $newSQL = "SELECT " . $queryPart . $newSQL;
            }

            if (strpos($queryPart, "row_number"))
                $newSQL = $newSQL . " ORDER BY row ";

            $sql = $newSQL;
        }

        return trim($sql);

    }
}
