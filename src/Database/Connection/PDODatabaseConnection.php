<?php


namespace Kinikit\Persistence\Database\Connection;


use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\PDOPreparedStatement;
use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;
use Kinikit\Persistence\Database\ResultSet\ResultSet;

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
    public function connect(array $configParams = []): bool {

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
     * @param string $string
     * @return string
     */
    public function escapeString(string $string): string {
        return $this->connection->quote($string);
    }


    /**
     * Can't escape columns by default.  Sub classes can implement if required.
     *
     * @param string $columnName
     * @return mixed
     */
    public function escapeColumn(string $columnName): string {
        return $columnName;
    }


    /**
     * Actual do Query method, should be implemented by child implementations
     *
     * @param string $sql
     * @param BindParameter[] $parameters
     * @return ResultSet
     */
    public function doQuery(string $sql, array $parameters): ResultSet {

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

            $success = $this->executeCallableWithLogging(function () use ($statement, $parameters) {
                try {
                    foreach ($parameters as $index => $parameter) {

                        // Bind numeric values of unknown type as numbers
                        if ($parameter->getSqlType() == ResultSetColumn::SQL_UNKNOWN && is_numeric($parameter->getValue())) {
                            $parameter = new BindParameter(ResultSetColumn::SQL_INTEGER, $parameter->getValue());
                        }

                        PDOPreparedStatement::bindParameter($statement, $index + 1, $parameter);
                    }
                    return $statement->execute();
                } catch (\Exception $e) {
                    Logger::log($e);
                    Logger::log("Placeholder Values: ");
                    Logger::log(array_map(fn($parameter) => $parameter->getValue(), $parameters));
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
    public function doCreatePreparedStatement(string $sql): PreparedStatement {
        return new PDOPreparedStatement($sql, $this->connection);
    }

    /**
     * Get the last auto increment id if an insert into auto increment occurred
     *
     * @return int
     * @throws SQLException
     */
    public function getLastAutoIncrementId(): int {
        return $this->connection->lastInsertId();
    }

    /**
     * Close function if required to close the database connection.
     *
     * @throws SQLException
     */
    public function close(): void {
        $this->connection = null;
    }


}
