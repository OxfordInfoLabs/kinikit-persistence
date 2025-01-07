<?php


namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\PreparedStatement\PDOPreparedStatement;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;

abstract class PDODatabaseConnection extends BaseDatabaseConnection {


    /**
     * The PDO connection object
     *
     * @var \PDO
     */
    protected $connection;

    /**
     * Get the underlying PDO object.
     *
     * @return \PDO
     */
    public function getPDO() {
        return $this->connection;
    }


    /**
     * Get the class type to use for the result set.
     *
     * @return string
     */
    public abstract function getResultSetClass();

    /**
     * Connect to the database.  This receives an array of normalised stripped config parameters
     * so e.g. "db.name" or "db.test.name" would be mapped to simply "name" for convenience of handling.
     *
     * @return boolean
     */
    public function connect($configParams = []) {

        try {
            $this->connection = new \PDO($configParams["dsn"], $configParams["username"] ?? null, $configParams["password"] ?? null);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            return true;
        } catch (\PDOException $e) {
            throw ($e);
            return false;
        }
    }

    /**
     * Escape a string value ready for use in a query.
     *
     * @param $string
     * @return string
     */
    public function escapeString($string) {
        return $this->connection->quote($string);
    }


    /**
     * Can't escape columns by default.  Sub classes can implement if required.
     *
     * @param $columnName
     * @return mixed
     */
    public function escapeColumn($columnName) {
        return $columnName;
    }


    /**
     * Actual do Query method, should be implemented by child implementations
     *
     * @param $sql
     * @param $placeholderValues
     * @return mixed
     */
    public function doQuery($sql, $placeholderValues) {


        if (!$this->connection) {
            throw new ConnectionClosedException();
        }

        try {
            $statement = $this->connection->prepare($sql);

        } catch (\PDOException $e) {
            $this->setLastErrorMessage($e->getMessage());
            throw new SQLException($e->getMessage(), $e->getCode() ?? 0);
        }

        if ($statement) {

            $success = $this->executeCallableWithLogging(function () use ($statement, $placeholderValues) {
                // Bind values - as numbers if numeric
                try {
                    foreach ($placeholderValues as $index => $value) {
                        $statement->bindValue($index + 1, $value, is_numeric($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
                    }
                    return $statement->execute();
                } catch (\Exception $e) {
                    Logger::log($e);
                    Logger::log("Placeholder Values: ");
                    Logger::log($placeholderValues);
                    throw new SQLException($e->getMessage());
                }
            }, $sql);

            if ($success) {
                $resultSetClass = $this->getResultSetClass();
                return new $resultSetClass($statement, $this);
            } else {
                $error = join(',', $this->connection->errorInfo());
                $this->setLastErrorMessage($error);
                throw new SQLException($error);
            }
        } else {

            $error = join(',', $this->connection->errorInfo());
            $this->setLastErrorMessage($error);
            throw new SQLException($error);
        }

    }

    /**
     * Execute a prepared statement (usually an update operation) and return a boolean according to
     * whether or not it was successful
     *
     * @param string $sql
     * @return PreparedStatement
     * @throws SQLException
     */
    public function doCreatePreparedStatement($sql) {
        return new PDOPreparedStatement($sql, $this->connection);
    }

    /**
     * Get the last auto increment id if an insert into auto increment occurred
     *
     * @return int
     * @throws SQLException
     */
    public function getLastAutoIncrementId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Close function if required to close the database connection.
     *
     * @throws SQLException
     */
    public function close() {
        $this->connection = null;
    }


}
