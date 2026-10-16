<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use DateTimeImmutable;
use DateTimeInterface;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Date;
use Google\Cloud\Spanner\Timestamp;
use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\Exception\SpannerSQLException;
use Kinikit\Persistence\Database\PreparedStatement\BasePreparedStatement;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\BindParameterType;
use Kinikit\Persistence\Database\PreparedStatement\WrongNumberOfPreparedStatementParametersException;

class SpannerPreparedStatement extends BasePreparedStatement {

    private Database $connection;

    /**
     * @var integer $boundValues
     */
    private $boundValues;

    /**
     * @param string $sql
     * @param Database $connection
     */
    public function __construct(?string $sql, $connection) {
        $this->boundValues = substr_count($sql, "?");

        $index = 1;
        $spannerSql = preg_replace_callback('/\?/', function () use (&$index) {
            return '@p' . $index++;
        }, $sql);

        parent::__construct($spannerSql);

        $this->connection = $connection;
    }

    /**
     * @param BindParameter[]|mixed[] $parameterValues
     * @return void
     * @throws SpannerSQLException
     * @throws WrongNumberOfPreparedStatementParametersException
     */
    public function execute(array $parameterValues): void {

        if (count($parameterValues) !== $this->boundValues) {
            throw new WrongNumberOfPreparedStatementParametersException($this->boundValues, count($parameterValues));
        }

        $spannerParams = [];
        $index = 1;
        foreach ($parameterValues as $value) {
            if (is_numeric($value)) {
                if (floor($value) == $value) {
                    $value = (int)$value;
                } else {
                    $value = (float)$value;
                }
            }
            $spannerParams['p' . $index++] = self::bindParameter($value);
        }

        Logger::log($parameterValues);
        Logger::log($this->getStatementSQL());

        try {
            $options = ['parameters' => $spannerParams];
            $this->connection->runTransaction(function ($transaction) use ($options) {
                $transaction->executeUpdate($this->getStatementSQL(), $options);
                $transaction->commit();
            });
        } catch (\Exception $e) {
            throw new SpannerSQLException($e->getMessage(), $e->getCode());
        }
    }

    public static function bindParameter(BindParameter $parameter): mixed {
        $value = $parameter->getValue();
        if ($value === null) {
            return null;
        }

        return match ($parameter->getType()) {
            BindParameterType::TYPE_INT => (int)$value,
            BindParameterType::TYPE_FLOAT => (float)$value,
            BindParameterType::TYPE_BOOL => (bool)$value,
            BindParameterType::TYPE_STRING => (string)$value,
            BindParameterType::TYPE_DATE => new Date(self::asDateTime($value)),
            BindParameterType::TYPE_DATETIME => new Timestamp(self::asDateTime($value)),
            default => $value
        };
    }

    private static function asDateTime(mixed $value): DateTimeInterface {
        return $value instanceof DateTimeInterface ? $value : new DateTimeImmutable((string)$value);
    }

    public function close(): void {
        $this->statement = null;
    }
}