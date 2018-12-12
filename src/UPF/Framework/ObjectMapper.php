<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Exception\ClassNotFoundException;
use Kinikit\Core\Exception\ClassNotSerialisableException;
use Kinikit\Core\Object\DynamicSerialisableObject;
use Kinikit\Core\Util\Annotation\ClassAnnotationParser;
use Kinikit\Core\Util\ClassUtils;
use Kinikit\Persistence\UPF\Exception\WrongMappedClassException;

/**
 * Base object mapper class.  This defines a mapping for a class of objects, with possible explicit
 * persistable fields if required.  It also allows for the selective mapping to certain defined engines
 * if the enabledEngines array if populated or disabledEngines array.
 *
 * @author mark
 *
 */
class ObjectMapper extends DynamicSerialisableObject {

    protected $className;
    protected $fields;
    protected $relationships;
    protected $interceptorEvaluator;
    protected $enabledEngines;
    protected $disabledEngines;
    protected $location;
    protected $locking = true;
    protected $lockingDataField = "lockingData";
    protected $extends;
    protected $readOnly;
    protected $noValidateOnSave = false;

    // Transient fields
    private $fieldsByName;


    /**
     * Create an object mapper for a given class of object and optionally with some preconfigured
     * persistable fields if known.
     *
     * @param string $className
     * @param array $persistableFields
     */
    public function __construct($className = null, $persistableFields = null, $relationships = null, $enabledEngines = null, $disabledEngines = null) {

        // Throw an exception if the class does not exist.
        if ($className && !class_exists($className)) {
            throw new ClassNotFoundException ($className);
        }

        if ($className && !ClassUtils::doesClassExtendClass($className, "Kinikit\Core\Object\SerialisableObject")) {
            throw new ClassNotSerialisableException ($className);
        }

        $this->className = $className;
        $this->fields = $persistableFields;
        $this->relationships = $relationships;
        $this->enabledEngines = $enabledEngines;
        $this->disabledEngines = $disabledEngines;

        if ($className && !$this->fields) {
            $this->populateFieldsFromAnnotationsOrDefault();
        }

    }

    /**
     * Get the class name in use for this mapper
     *
     * @return the $className
     */
    public function getClassName() {
        return $this->className;
    }


    public function setClassName($className) {
        $this->className = $className;
        $this->populateFieldsFromAnnotationsOrDefault();
    }

    /**
     * Get the persistable fields defined for this class.
     *
     * @return the $persistableFields
     */
    public function getFields() {
        return $this->fields ? (is_array($this->fields) ? $this->fields : array($this->fields)) : array();
    }


    public function setFields($fields) {
        $this->fields = $fields;
        $this->fieldsByName = null;
    }


    /**
     * @return the $relationships
     */
    public function getRelationships() {
        return $this->relationships ? (is_array($this->relationships) ? $this->relationships : array($this->relationships)) : array();
    }

    /**
     * @param null $relationships
     */
    public function setRelationships($relationships) {
        $this->relationships = $relationships;
    }


    /**
     * @param $interceptors the $interceptors to set
     */
    public function setInterceptors($interceptors = array()) {
        $this->interceptorEvaluator = new UPFObjectInterceptorEvaluator ($this->className, $interceptors);
    }

    /**
     * Return the evaluator used by the persistence coordinator to run all of the interceptors for a given mapping
     * for a given operation.
     *
     */
    public function getInterceptorEvaluator() {
        return $this->interceptorEvaluator;
    }

    public function setExtends($extends) {
        $this->extends = $extends;
    }

    public function getExtends() {
        return $this->extends;
    }

    /**
     * @return mixed
     */
    public function getReadOnly() {
        return $this->readOnly;
    }

    /**
     * @param mixed $readOnly
     */
    public function setReadOnly($readOnly) {
        $this->readOnly = $readOnly;
    }


    /**
     * Get all defined fields keyed in by name
     *
     */
    public function getFieldsByName() {
        // If no by name map has been made, make one.
        if (!$this->fieldsByName) {

            $this->fieldsByName = array();

            foreach ($this->getFields() as $field) {
                if (($field instanceof ObjectPersistableField)) {
                    $this->fieldsByName [$field->getFieldName()] = $field;
                } else if (is_string($field)) {
                    $this->fieldsByName [$field] = new ObjectPersistableField ($field);
                }
            }

        }

        return $this->fieldsByName;
    }

    /**
     * Get the location value for this mapper if defined.
     */
    public function getLocation() {
        return $this->location;
    }

    /**
     * @return the $locking
     */
    public function getLocking() {
        return $this->locking;
    }

    /**
     * @return the $lockingDataField
     */
    public function getLockingDataField() {
        return $this->lockingDataField;
    }

    /**
     * @param $locking the $locking to set
     */
    public function setLocking($locking) {
        $this->locking = $locking;
    }

