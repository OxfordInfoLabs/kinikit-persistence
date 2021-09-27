<?php

namespace Kinikit\Persistence\Database\ResultSet;


use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\Exception\SQLException;

class PDOResultSet extends BaseResultSet {

    private $statement;

    /**
     * Construct a result set for sqlite 3 results.
     *
     * @param \PDOStatement $statement
     *
     * @return PDOResultSet
     */
    public function __construct($statement) {
        $this->statement = $statement;
    }

    /**
     * @see ResultSet::close()
     *
     */
    public function close() {
        $this->statement->closeCursor();
    }

    /**
     * @see ResultSet::getColumnNames()
     *
     */
    public function getColumnNames() {

        $columnNames = array();
        for ($i = 0; $i < $this->statement->columnCount(); $i++) {
            try {
                $columnMeta = $this->statement->getColumnMeta($i);
                $columnNames [] = $columnMeta ["name"];
            } catch(\PDOException $e){
            }
        }

        return $columnNames;
    }

    /**
     * @see ResultSet::nextRow()
     *
     */
    public function nextRow() {
        $row = $this->statement->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        } else
            $this->close();
    }

}

?>
