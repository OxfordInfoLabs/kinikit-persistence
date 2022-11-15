<?php


namespace Kinikit\Persistence\Database\MetaData;


class ResultSetColumn {

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
     * ResultSetColumn constructor.
     *
     * @param string $name
     * @param string $type
     * @param int $length
     * @param int $precision
     */
    public function __construct($name = null, $type = null, $length = null, $precision = null) {
        $this->name = $name;
        $this->type = $type;
        $this->length = $length;
        $this->precision = $precision;
    }


    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getLength() {
        return $this->length;
    }

    /**
     * @return int
     */
    public function getPrecision() {
        return $this->precision;
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