    /**
     * @param $lockingDataField the $lockingDataField to set
     */
    public function setLockingDataField($lockingDataField) {
        $this->lockingDataField = $lockingDataField;
    }

    /**
     * Get the list of enabled engines if defined either as an array or csv string
     *
     * @return array
     */

    public function getEnabledEngines() {
        return $this->enabledEngines;
    }

    /**
     * Get the list of disabled engines if defined (either as an array or csv string)
     *
     * @return array
     */
    public function getDisabledEngines() {
        return $this->disabledEngines;
    }

    /**
     * Get the boolean for no validate on save.
     *
     * @return boolean
     */
    public function getNoValidateOnSave() {
        return $this->noValidateOnSave;
    }


    /**
     * Return a boolean indicating whether or not this mapper is enabled for a particular engine by identifier.
     *
     * @param string $engineIdentifier
     * @return boolean
     */
    public function isEnabledForEngine($engineIdentifier) {

        if ($this->enabledEngines) {

            $enabledEngines =
                is_array($this->enabledEngines) ? $this->enabledEngines : explode(",", $this->enabledEngines);

            foreach ($enabledEngines as $enabledEngine) {
                if (trim($enabledEngine) == $engineIdentifier) return true;
            }
        } else if ($this->disabledEngines) {

            $disabledEngines =
                is_array($this->disabledEngines) ? $this->disabledEngines : explode(",", $this->disabledEngines);

            foreach ($disabledEngines as $disabledEngine) {
                if (trim($disabledEngine) == $engineIdentifier) {
                    return false;
                }
            }
            return true;
        } else {
            return true;
        }

        return false;
    }

    /**
     * Get a particular persistable field by name from this mapper, or a placeholder one if none has been defined.
     *
     * @param $fieldName
     * @return ObjectPersistableField
     */
    public function getField($fieldName) {

        // If no field entry has been added, add one now.
        if (!array_key_exists($fieldName, $this->getFieldsByName())) {
            return null;
        } else {
            return $this->fieldsByName [$fieldName];
        }

    }

    /**
     * Get the map of fields and values for an object instance using the constructed definition.
     *
     * @param SerialisableObject object
     */
    public function getPersistableFieldValueMapForObject($object) {

        // If wrong class supplied, throw an exception
        if (!($object instanceof $this->className)) {
            throw new WrongMappedClassException ($this->className, get_class($object));
        }

        // Get all serialisable data from the object
        $objectPropertyMap = $object->__getSerialisablePropertyMap();

        // Check we have fields
        $persistableFields = $this->fields;

        // Construct the map for return.
        $returnMap = array();
        $pkFound = false;
        foreach ($persistableFields as $field) {
            if ($field instanceof ObjectPersistableField) {
                if (array_key_exists($field->getFieldName(), $objectPropertyMap)) {
                    $fieldValue = $objectPropertyMap [$field->getFieldName()];
                } else {
                    $fieldValue =
                        $objectPropertyMap [substr(strtoupper($field->getFieldName()), 0, 1) . substr($field->getFieldName(), 1)];
                }

                $returnMap [$field->getFieldName()] = array($field, $fieldValue);
                if ($field->getPrimaryKey()) $pkFound = true;
            } else if (is_string($field)) {
                $returnMap [$field] = array(new ObjectPersistableField ($field),
                    (isset ($objectPropertyMap [$field]) ? $objectPropertyMap [$field] : null));
            }
        }

        // If no persistable fields were defined and an id field exists, assume it is the primary key and also an auto increment field.
        if (!$pkFound && isset ($returnMap ["id"])) {
            $returnMap ["id"] = array(new ObjectPersistableField ("id", true, true, true), $returnMap ["id"] [1]);
        }

        return $returnMap;

    }

    /**
     * Convenience method for obtaining the array of fields which constitute the primary key.
     *
     */
    public function getPrimaryKeyFields() {

        $pkFields = array();
        if ($this->getFields()) {
            foreach ($this->getFields() as $field) {
                if (is_object($field) && $field->getPrimaryKey()) {
                    $pkFields [] = $field;
                }
            }
        }

        // If no pkFields, if an id field exists use it.
        if (!$pkFields && (ClassUtils::doesClassExtendClass($this->getClassName(), "DynamicSerialisableObject") || method_exists($this->getClassName(), "getId"))) {
            $pkFields [] = new ObjectPersistableField ("id", true, true, true);
        }

        return $pkFields;

    }

