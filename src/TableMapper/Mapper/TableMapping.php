<?php


namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Relationship\TableRelationship;

/**
 * Encodes a mapping to a table including any nested relationships.
 *
 * Class TableMapping
 * @package Kinikit\Persistence\TableMapper\Mapper
 */
class TableMapping {

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var TableRelationship[]
     */
    private $relationships = [];


    /**
     * In use database connection for this mapping.
     *
     * @var DatabaseConnection
     */
    private $databaseConnection;


    /**
     * List of column names to use for this mapping.
     *
     * @var string[]
     */
    private $columnNames;


    /**
     * List of primary key columns to use for this mapping.
     *
     * @var string[]
     */
    private $primaryKeyColumnNames;


    /**
     * Array of relationship alias prefixes used by the table query engine.
     *
     * @var string[]
     */
    private $relationshipAliasPrefixes;

    /**
     * TableMapping constructor.
     *
     * @param string $tableName
     * @param TableRelationship[] $relationships
     */
    public function __construct($tableName, $relationships = [], $databaseConnection = null) {
        $this->tableName = $tableName;
        $this->relationships = $relationships;

        // Use built in database connection if none supplied.
        if (!$databaseConnection)
            $databaseConnection = Container::instance()->get(DatabaseConnection::class);

        $this->databaseConnection = $databaseConnection;

        if ($relationships) {
            foreach ($relationships as $relationship) {
                $relationship->setParentMapping($this);
            }
        }
    }

    /**
     * Get the database connection used for this table mapping.
     *
     * @return DatabaseConnection
     */
    public function getDatabaseConnection() {
        return $this->databaseConnection;
    }


    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * @return TableRelationship[]
     */
    public function getRelationships() {
        return $this->relationships;
    }


    /**
     * Get the primary key columns
     *
     * @return string[]
     */
    public function getPrimaryKeyColumnNames() {
        if (!$this->primaryKeyColumnNames) {
            $this->primaryKeyColumnNames = array_keys($this->databaseConnection->getTableMetaData($this->tableName)->getPrimaryKeyColumns());
        }
        return $this->primaryKeyColumnNames;
    }


    /**
     * Get all column names
     */
    public function getColumnNames() {
        if (!$this->columnNames) {
            $this->columnNames = array_keys($this->databaseConnection->getTableMetaData($this->tableName)->getColumns());
        }
        return $this->columnNames;
    }


    /**
     * @return string[]
     */
    public function getRelationshipAliasPrefixes() {
        return $this->relationshipAliasPrefixes;
    }

    /**
     * @param string[] $relationshipAliasPrefixes
     */
    public function setRelationshipAliasPrefixes($relationshipAliasPrefixes) {
        $this->relationshipAliasPrefixes = $relationshipAliasPrefixes;
    }


}
