<?php


namespace Kinikit\Persistence\ORM\Mapping;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Reflection\ClassInspector;
use Kinikit\Core\Reflection\ClassInspectorProvider;
use Kinikit\Core\Reflection\Property;
use Kinikit\Persistence\ORM\Interceptor\ORMInterceptorProcessor;
use Kinikit\Persistence\TableMapper\Mapper\TableMapping;
use Kinikit\Persistence\TableMapper\Relationship\ManyToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\ManyToOneTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToOneTableRelationship;

class ORMMapping {

    /**
     * @var TableMapping
     */
    private $tableMapping;

    /**
     * @var string
     */
    private $className;

    /**
     * @var ClassInspector
     */
    private $classInspector;


    /**
     * @var ORMInterceptorProcessor
     */
    private $ormInterceptorProcessor;


    /**
     * List of related entities for this class.  This
     * maps property names to class names.
     *
     * @var string[string]
     */
    private $relatedEntities = [];


    /**
     * @var ORMMapping[string]
     */
    private static $ormMappings = [];


    /**
     * Private constructor - should use static method below for efficiency.
     *
     * ORMMapping constructor.
     *
     * @param $className
     */
    private function __construct($className) {
        $this->className = $className;
        $classInspectorProvider = Container::instance()->get(ClassInspectorProvider::class);
        $this->classInspector = $classInspectorProvider->getClassInspector($className);
        $this->ormInterceptorProcessor = Container::instance()->get(ORMInterceptorProcessor::class);
        $this->generateTableMapping();
    }


    /**
     * Get an ORM Mapping by class name - maintains an array for efficiency.
     *
     * @param $className
     * @return ORMMapping
     */
    public static function get($className) {
        if (!isset(self::$ormMappings[$className])) {
            self::$ormMappings[$className] = new ORMMapping($className);
        }
        return self::$ormMappings[$className];
    }


    /**
     * Clear cached mappings - useful for testing purposes
     */
    public static function clearMappings() {
        self::$ormMappings = [];
    }

    /**
     * @return string
     */
    public function getClassName() {
        return $this->className;
    }


    /**
     * Get the table mapping generated for this ORM mapping.
     *
     * @return TableMapping
     */
    public function getTableMapping() {
        return $this->tableMapping;
    }


    /**
     * Replace members with columns in a string.
     *
     * @param string $string
     */
    public function replaceMembersWithColumns($string) {

        // Loop through the properties and substitute any properties for column names.
        foreach ($this->classInspector->getProperties() as $property) {

            $propertyName = $property->getPropertyName();
            $string = preg_replace_callback("/(?<![\\S'])($propertyName)(?![\\S'])/", function () use ($propertyName) {
                $columnName = $this->getColumnNameForProperty($propertyName);
                return $columnName;
            }, $string);

        }

        return $string;

    }


    /**
     * Replace columns with members in a string.
     *
     * @param $string
     */
    public function replaceColumnsWithMembers($string) {

        // Loop through the properties and substitute any properties for column names.
        foreach ($this->classInspector->getProperties() as $property) {

            $propertyName = $property->getPropertyName();
            $columnName = $this->getColumnNameForProperty($propertyName);

            $string = preg_replace_callback("/(?<![\\S'])($columnName)(?![\\S'])/", function () use ($propertyName) {
                return $propertyName;
            }, $string);

        }

        return $string;

    }


