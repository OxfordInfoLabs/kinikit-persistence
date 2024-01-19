<?php


namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Core\Configuration\ConfigFile;
use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\Logging\Logger;
use Kinikit\Core\Util\ArrayUtils;
use Kinikit\Persistence\Database\BulkData\BulkDataManager;
use Kinikit\Persistence\Database\BulkData\DefaultBulkDataManager;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\MetaData\TableIndex;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

/**
 * Base database connection which implements common defaults for the database connection.
 *
 * It is constructed with a config key which matches a configuration (Can be left blank for default)
 * defined in the config file.
 *
 * Class BaseDatabaseConnection
 * @package Kinikit\Persistence\Database\Connection
 */
abstract class BaseDatabaseConnection implements DatabaseConnection {

    /**
     * Transaction depth variable for nested transaction management.
     *
     * @var integer
     */
    protected $transactionDepth;

    /**
     * @var string
     */
    private $logFile;


    /**
     * @var string
     */
    private $lastErrorMessage;


    /**
     * @var string[]
     */
    protected $configParams;


    /**
     * @var \Kinikit\Persistence\Database\MetaData\TableMetaData[string]
     */
    protected $cachedMetaData = [];

    /**
     * Constructor - calls connect automatically to prevent need to call connect explicitly
     *
     * BaseDatabaseConnection constructor.
     *
     * @param string $configParams
     *
     * @throws DatabaseConnectionException
     */
    public function __construct($configParams = null) {


        // If no config params, assume default database
        if (!$configParams) {
            $configParams = Configuration::instance()->getParametersMatchingPrefix("db.", true);
            if (sizeof($configParams) == 0) {
                throw new MissingDatabaseConfigurationException();
            }

        }

        $connected = $this->connect($configParams);

        if (!$connected)
            throw new DatabaseConnectionException();


        if (isset($configParams["logFile"]))
            $this->logFile = $configParams["logFile"];

        // Store the config params for later.
        $this->configParams = $configParams;

        // Connect using the filtered params.
        $this->connect($configParams);

    }

    /**
     * @return string[]
     */
    public function getConfigParams() {
        return $this->configParams;
    }


    /**
     * Default implementation of the parseSQL method - simply returns SQL intact
     *
     * @param $sql
     * @param array &$parameterValues
     * @return mixed|void
     */
    public function parseSQL($sql, &$parameterValues = []) {
        return $sql;
    }


    /**
     * Implement query method to ensure query is correctly
     * processed and then call the abstract doQuery method
     *
     * @param string $sql
     * @param mixed ...$placeholders
     * @return ResultSet
     */
    public function query($sql, ...$placeholders) {

        if (sizeof($placeholders) > 0 && is_array($placeholders[0])) {
            $placeholders = $placeholders[0];
        }

        // Parse the SQL to ensure we catch any vendor specific dialect
        $sql = $this->parseSQL($sql, $placeholders);


        return $this->doQuery($sql, $placeholders);
    }


    /**
     * Implement execute method by creating a prepared statement and
     * executing it once.
     *
     * @param $sql
     * @param mixed ...$placeholders
     * @return bool|void
     * @throws SQLException
     */
    public function execute($sql, ...$placeholders) {

        if (sizeof($placeholders) > 0 && is_array($placeholders[0])) {
            $placeholders = $placeholders[0];
        }

        // Create prepared statement for passed SQL.
        $statement = $this->createPreparedStatement($sql);

        // Execute with placeholdere
        $statement->execute($placeholders);

        // Close the statement
        $statement->close();

        return true;
    }

    /**
     * Intercept requests for create prepared statement
     * to allow for SQL rewriting if required.
     *
     * @param string $sql
     * @return PreparedStatement|void
     */
    public function createPreparedStatement($sql) {

        // Parse the SQL to ensure we catch any vendor specific dialect
        $sql = $this->parseSQL($sql);

        return $this->doCreatePreparedStatement($sql);

    }


    /**
     * Actual do Query method, should be implemented by child implementations
     *
     * @param $sql
     * @param $placeholderValues
     * @return mixed
     */
    public abstract function doQuery($sql, $placeholderValues);


    /**
     * Actual do method for creating a prepared statement.
     *
     * @param $sql
     * @return PreparedStatement
     */
    public abstract function doCreatePreparedStatement($sql);


