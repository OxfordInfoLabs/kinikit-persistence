<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Object\DynamicSerialisableObject;

/**
 * Relationship field which encodes a nested object relationship for a field.
 * This encapsulates the generic business rules behind a field relationship including
 * which object class it relates to, whether it is a single or multiple relationship,  lazy loading, read only and delete cascade rules.
 * It also encodes in a reasonably generic way whether or not the relationship is a many-many or one-many
 * if the child parentKeyField is defined or not.
 *
 * @author mark
 *
 */
class ObjectRelationship extends DynamicSerialisableObject {

    // Members
    protected $fieldName;
    protected $relatedClassName;
    protected $isMultiple;
    protected $readOnly;
    protected $lazyLoad;
    protected $createIfNull;
    protected $deleteCascade;

    protected $master;
    protected $relatedFields;
    protected $orderingFields;


    const MASTER_PARENT = "parent";
    const MASTER_CHILD = "child";

    /**
     * Construct the relationship object optionally with any configuration.
     *
     * @param string $relatedClassName
     * @param boolean $isMultiple
     * @param boolean $readOnly
     * @param boolean $lazyLoad
     * @param boolean $deleteCascade
     * @param string $parentKeyField
     */
    public function __construct($fieldName = null, $relatedClassName = null, $isMultiple = false, $readOnly = false, $lazyLoad = false, $deleteCascade = false, $master = null, $createIfNull = null) {
        $this->fieldName = $fieldName;
        $this->relatedClassName = $relatedClassName;
        $this->isMultiple = $isMultiple;
        $this->readOnly = $readOnly;
        $this->lazyLoad = $lazyLoad;
        $this->deleteCascade = $deleteCascade;
        $this->master = $master;
        $this->createIfNull = $createIfNull;
    }

    /**
     * @return the $fieldName
     */
    public function getFieldName() {
        return $this->fieldName;
    }

    /**
     * @return the $master
     */
    public function getMaster() {
        return $this->master ? $this->master : ($this->getIsMultiple() ? self::MASTER_PARENT : self::MASTER_CHILD);
    }

    /**
     * @return the $relatedObjectClass
     */
    public function getRelatedClassName() {
        return $this->relatedClassName;
    }

    /**
     * @return the $isMultiple
     */
    public function getIsMultiple() {
        return $this->isMultiple;
    }

    /**
     * @return the $readOnly
     */
    public function getReadOnly() {
        return $this->readOnly;
    }

    /**
     * @return the $lazyLoad
     */
    public function getLazyLoad() {
        return $this->lazyLoad;
    }

    /**
     * @return mixed
     */
    public function getCreateIfNull() {
        return $this->createIfNull;
    }


    /**
     * @return the $deleteCascade
     */
    public function getDeleteCascade() {
        return $this->deleteCascade;
    }

    /**
     * @return array $relatedFields
     */
    public function getRelatedFields() {
        return $this->relatedFields ? (is_array($this->relatedFields) ? $this->relatedFields : array($this->relatedFields)) : array();
    }

    /**
     * @param array $relatedFields
     */
    public function setRelatedFields($relatedFields) {
        $this->relatedFields = $relatedFields;
    }

    /**
     * Set any ordering fields for this relationship
     *
     * @param $array
     */
    public function setOrderingFields($orderingFields) {
        $this->orderingFields = $orderingFields;
    }

    /**
     * @return mixed
     */
    public function getOrderingFields() {
        return $this->orderingFields ? (is_array($this->orderingFields) ? $this->orderingFields : array($this->orderingFields)) : array();
    }


}

?>