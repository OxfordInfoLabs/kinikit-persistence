<?php


namespace Kinikit\Persistence\Database\PreparedStatement;


use Kinikit\Persistence\Database\Exception\SQLException;

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
     *
     * @param mixed[] $parameterValues
     * @return mixed
     */
    public function execute($parameterValues) {

        // If mismatch of parameter values, throw.
        if (sizeof($parameterValues) != $this->boundValues) {
            throw new WrongNumberOfPreparedStatementParametersException($this->boundValues, sizeof($parameterValues));
        }

        // Bind each parameter value
        foreach ($parameterValues as $index => $parameterValue) {

            if ($parameterValue instanceof BlobWrapper) {

                if ($parameterValue->getContentFileName()) {
                    $blobHandle = fopen($parameterValue->getContentFileName(), "r");
                    $this->statement->bindValue(($index + 1), $blobHandle, \PDO::PARAM_LOB);
                } else {
                    $this->statement->bindValue(($index + 1), $parameterValue->getContentText(), \PDO::PARAM_LOB);
                }

            } else {
                $this->statement->bindValue(($index + 1), $parameterValue);
            }


        }
        try {
            $this->statement->execute();
        } catch (\PDOException $e) {
            throw new SQLException($e->getMessage());
        }
    }


    /**
     * Close this statement and free resources.
     */
    public function close() {
        $this->statement = null;
    }
}
