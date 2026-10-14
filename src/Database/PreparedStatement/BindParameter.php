<?php


namespace Kinikit\Persistence\Database\PreparedStatement;


use Kinikit\Persistence\Database\MetaData\ResultSetColumn;

/**
 * Bind parameter class
 *
 */
class BindParameter {

    private $sqlType;
    private $value;

    public function __construct($sqlType, $value) {
        $this->sqlType = $sqlType;
        $this->value = $value;
    }

    public function getSqlType() {
        return $this->sqlType;
    }

    public function getValue() {
        return $this->value;
    }


    /**
     * Create a bind parameter from a raw value, inferring the SQL type where unambiguous.
     * Existing bind parameters are returned intact.  Values whose type cannot be safely
     * inferred are given the SQL_UNKNOWN type so drivers can apply their default binding.
     *
     * @param mixed $value
     * @return BindParameter
     */
    public static function create($value): BindParameter {

        if ($value instanceof BindParameter) {
            return $value;
        }

        $sqlType = match (true) {
            $value instanceof BlobWrapper => ResultSetColumn::SQL_BLOB,
            is_array($value) => ResultSetColumn::SQL_JSON,
            is_int($value) => ResultSetColumn::SQL_INTEGER,
            default => ResultSetColumn::SQL_UNKNOWN
        };

        return new BindParameter($sqlType, $value);
    }


    /**
     * Create an array of bind parameters from an array of raw values and / or bind parameters.
     *
     * @param mixed[] $values
     * @return BindParameter[]
     */
    public static function createAll(array $values): array {
        return array_values(array_map(fn($value) => self::create($value), $values));
    }

}
