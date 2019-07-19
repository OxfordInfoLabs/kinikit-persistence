<?php

namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Exception\PrimaryKeyRowNotFoundException;
use Kinikit\Persistence\TableMapper\Exception\WrongPrimaryKeyLengthException;

/**
 * Main
 *
 * Class TableMapper
 */
class TableMapper {

    /**
     * @var string
     */
    private $tableName;


    /**
     * @var string[]
     */
    private $primaryKeyColumns;


    /**
     * @var Relationship[]
     */
    private $relationships;

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;


    /**
     * TableMapper constructor.
     *
     * @param string $tableName
     * @param mixed $primaryKeyColumns
     * @param Relationship[] $relationships
     * @param DatabaseConnection $databaseConnection
     */
    public function __construct($tableName, $primaryKeyColumns, $relationships = [], $databaseConnection = null) {
        $this->tableName = $tableName;
        $this->primaryKeyColumns = is_array($primaryKeyColumns) ? $primaryKeyColumns : [$primaryKeyColumns];
        $this->relationships = $relationships;
        $this->databaseConnection = $databaseConnection ?? Container::instance()->get(DatabaseConnection::class);
    }


    /**
     * Fetch an item by primary key from the configured table
     *
     * @param mixed $primaryKeyValue
     */
    public function fetch($primaryKeyValue) {

        // If primary key is not an array, make it so.
        if (!is_array($primaryKeyValue)) {
            $primaryKeyValue = [$primaryKeyValue];
        }

        if (sizeof($primaryKeyValue) != sizeof($this->primaryKeyColumns)) {
            throw new WrongPrimaryKeyLengthException($this->tableName, $primaryKeyValue, sizeof($this->primaryKeyColumns));
        }

        $primaryKeyClauses = [];
        foreach ($this->primaryKeyColumns as $column) {
            $primaryKeyClauses[] = $column . "=?";
        }

        $primaryKeyClause = join(" AND ", $primaryKeyClauses);

        $results = $this->doQuery("SELECT * FROM {$this->tableName} WHERE {$primaryKeyClause}", $primaryKeyValue, true);
        if (sizeof($results) > 0) {
            return $results[0];
        } else {
            throw new PrimaryKeyRowNotFoundException($this->tableName, $primaryKeyValue);
        }


    }


    /**
     * Supply a query with ? placeholders and an array of placeholder values to substitute.
     *
     * @param string $query
     * @param mixed[] $placeholderValues
     */
    public function query($query, ...$placeholderValues) {

    }

    // Actually do a query with or without results.
    private function doQuery($query, $placeholderValues, $withResults) {

        if ($withResults) {
            $result = $this->databaseConnection->queryWithResults($query, $placeholderValues);
            $rows = [];
            while ($row = $result->nextRow()) {
                $rows[] = $row;
            }
            return $rows;
        } else {
            return $this->databaseConnection->query($query, $placeholderValues);
        }

    }

}
