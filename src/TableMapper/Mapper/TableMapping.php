<?php


namespace Kinikit\Persistence\TableMapper\Mapper;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\DDL\SQLValidator;
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
     * @var string
     */
    private $autoIncrementPkColumn;

    /**
     * TableMapping constructor.
     *
     * @param string $tableName
     * @param TableRelationship[] $relationships
     */
    public function __construct($tableName, $relationships = [], $databaseConnection = null, $primaryKeyColumnNames = null) {
        $this->tableName = SQLValidator::validateTableName($tableName);

        // Use built in database connection if none supplied.
        if (!$databaseConnection)
            $databaseConnection = Container::instance()->get(DatabaseConnection::class);

        $this->databaseConnection = $databaseConnection;

        if ($relationships) {
            $this->setRelationships($relationships);
        }

        $this->primaryKeyColumnNames = $primaryKeyColumnNames;
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
     * @param TableRelationship[] $relationships
     */
    public function setRelationships($relationships) {
        if ($relationships) {
            foreach ($relationships as $relationship) {
                $relationship->setParentMapping($this);
            }
        }

        $this->relationships = $relationships;
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
            try {
                $this->primaryKeyColumnNames = array_keys($this->databaseConnection->getTableMetaData($this->tableName)->getPrimaryKeyColumns());
            } catch (\Exception $e) {
                return [];
            }
        }
        return $this->primaryKeyColumnNames;
    }


    /**
     * Get the primary key values from a data row using column names.
     */
    public function getPrimaryKeyValues($dataRow) {
        $pkValues = [];
        foreach ($this->getPrimaryKeyColumnNames() as $columnName) {
            if (isset($dataRow[$columnName]))
                $pkValues[$columnName] = $dataRow[$columnName];
        }

        return $pkValues;
    }


    /**
     * Return a boolean indicating whether or not this has auto increment PK.
     *
     * @return bool
     */
    public function getAutoIncrementPk() {
        if (!$this->autoIncrementPkColumn) {
            $pkColumns = $this->databaseConnection->getTableMetaData($this->tableName)->getPrimaryKeyColumns();
            foreach ($pkColumns as $pkColumn) {
                if ($pkColumn->isAutoIncrement())
                    $this->autoIncrementPkColumn = $pkColumn->getName();
            }

        }
        return $this->autoIncrementPkColumn;
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


}
