<?php


namespace Kinikit\Persistence\Database\ResultSet;


abstract class BaseResultSet implements ResultSet {

    private $results;

    /**
     * Generic fetch all method, simply calls next until all rows have been collected.
     * The results are cached to allow for multiple calling.
     *
     * @return \mixed[][]
     */
    public function fetchAll() {

        if (!$this->results) {

            $this->results = [];
            while ($row = $this->nextRow()) {
                $this->results[] = $row;
            }

        }

        return $this->results;
    }


}
