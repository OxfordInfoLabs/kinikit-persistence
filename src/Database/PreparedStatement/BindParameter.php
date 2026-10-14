<?php

namespace Kinikit\Persistence\Database\PreparedStatement;

/**
 * Bind parameter class
 *
 */
class BindParameter {

    private BindParameterType $type;
    private mixed $value;

    public function __construct($value, $type = BindParameterType::TYPE_OTHER) {
        $this->type = $type;
        $this->value = $value;
    }

    public function getType(): BindParameterType {
        return $this->type;
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

        $type = match (true) {
            $value instanceof BlobWrapper => BindParameterType::TYPE_BLOB,
            is_array($value) => BindParameterType::TYPE_ARRAY,
            is_int($value) => BindParameterType::TYPE_INT,
            is_numeric($value) => BindParameterType::TYPE_FLOAT,
            default => BindParameterType::TYPE_OTHER
        };

        return new BindParameter($value, $type);
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
