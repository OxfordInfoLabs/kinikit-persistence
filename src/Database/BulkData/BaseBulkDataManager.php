<?php


namespace Kinikit\Persistence\Database\BulkData;


use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;

/**
 * Ensure we have the
 *
 * Class BaseBulkDataManager
 * @package Kinikit\Persistence\Database\BulkData
 */
abstract class BaseBulkDataManager implements BulkDataManager {

    /**
     * Batch size, used where required.
     *
     * @var int
     */
    protected $batchSize = self::DEFAULT_BATCH_SIZE;

    /**
     * @var DatabaseConnection
     */
    protected $databaseConnection;

    /**
     * Cached prepared statements for repeat running.  One per type.
     *
     * @var PreparedStatement[]
     */
    private $preparedStatements = [];


    const DEFAULT_BATCH_SIZE = 50;


    /**
     * Construct with a database connection
     *
     * BaseBulkDataManager constructor.
     * @param DatabaseConnection $databaseConnection
     */
    public function __construct($databaseConnection) {
        $this->databaseConnection = $databaseConnection;
    }

    /**
     * Implementation of set batch size
     *
     * @param int $batchSize
     */
    public function setBatchSize($batchSize) {
        $this->batchSize = $batchSize;
    }

    /**
     * Implement insert to do standard cleaning and defer to doInsert on child implementation.
     *
     * @param string $tableName
     * @param \mixed[][] $rows
     * @param null $insertColumns
     */
    public function insert($tableName, $rows, $insertColumns = null) {

        // Exit if nothing to do.
        if (sizeof($rows) == 0) {
            return;
        }

        // Normalise single row sets
        if (!isset($rows[0])) {
            $rows = [$rows];
        }

        // Gather insert columns
        if (is_null($insertColumns)) {
            $insertColumns = array_keys($rows[0]);
        }


        $this->doInsert($tableName, $rows, $insertColumns);
    }

    /**
     * Implement update to do standard cleaning and defer to doUpdate on child implementation.
     *
     * @param string $tableName
     * @param \mixed[][] $rows
     * @param null $updateColumns
     * @param null $matchColumns
     */
    public function update($tableName, $rows, $updateColumns = null, $matchColumns = null) {

        // Exit if nothing to do.
        if (sizeof($rows) == 0) {
            return;
        }

        // Normalise single row sets
        if (!isset($rows[0])) {
            $rows = [$rows];
        }

        if (is_null($matchColumns)) {
            $metaData = $this->databaseConnection->getTableMetaData($tableName);
            $matchColumns = array_keys($metaData->getPrimaryKeyColumns());
        }

        // Gather insert columns
        if (is_null($updateColumns)) {
            $updateColumns = array_diff(array_keys($rows[0]), $matchColumns);
        }

        $this->doUpdate($tableName, $rows, $updateColumns, $matchColumns);
    }

    /**
     * Implement update to do standard cleaning and defer to doReplace on child implementation
     *
     * @param string $tableName
     * @param \mixed[][] $rows
     * @param null $replaceColumns
     */
    public function replace($tableName, $rows, $replaceColumns = null) {

        // Exit if nothing to do.
        if (sizeof($rows) == 0) {
            return;
        }

        // Normalise single row sets
        if (!isset($rows[0])) {
            $rows = [$rows];
        }

        // Gather insert columns
        if (is_null($replaceColumns)) {
            $replaceColumns = array_keys($rows[0]);
        }


        $this->doReplace($tableName, $rows, $replaceColumns);
    }


    /**
     * Implement delete to do standard cleaning and defer to doDelete on child implementation
     *
     * @param string $tableName
     * @param string[] $pkValues
     * @param null $matchColumns
     */
    public function delete($tableName, $pkValues, $matchColumns = null) {

        // Exit if nothing to do.
        if (sizeof($pkValues) == 0) {
            return;
        }

        if (is_null($matchColumns)) {
            $metaData = $this->databaseConnection->getTableMetaData($tableName);
            $matchColumns = array_keys($metaData->getPrimaryKeyColumns());
        }


        $this->doDelete($tableName, $pkValues, $matchColumns);
    }


    /**
     * Do insert implemented by child.
     *
     * @param $tableName
     * @param $rows
     * @param null $insertColumns
     * @return mixed
     */
    public abstract function doInsert($tableName, $rows, $insertColumns);


    /**
     * Do update implemented by child.
     *
     * @param string $tableName
     * @param \mixed[][] $rows
     * @param null $updateColumns
     * @param null $matchColumns
     */
    public abstract function doUpdate($tableName, $rows, $updateColumns, $matchColumns);


    /**
     * Do the replace, implemented by child
     *
     * @param $tableName
     * @param $rows
     * @param $replaceColumns
     * @return mixed
     */
    public abstract function doReplace($tableName, $rows, $replaceColumns);


    /**
     * Do the delete, implemented by child.
     *
     * @param $tableName
     * @param $pkValues
     * @param $matchColumns
     * @return mixed
     */
    public abstract function doDelete($tableName, $pkValues, $matchColumns);

    /**
     * Get a prepared statement with operation key.
     *
     * @param $operationKey
     * @param $statementSQL
     *
     * @return PreparedStatement
     */
    protected function getPreparedStatement($operationKey, $statementSQL) {

        if (!isset($this->preparedStatements[$operationKey][$statementSQL])) {
            $this->preparedStatements[$operationKey] = [];
            $this->preparedStatements[$operationKey][$statementSQL] = $this->databaseConnection->createPreparedStatement($statementSQL);
        }
        return $this->preparedStatements[$operationKey][$statementSQL];
    }


    /**
     * Close any prepared statements which have been opened.
     *
     * @return mixed|void
     */
    public function cleanup() {
        foreach ($this->preparedStatements as $preparedStatement) {
            $preparedStatement->close();
        }
        $this->preparedStatements = null;
    }


}
