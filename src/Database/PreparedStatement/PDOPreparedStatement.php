<?php


namespace Kinikit\Persistence\Database\PreparedStatement;


use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\Database\MetaData\ResultSetColumn;

class PDOPreparedStatement extends BasePreparedStatement {

    /**
     * @var \PDOStatement
     */
    private $statement;


    /**
     * @var integer $boundValues
     */
    private $boundValues;

    /**
     * PDOPreparedStatement constructor.
     *
     * @param string $sql
     * @param \PDO $pdo
     */
    public function __construct($sql, $pdo) {

        // Construct with SQL
        parent::__construct($sql);

        try {
            $this->statement = $pdo->prepare($sql);
            $this->boundValues = substr_count($sql, "?");

        } catch (\PDOException $e) {
            throw new SQLException($e->getMessage());
        }
    }


    /**
     * Execute this statement for an array of parameter values matching ? in SQL.
     * Values may be supplied as bind parameters or as raw values.
     *
     * @param BindParameter[]|mixed[] $parameterValues
     * @return void
     *
     * @throws SQLException
     * @throws WrongNumberOfPreparedStatementParametersException
     */
    public function execute($parameterValues): void {

        // If mismatch of parameter values, throw.
        if (sizeof($parameterValues) != $this->boundValues) {
            throw new WrongNumberOfPreparedStatementParametersException($this->boundValues, sizeof($parameterValues));
        }

        // Bind each parameter value
        foreach (BindParameter::createAll($parameterValues) as $index => $parameter) {
            self::bindParameter($this->statement, $index + 1, $parameter);
        }

        try {
            $this->statement->execute();
        } catch (\PDOException $e) {
            throw new SQLException($e->getMessage(), $e->getCode());
        }
    }


    /**
     * Bind a single bind parameter to a PDO statement at the supplied (1-based) position,
     * using the SQL type of the parameter to determine the PDO parameter type.
     *
     * @param \PDOStatement $statement
     * @param int $position
     * @param BindParameter $parameter
     */
    public static function bindParameter(\PDOStatement $statement, int $position, BindParameter $parameter): void {

        $value = $parameter->getValue();

        if ($value === null) {
            $statement->bindValue($position, null, \PDO::PARAM_NULL);
        } else if ($value instanceof BlobWrapper) {
            if ($value->getContentFileName()) {
                $statement->bindValue($position, fopen($value->getContentFileName(), "r"), \PDO::PARAM_LOB);
            } else {
                $statement->bindValue($position, $value->getContentText(), \PDO::PARAM_LOB);
            }
        } else if (is_array($value)) {
            $statement->bindValue($position, json_encode($value));
        } else if (is_bool($value)) {
            $statement->bindValue($position, $value, \PDO::PARAM_BOOL);
        } else {
            $statement->bindValue($position, $value, self::getPDOParamType($parameter->getSqlType()));
        }
    }


    /**
     * Map a SQL type to the equivalent PDO parameter type.
     *
     * @param string $sqlType
     * @return int
     */
    private static function getPDOParamType($sqlType): int {
        return match (strtoupper($sqlType ?? "")) {
            ResultSetColumn::SQL_TINYINT, ResultSetColumn::SQL_SMALLINT, ResultSetColumn::SQL_INT,
            ResultSetColumn::SQL_INTEGER, ResultSetColumn::SQL_BIGINT => \PDO::PARAM_INT,
            ResultSetColumn::SQL_BLOB, ResultSetColumn::SQL_LONGBLOB => \PDO::PARAM_LOB,
            default => \PDO::PARAM_STR
        };
    }


    /**
     * Close this statement and free resources.
     */
    public function close(): void {
        $this->statement = null;
    }
}
