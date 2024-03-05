<?php

namespace Kinikit\Persistence\Database\MetaData;

/**
 * Table column class.  Returned from dialect managers for getting table column info.
 */
class TableColumn extends ResultSetColumn {


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
        parent::__construct($name, $type, $length, $precision);
        $this->defaultValue = $defaultValue;
        $this->primaryKey = $primaryKey;
        $this->autoIncrement = $autoIncrement;
        $this->notNull = $notNull || $primaryKey;
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
