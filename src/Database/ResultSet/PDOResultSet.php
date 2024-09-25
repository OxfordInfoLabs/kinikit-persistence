<?php

namespace Kinikit\Persistence\Database\ResultSet;


use Kinikit\Persistence\Database\Connection\PDODatabaseConnection;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;

abstract class PDOResultSet extends BaseResultSet {

    /**
     * @var \PDOStatement
     */
    protected $statement;

    /**
     * @var PDODatabaseConnection
     */
    protected $databaseConnection;

    /**
     * Construct a result set for sqlite 3 results.
     *
     * @param \PDOStatement $statement
     * @param PDODatabaseConnection $databaseConnection
     *
     * @return PDOResultSet
     */
    public function __construct($statement, $databaseConnection) {
        $this->statement = $statement;
        $this->databaseConnection = $databaseConnection;
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
            } catch (\PDOException $e) {
            }
        }

        return $columnNames;
    }


    /**
     * Get columns
     *
     * @return ResultSetColumn[]
     */
    public abstract function getColumns();


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
