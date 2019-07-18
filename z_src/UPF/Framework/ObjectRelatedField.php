<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\DynamicSerialisableObject;

/**
 * Related field for mapping parent and child fields in object relationships.
 *
 * @author oxil
 *
 */
class ObjectRelatedField extends DynamicSerialisableObject {

    private $parentField;
    private $childField;
    private $staticValue;
    private $staticValueSet;

    public function __construct($parentField = null, $childField = null, $staticValue = null, $staticValueSet = null) {
        $this->parentField = $parentField;
        $this->childField = $childField;
        $this->staticValue = $staticValue;
        $this->staticValueSet = $staticValueSet;
    }

    /**
     * @return the $parentField
     */
    public function getParentField() {
        return $this->parentField;
    }

    /**
     * @return the $childField
     */
    public function getChildField() {
        return $this->childField;
    }

    /**
     * @param field_type $parentField
     */
    public function setParentField($parentField) {
        $this->parentField = $parentField;
    }

    /**
     * @param field_type $childField
     */
    public function setChildField($childField) {
        $this->childField = $childField;
    }

    /**
     * @param null $staticValue
     */
    public function setStaticValue($staticValue) {
        $this->staticValue = $staticValue;
    }

    /**
     * @return null
     */
    public function getStaticValue() {
        return $this->staticValue;
    }

    /**
     * @return null
     */
    public function getStaticValueSet() {
        return $this->staticValueSet;
    }

    /**
     * @param null $staticValueSet
     */
    public function setStaticValueSet($staticValueSet) {
        $this->staticValueSet = $staticValueSet;
    }

}

?>