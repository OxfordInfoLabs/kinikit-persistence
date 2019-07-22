<?php

namespace Kinikit\Persistence\Database\MetaData;

/**
 * Table column class.  Returned from dialect managers for getting table column info.
 */
class TableColumn {

    private $name;
    private $type;
    private $length;
    private $precision;
    private $defaultValue;
    private $primaryKey;
    private $autoIncrement;
    private $notNull;

    const SQL_VARCHAR = "VARCHAR";
    const SQL_TINYINT = "TINYINT";
    const SQL_SMALLINT = "SMALLINT";
    const SQL_INT = "INT";
    const SQL_INTEGER = "INTEGER";
    const SQL_BIGINT = "BIGINT";
    const SQL_FLOAT = "FLOAT";
    const SQL_DOUBLE = "DOUBLE";
    const SQL_REAL = "REAL";
    const SQL_DECIMAL = "DECIMAL";
    const SQL_DATE = "DATE";
    const SQL_TIME = "TIME";
    const SQL_DATE_TIME = "DATETIME";
    const SQL_TIMESTAMP = "TIMESTAMP";
    const SQL_BLOB = "BLOB";
    const SQL_UNKNOWN = "UNKNOWN";

    /**
     * Construct with name and type
     *
     * @param $name string
     * @param $type string
     * @return TableColumn
     */
    public function __construct($name, $type, $length = null, $precision = null, $defaultValue = null, $primaryKey = false, $autoIncrement = false, $notNull = false) {
        $this->name = $name;
        $this->type = $type;
        $this->length = $length;
        $this->precision = $precision;
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
     * @return null
     */
    public function getPrecision() {
        return $this->precision;
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
    public function isPrimaryKey() {
        return $this->primaryKey;
    }


    /**
     * @return bool
     */
    public function isAutoIncrement() {
        return $this->autoIncrement;
    }


    /**
     * @return mixed
     */
    public function isNotNull() {
        return $this->notNull;
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


}

?>
