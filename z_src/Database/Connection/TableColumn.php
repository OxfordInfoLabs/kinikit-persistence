<?php

namespace Kinikit\Persistence\Database\Connection;

use Kinikit\Core\Object\SerialisableObject;

/**
 * Table column class.  Contains
 */
class TableColumn extends SerialisableObject {

    private $name;
    private $type;
    private $length;
    private $defaultValue;
    private $primaryKey;
    private $autoIncrement;
    private $notNull;

    const SQL_VARCHAR = 12;
    const SQL_TINYINT = -6;
    const SQL_SMALLINT = 5;
    const SQL_INT = 4;
    const SQL_INTEGER = 4;
    const SQL_BIGINT = -5;
    const SQL_FLOAT = 6;
    const SQL_DOUBLE = 8;
    const SQL_REAL = 7;
    const SQL_DECIMAL = 3;
    const SQL_DATE = 9;
    const SQL_TIME = 10;
    const SQL_TIMESTAMP = 11;
    const SQL_BLOB = 99;
    const SQL_UNKNOWN = 0;

    private static $phpTypeMappings = array(
        "integer" => array(self::SQL_INT, null),
        "int" => array(self::SQL_INT, null),
        "string" => array(self::SQL_VARCHAR, 255),
        "boolean" => array(self::SQL_TINYINT, null),
        "bool" => array(self::SQL_TINYINT, null),
        "float" => array(self::SQL_FLOAT, 25));


    /**
     * Construct with name and type
     *
     * @param $name string
     * @param $type string
     * @return TableColumn
     */
    public function __construct($name, $type, $length, $defaultValue = "", $primaryKey = false, $autoIncrement = false, $notNull = false) {
        $this->name = $name;
        $this->type = $type;
        $this->length = $length;
        $this->defaultValue = $defaultValue;
        $this->primaryKey = $primaryKey;
        $this->autoIncrement = $autoIncrement;
        $this->notNull = $notNull;
    }

    /**
     * Get the column name
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Get the column type
     *
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Get the column length
     *
     * @return integer
     */
    public function getLength() {
        return $this->length;
    }

    /**
     * Return the default value if set.
     */
    public function getDefaultValue() {
        return $this->defaultValue;
    }

    /**
     * @return mixed
     */
    public function getPrimaryKey() {
        return $this->primaryKey;
    }

    /**
     * @param bool $primaryKey
     */
    public function setPrimaryKey($primaryKey) {
        $this->primaryKey = $primaryKey;
    }

    /**
     * @return bool
     */
    public function getAutoIncrement() {
        return $this->autoIncrement;
    }


    /**
     * @param mixed $autoIncrement
     */
    public function setAutoIncrement($autoIncrement) {
        $this->autoIncrement = $autoIncrement;
    }

    /**
     * @return mixed
     */
    public function getNotNull() {
        return $this->notNull;
    }

    /**
     * @param bool $notNull
     */
    public function setNotNull($notNull) {
        $this->notNull = $notNull;
    }


    /**
     * Get the SQL type for this table column as a string.
     */
    public function getSQLType() {
        if (is_string($this->type)) {
            return $this->type;
        } else {
            $reflectionClass = new \ReflectionClass($this);
            $constants = $reflectionClass->getConstants();
            $flipped = array_flip($constants);
            if (isset($flipped[$this->type])) {
                $explodedConstant = explode("SQL_", $flipped[$this->type]);
                return array_pop($explodedConstant);
            }
        }
    }

    /**
     * Return an indicator as to whether or not this column is a numeric column
     */
    public function isNumeric() {

        // Check the type
        switch ($this->getType()) {
            case TableColumn::SQL_TINYINT :
            case TableColumn::SQL_SMALLINT :
            case TableColumn::SQL_INT :
            case TableColumn::SQL_BIGINT :
            case TableColumn::SQL_FLOAT :
            case TableColumn::SQL_DOUBLE :
            case TableColumn::SQL_DECIMAL :
                return true;
                break;
            default :
                return false;

        }

    }

    /**
     * Get the SQL Value ready for select / insert based upon the input value.
     * Essentially we need to ensure that we quote non numerics and 0 out
     * numerics
     * which are blank as well as blank out strings passed for numeric columns.
     */
    public function getSQLValue($inputValue) {

        $columnNumeric = $this->isNumeric();

        // If the column is numeric do appropriate
        if ($inputValue === null) {
            $inputValue = "NULL";
        } else if ($columnNumeric) {
            if (!is_numeric($inputValue)) {
                $inputValue = "'" . $inputValue . "'";
            } else if ($inputValue == '') {
                $inputValue = 0;
            }
        } else {
            $inputValue = "'" . str_replace("'", "''", $inputValue) . "'";
        }

        return $inputValue;

    }


    /**
     * Get the SQL type for a php type - default to string
     */
    public static function getSQLTypeForPHPType($phpType) {
        $phpType = strtolower($phpType);
        return isset(self::$phpTypeMappings[$phpType]) ? self::$phpTypeMappings[$phpType] : self::$phpTypeMappings["string"];
    }


}

?>