    /**
     * Obtain the primary key value for the supplied object using the mapper definition.
     *
     * @param Object $object
     */
    public function getPrimaryKeyValueForObject($object, $fieldFormatters = null) {


        $persistableFieldsMap = $this->getPersistableFieldValueMapForObject($object);

        $key = array();
        foreach ($persistableFieldsMap as $fieldName => $persistableEntry) {
            if ($persistableEntry [0]->getPrimaryKey()) {

                $key [$fieldName] = $persistableEntry [1];

                // If we have a field formatter, ensure we unformat the primary key value before using it....
                if (isset ($fieldFormatters [$persistableEntry [0]->getFormatter()])) {
                    $key [$fieldName] =
                        $fieldFormatters [$persistableEntry [0]->getFormatter()]->unformat($key [$fieldName]);
                }

            }

        }


        return sizeof($key) > 0 ? $key : null;
    }

    /**
     * Extract the primary key value from a supplied array of values passed as an associative array
     * keyed in by field name using this mapper definition.
     *
     * @param $valuesArray
     */
    public function getPrimaryKeyValueForArrayOfValues($valuesArray) {

        $pkPersistableFields = $this->getPrimaryKeyFields();

        $key = array();
        foreach ($pkPersistableFields as $persistableEntry) {
            if (isset ($valuesArray [$persistableEntry->getFieldName()])) {
                $key [$persistableEntry->getFieldName()] = $valuesArray [$persistableEntry->getFieldName()];
            }
        }

        return sizeof($key) > 0 ? $key : null;
    }


    // Attempt population of this mapper from annotations.
    private function populateFieldsFromAnnotationsOrDefault() {

        if ($this->className) {

            $classAnnotationsObj = ClassAnnotationParser::instance()->parse($this->getClassName());

            $classAnnotations = $classAnnotationsObj->getClassAnnotations();


            $ignoredTags = array("authors", "package", "var", "validation", "mapped");

            foreach ($classAnnotations as $key => $value) {

                // Assume annotations are singleton so pick the first one.
                $value = $value[0];

                if (in_array($key, $ignoredTags)) continue;

                if ($key == "interceptors") {
                    $interceptors = array();
                    $interceptorNames = $value->getValues();
                    foreach ($interceptorNames as $interceptorName) {
                        $interceptors[] = new $interceptorName();
                    }
                    $this->setInterceptors($interceptors);
                } else
                    $this->__setSerialisablePropertyValue($key, ($value->getValue() ? $value->getValue() : true));
            }

            $fieldAnnotations = $classAnnotationsObj->getFieldAnnotationsNotContainingTags(array("relationship", "unmapped"));
            $fields = array();
            foreach ($fieldAnnotations as $field => $annotations) {
                $field = new ObjectPersistableField($field);
                foreach ($annotations as $key => $value) {

                    // Assume annotations are singleton so pick the first one.
                    $value = $value[0];

                    if (in_array($key, $ignoredTags)) continue;
                    $field->__setSerialisablePropertyValue($key, ($value->getValue() ? $value->getValue() : true));
                }
                $fields[] = $field;
            }


            $this->fields = $this->fields ? array_merge($fields, $this->fields) : $fields;
            $this->getFieldsByName();

            $relationshipAnnotations = $classAnnotationsObj->getFieldAnnotationsContainingMatchingTag("relationship");
            $relationships = array();
            foreach ($relationshipAnnotations as $field => $annotations) {
                $relationship = new ObjectRelationship($field);

                foreach ($annotations as $key => $value) {

                    // Assume annotations are singleton so pick the first one.
                    $value = $value[0];

                    if (in_array($key, $ignoredTags)) continue;

                    if ($key == "relatedFields") {
                        $relatedFields = array();
                        $relatedFieldsRaw = $value->getValues();
                        foreach ($relatedFieldsRaw as $rawField) {
                            $explodedField = explode("=>", $rawField);
                            if (sizeof($explodedField) == 2) {
                                $rhs = trim($explodedField[1]);
                                $lhs = trim($explodedField[0]);
                                if (trim($rhs, '"\'') == $rhs) {
                                    $relatedFields[] = new ObjectRelatedField($lhs, $rhs);
                                } else {
                                    $relatedFields[] = new ObjectRelatedField(null, $lhs, trim($rhs, '"\''));
                                }
                            }
                        }
                        $relationship->setRelatedFields($relatedFields);

                    } else if ($key == "orderingFields") {
                        $orderingFields = array();
                        $orderingFieldsRaw = $value->getValues();
                        foreach ($orderingFieldsRaw as $rawField) {
                            $explodedField = explode(":", $rawField);
                            if (sizeof($explodedField) == 2) {
                                $rhs = trim($explodedField[1]);
                                $lhs = trim($explodedField[0]);
                                $orderingFields[] = new ObjectOrderingField($lhs, $rhs);
                            }
                        }
                        $relationship->setOrderingFields($orderingFields);
                    } else
                        $relationship->__setSerialisablePropertyValue($key, ($value->getValue() ? $value->getValue() : true));
                }

                $relationships[] = $relationship;
            }

            $this->relationships = $this->relationships ? array_merge($relationships, $this->relationships) : $relationships;


        }


    }

}

?>