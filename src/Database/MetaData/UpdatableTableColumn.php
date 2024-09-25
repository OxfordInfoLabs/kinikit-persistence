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

    /**
     * UpdatableTableColumn constructor.
     *
     * @param string $name
     * @param string $type
     * @param integer $length
     * @param integer $precision
     * @param mixed $defaultValue
     * @param boolean $primaryKey
     * @param boolean $autoIncrement
     * @param boolean $notNull
     * @param string $previousName
     */
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
     * @param integer $length
     */
    public function setLength($length) {
        $this->length = $length;
    }

    /**
     * @param integer $precision
     */
    public function setPrecision($precision) {
        $this->precision = $precision;
    }

    /**
     * @param mixed $defaultValue
     */
    public function setDefaultValue($defaultValue) {
        $this->defaultValue = $defaultValue;
    }

    /**
     * @param boolean $primaryKey
     */
    public function setPrimaryKey($primaryKey) {
        $this->primaryKey = $primaryKey;
    }

    /**
     * @param boolean $autoIncrement
     */
    public function setAutoIncrement($autoIncrement) {
        $this->autoIncrement = $autoIncrement;
    }

    /**
     * @param boolean $notNull
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


    /**
     * Get an updatable table column from a table column
     *
     * @param TableColumn $tableColumn
     */
    public static function createFromTableColumn($tableColumn) {
        return new UpdatableTableColumn($tableColumn->getName(), $tableColumn->getType(), $tableColumn->getLength(),
            $tableColumn->getPrecision(), $tableColumn->getDefaultValue(), $tableColumn->isPrimaryKey(), $tableColumn->isAutoIncrement(),
            $tableColumn->isNotNull());
    }


}