    /**
     * Execute a script containing multiple statements terminated by ;
     * Split each statement and execute in turn.
     *
     * @param $scriptContents
     */
    public function executeScript($scriptContents) {


        $numberProcessed = 1;

        while ($numberProcessed > 0)
            $scriptContents = preg_replace("/'(.*?);(.*?)'/", "'$1||^^$2'", $scriptContents, -1, $numberProcessed);


        $splitStatements = explode(";", $scriptContents);


        foreach ($splitStatements as $statement) {

            if (trim($statement)) {

                $numberProcessed = 1;

                while ($numberProcessed > 0)
                    $statement = preg_replace("/'(.*?)\|\|\^\^(.*?)'/", "'$1;$2'", $statement, -1, $numberProcessed);

                $this->execute($statement);

            }
        }

    }


    /**
     * Log a string to the database log.
     *
     * @param string $string
     */
    public function log($string) {
        if ($this->logFile) {
            file_put_contents($this->logFile, $string . "\n", FILE_APPEND);
        }
    }

    /**
     * Executes a callable with time based logging.
     *
     * @param callable $callable
     * @param string $logString
     */
    public function executeCallableWithLogging($callable, $logString) {

        // if a log file, execute with timings and logging, otherwise run callable intact.
        if ($this->logFile) {
            $startTime = microtime(true);
            $result = $callable();
            $endTime = microtime(true);
            $this->log($logString . "\nCompleted in " . round($endTime - $startTime, 4) . " seconds");
            return $result;
        } else {
            return $callable();
        }

    }


    /**
     * Standard begin transaction logic for most systems.
     *
     * @throws SQLException
     */
    public function beginTransaction() {

        // Increase the transaction depth
        $this->transactionDepth++;

        // If not in transaction, start a transaction
        if ($this->transactionDepth == 1) {
            $this->query("BEGIN");
        } else {
            $this->query("SAVEPOINT SP" . $this->transactionDepth);
        }

    }

    /**
     * Standard implementation of commit for a transaction.
     *
     * @throws SQLException
     */
    public function commit() {

        $this->query("COMMIT");

        // Reset the transaction depth
        $this->transactionDepth = 0;

    }

    /**
     * Standard implementation of rollback for a transaction
     *
     * @param bool $wholeTransaction
     *
     * @throws SQLException
     */
    public function rollback($wholeTransaction = true) {


        if ($this->transactionDepth <= 1 || $wholeTransaction) {
            $this->query("ROLLBACK");
        } else {
            $this->query("ROLLBACK TO SAVEPOINT SP" . $this->transactionDepth);
        }

        // Decrement the transaction depth
        $this->transactionDepth = $wholeTransaction ? 0 : max(0, $this->transactionDepth - 1);

    }


    /**
     * Set the last error message
     *
     * @param string $lastErrorMessage
     */
    protected function setLastErrorMessage($lastErrorMessage) {
        $this->lastErrorMessage = $lastErrorMessage;
        $this->log("Error: $lastErrorMessage");
    }

    /**
     * Get the last error message generated by this connection
     *
     * @return string
     */
    public function getLastErrorMessage() {
        return $this->lastErrorMessage;
    }

    /**
     * @param $tableName
     * @return TableMetaData|\Kinikit\Persistence\Database\MetaData\TableMetaData|void
     */
    public function getTableMetaData($tableName) {
        if (!isset($this->cachedMetaData[$tableName])) {
            $this->cachedMetaData[$tableName] = new \Kinikit\Persistence\Database\MetaData\TableMetaData($tableName, $this->getTableColumnMetaData($tableName), $this->getTableIndexMetaData($tableName));
        }
        return $this->cachedMetaData[$tableName];
    }


    /**
     * Clear meta data cache.  Useful when installing test data
     */
    public function clearMetaDataCache() {
        $this->cachedMetaData = [];
    }


    /**
     * Get table column meta data for a given table as an associative array keyed in by column name.
     *
     * @param $tableName
     * @return \Kinikit\Persistence\Database\MetaData\TableColumn[]
     */
    public abstract function getTableColumnMetaData($tableName);


    /**
     * Get table index meta data for a given table as an associative array keyed in by index name.
     *
     * @param $tableName
     * @return TableIndex[]
     */
    public abstract function getTableIndexMetaData($tableName);

    /**
     * Get the bulk data manager for this database connection.
     * Used for Inserting, Updating and Replacing table data in bulk.
     * This is useful as different RDBMS engines do and don't support
     * use of certain operations.
     *
     * @return BulkDataManager
     */
    public function getBulkDataManager() {
        return new DefaultBulkDataManager($this);
    }


}