    /**
     * Map row data to objects
     *
     * @param $rowData
     * @param bool $singleItem
     */
    public function mapRowsToObjects($rowData, $existingObjects = null) {

        // Loop through each row and map it.
        $returnObjects = [];
        foreach ($rowData as $pk => $row) {

            $populateObject = isset($existingObjects[sizeof($returnObjects)]) ? $existingObjects[sizeof($returnObjects)] : $this->classInspector->createInstance([]);

            foreach ($this->classInspector->getProperties() as $propertyName => $property) {

                $isArray = strpos($property->getType(), "[");

                if (isset($this->relatedEntities[$propertyName])) {
                    $relatedClassName = $this->relatedEntities[$propertyName];
                    if (isset($row[$propertyName]) && $row[$propertyName]) {

                        // Work out if single item.
                        $mapper = self::get($relatedClassName);

                        $populateProperty = $property->get($populateObject);
                        $existingObjects = $populateProperty ? ($isArray ? $populateProperty : [$populateProperty]) : null;

                        $propertyResults = $mapper->mapRowsToObjects($isArray ? $row[$propertyName] : [$row[$propertyName]], $existingObjects);

                        $propertyValue = null;
                        if ($propertyResults) {
                            $propertyValue = $isArray ? $propertyResults : array_pop($propertyResults);
                        } else {
                            if ($isArray) $propertyValue = [];
                        }

                        $property->set($populateObject, $propertyValue);

                    }
                } else {
                    $columnName = $this->getColumnNameForProperty($property->getPropertyName());
                    if (isset($row[$columnName])) {
                        $this->mapColumnValueToProperty($row[$columnName], $property, $populateObject);
                    }
                }
            }

            $returnObjects[$pk] = $populateObject;
        }


        // If exisiting objects, we are following a save operation so run post save interceptors.
        // Otherwise run post map interceptors.
        if ($existingObjects) {
            $this->ormInterceptorProcessor->processPostSaveInterceptors($this->className, $returnObjects);
        } else {
            $returnObjects = $this->ormInterceptorProcessor->processPostMapInterceptors($this->className, $returnObjects);
        }


        return $returnObjects;

    }


