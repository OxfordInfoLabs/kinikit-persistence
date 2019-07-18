<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Object\DynamicSerialisableObject;

/**
 * Common Persistable field object.  Encodes optional rules about fields for persistence such as key, required, auto increment etc.
 *
 * @author mark
 *
 */
class ObjectPersistableField extends DynamicSerialisableObject {

    protected $fieldName;
    protected $primaryKey;
    protected $required;
    protected $autoIncrement;
    protected $formatter;
    protected $readOnly;
    protected $type;
    protected $length;

    /**
     * Define a persistable field using field name and optionally rule booleans if applicable.
     *
     * @param $fieldName
     * @param $required
     * @param $key
     * @param $required
     */
    public function __construct($fieldName = null, $required = false, $primaryKey = false, $autoIncrement = false, $formatter = null, $readOnly = false) {
        $this->fieldName = $fieldName;
        $this->primaryKey = $primaryKey;
        $this->required = $required;
        $this->autoIncrement = $autoIncrement;
        $this->formatter = $formatter;
        $this->readOnly = $readOnly;
    }

    /**
     * @return the $fieldName
     */
    public function getFieldName() {
        return $this->fieldName;
    }

    /**
     * @return the $key
     */
    public function getPrimaryKey() {
        return $this->primaryKey;
    }

    /**
     * @return the $required
     */
    public function getRequired() {
        return $this->required;
    }

    /**
     * @return the $autoIncrement
     */
    public function getAutoIncrement() {
        return $this->autoIncrement;
    }

    /**
     * @return the $formatter
     */
    public function getFormatter() {
        return $this->formatter;
    }

    /**
     * @return mixed
     */
    public function getReadOnly() {
        return $this->readOnly;
    }

    /**
     * @return mixed
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getLength() {
        return $this->length;
    }


}

?>
