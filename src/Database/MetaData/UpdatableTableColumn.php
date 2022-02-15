<?php


namespace Kinikit\Persistence\Database\MetaData;


/**
 * Updatable version of a table column.  This allows for setting of all properties as well
 * as providing ability to rename a column by allowing for a previous name field.
 *
 * Class UpdatableTableColumn
 * @package Kinikit\Persistence\Database\MetaData
 */
class UpdatableTableColumn extends TableColumn {

    /**
     * @var string
     */
    private $previousName;


    public function __construct($name, $type, $length = null, $precision = null, $defaultValue = null, $primaryKey = false, $autoIncrement = false, $notNull = false, $previousName = null) {
        parent::__construct($name, $type, $length, $precision, $defaultValue, $primaryKey, $autoIncrement, $notNull);
        $this->previousName = $previousName;
    }

    /**
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @param string $type
     */
    public function setType($type) {
        $this->type = $type;
    }

    /**
     * @param mixed|null $length
     */
    public function setLength($length) {
        $this->length = $length;
    }

    /**
     * @param mixed|null $precision
     */
    public function setPrecision($precision) {
        $this->precision = $precision;
    }

    /**
     * @param mixed|null $defaultValue
     */
    public function setDefaultValue($defaultValue) {
        $this->defaultValue = $defaultValue;
    }

    /**
     * @param false|mixed $primaryKey
     */
    public function setPrimaryKey($primaryKey) {
        $this->primaryKey = $primaryKey;
    }

    /**
     * @param false|mixed $autoIncrement
     */
    public function setAutoIncrement($autoIncrement) {
        $this->autoIncrement = $autoIncrement;
    }

    /**
     * @param false|mixed $notNull
     */
    public function setNotNull($notNull) {
        $this->notNull = $notNull;
    }


    /**
     * @return string
     */
    public function getPreviousName() {
        return $this->previousName;
    }

    /**
     * @param string $previousName
     */
    public function setPreviousName($previousName) {
        $this->previousName = $previousName;
    }


}