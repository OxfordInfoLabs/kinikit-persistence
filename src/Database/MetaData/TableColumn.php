<?php

namespace Kinikit\Persistence\Database\MetaData;

/**
 * Table column class.  Returned from dialect managers for getting table column info.
 */
class TableColumn {

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var integer
     */
    protected $length;

    /**
     * @var integer
     */
    protected $precision;

    /**
     * @var mixed
     */
    protected $defaultValue;

    /**
     * @var boolean
     */
    protected $primaryKey;

    /**
     * @var boolean
     */
    protected $autoIncrement;

    /**
     * @var boolean
     */
    protected $notNull;

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
    const SQL_LONGBLOB = "LONGBLOB";
    const SQL_UNKNOWN = "UNKNOWN";


    /**
     * TableColumn constructor.
     *
     * @param string $name
     * @param string $type
     * @param integer $length
     * @param integer $precision
     * @param mixed $defaultValue
     * @param boolean $primaryKey
     * @param boolean $autoIncrement
     * @param boolean $notNull
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
     * @return integer
     */
    public function getPrecision() {
        return $this->precision;
    }


    /**
     * Return the default value if set.
     *
     * @return mixed
     */
    public function getDefaultValue() {
        return $this->defaultValue;
    }

    /**
     * @return boolean
     */
    public function isPrimaryKey() {
        return $this->primaryKey;
    }


    /**
     * @return boolean
     */
    public function isAutoIncrement() {
        return $this->autoIncrement;
    }

    /**
     * @param boolean $autoIncrement
     */
    public function setAutoIncrement($autoIncrement) {
        $this->autoIncrement = $autoIncrement;
    }


    /**
     * @return boolean
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


    /**
     * Create a table column from a string spec
     *
     * @param $stringSpec
     */
    public static function createFromStringSpec($stringSpec) {

        $splitSpec = explode(" ", trim($stringSpec));

        $columnName = $splitSpec[0];
        $type = $splitSpec[1];
        $length = null;
        $notNull = strpos($stringSpec, "NOT NULL") ? true : false;
        $autoIncrement = strpos($stringSpec, "AUTOINCREMENT") ? true : false;
        preg_match("/^(.*?)\((.*?)\)/", $type, $matches);
        if ($matches) {
            $type = $matches[1];
            $length = $matches[2];
        }
        preg_match("/DEFAULT ('.*?'|\w+)/", $stringSpec, $defaultMatches);
        $defaultValue = sizeof($defaultMatches) ? trim($defaultMatches[1], "' ") : null;

        return new TableColumn($columnName, $type, $length, null, $defaultValue, $autoIncrement, $autoIncrement, $notNull);

    }

}

?>