    /**
     * Map objects to rows.  If an array of
     *
     * @param $objects
     */
    public function mapObjectsToRows($objects, $operationType = "SAVE") {

        $rows = [];
        foreach ($objects as $object) {

            if ($operationType == "SAVE")
                $this->ormInterceptorProcessor->processPreSaveInterceptors($this->className, $objects);
            else
                $this->ormInterceptorProcessor->processPreDeleteInterceptors($this->className, $objects);

            $row = [];

            foreach ($this->classInspector->getProperties() as $propertyName => $property) {

                $propertyValue = $property->get($object);
                $isArray = strpos($property->getType(), "[");

                if (isset($this->relatedEntities[$propertyName])) {
                    $relatedClassName = $this->relatedEntities[$propertyName];
                    $relatedMapper = self::get($relatedClassName);
                    if ($isArray) {
                        $items = is_array($propertyValue) ? $propertyValue : [];
                        $rowData = [];
                        foreach ($items as $item) {
                            $rowData[] = $relatedMapper->mapObjectsToRows([$item], $operationType);
                        }
                        $row[$propertyName] = $rowData;
                    } else {
                        if ($propertyValue) {
                            $row[$propertyName] = $relatedMapper->mapObjectsToRows([$propertyValue], $operationType);
                        } else {
                            $row[$propertyName] = null;
                        }
                    }
                } else {

                    $columnName = $this->getColumnNameForProperty($property->getPropertyName());
                    $row[$columnName] = $propertyValue;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }


    /**
     * Map a column value to a property.
     *
     * @param string $columnValue
     * @param Property $property
     * @param mixed $targetObject
     */
    private function mapColumnValueToProperty($columnValue, $property, $targetObject) {

        $propertyValue = $columnValue;
        if (trim($property->getType(), "\\") == \DateTime::class) {
            $propertyValue = \DateTime::createFromFormat("Y-m-d", $columnValue);
            if (!$propertyValue) $propertyValue = \DateTime::createFromFormat("Y-m-d H:i:s", $columnValue);
        }

        $property->set($targetObject, $propertyValue);
    }


    /**
     * Process post delete logic
     *
     * @param array $deleteRows
     */
    public function processPostDelete($deleteRows) {

        $this->ormInterceptorProcessor->processPostDeleteInterceptors($this->className, $deleteRows);

    }


    // Generate the underlying table mapping
    private function generateTableMapping() {

        // Gather common items for below.
        $classAnnotations = $this->classInspector->getClassAnnotationsObject();
        $properties = $this->classInspector->getProperties();

        // Calculate the table name and primary keys etc up front.
        $customTableName = $classAnnotations->getClassAnnotationForMatchingTag("table");
        $tableName = $customTableName ? $customTableName->getValue() : $this->camelCaseToUnderscore($this->classInspector->getShortClassName());
        $tableMapping = new TableMapping($tableName);


        // Resolve any relationships for each type
        $relationships = [];

        // MANY TO MANY
        $manyToManyFields = $classAnnotations->getFieldAnnotationsContainingMatchingTag("manyToMany");
        foreach ($manyToManyFields as $field => $annotations) {
            $relatedType = trim($properties[$field]->getType(), "[]");
            $linkTableName = isset($annotations["linkTable"][0]) ? $annotations["linkTable"][0]->getValue() : $this->camelCaseToUnderscore($this->className . $relatedType);
            $relationships[] = new ManyToManyTableRelationship(self::get($relatedType)->getTableMapping(), $field, $linkTableName);
            $this->relatedEntities[$field] = $relatedType;
        }

        // ONE TO MANY / ONE TO ONE
        $toOneFields = $classAnnotations->getFieldAnnotationsContainingMatchingTag("oneToMany");
        $toOneFields = array_merge($toOneFields, $classAnnotations->getFieldAnnotationsContainingMatchingTag("oneToOne"));
        foreach ($toOneFields as $field => $annotations) {
            $relatedType = trim($properties[$field]->getType(), "[]");

            $relatedColumns = [];
            if (isset($annotations["childJoinColumns"][0])) {
                $relatedColumns = explode(",", str_replace(" ", "", $annotations["childJoinColumns"][0]->getValue()));
            } else {
                $pkNames = $tableMapping->getPrimaryKeyColumnNames();
                foreach ($pkNames as $name) {
                    $relatedColumns[] = $tableName . "_" . $name;
                }
            }

            if (isset($annotations["oneToMany"]))
                $relationships[] = new OneToManyTableRelationship(self::get($relatedType)->getTableMapping(), $field, $relatedColumns);
            else
                $relationships[] = new OneToOneTableRelationship(self::get($relatedType)->getTableMapping(), $field, $relatedColumns);

            $this->relatedEntities[$field] = $relatedType;
        }

        // MANY TO ONE
        $manyToOneFields = $classAnnotations->getFieldAnnotationsContainingMatchingTag("manyToOne");
        foreach ($manyToOneFields as $field => $annotations) {
            $relatedType = trim($properties[$field]->getType(), "[]");
            $relatedTableMapping = self::get($relatedType)->getTableMapping();
            $relatedColumns = [];
            if (isset($annotations["parentJoinColumns"][0])) {
                $relatedColumns = explode(",", str_replace(" ", "", $annotations["parentJoinColumns"][0]->getValue()));
            } else {
                $pkNames = $relatedTableMapping->getPrimaryKeyColumnNames();
                $relatedTableName = $relatedTableMapping->getTableName();
                foreach ($pkNames as $name) {
                    $relatedColumns[] = $relatedTableName . "_" . $name;
                }
            }
            $relationships[] = new ManyToOneTableRelationship($relatedTableMapping, $field, $relatedColumns);
            $this->relatedEntities[$field] = $relatedType;
        }


        $this->tableMapping = new TableMapping($tableName, $relationships);


    }


    // Get a column name for a property for this class.
    private function getColumnNameForProperty($propertyName) {
        $properties = $this->classInspector->getProperties();
        if (!isset($properties[$propertyName])) return null;
        $property = $properties[$propertyName];
        $customColumnName = isset($property->getPropertyAnnotations()["column"]) ? $property->getPropertyAnnotations()["column"][0]->getValue() : null;
        return $customColumnName ?? $this->camelCaseToUnderscore($property->getPropertyName());
    }


    // Convert a camel case item to underscore.
    private function camelCaseToUnderscore($camelCase) {
        return strtolower(substr($camelCase, 0, 1) . preg_replace("/([A-Z0-9])/", "_$1", substr($camelCase, 1)));
    }


}
