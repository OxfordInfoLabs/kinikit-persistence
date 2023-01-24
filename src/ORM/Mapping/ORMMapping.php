<?php


namespace Kinikit\Persistence\ORM\Mapping;

use Kinicart\Objects\Account\Account;
use Kinikit\Core\Binding\ObjectBinder;
use Kinikit\Core\Binding\ObjectBindingException;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Logging\Logger;
use Kinikit\Core\Reflection\ClassInspector;
use Kinikit\Core\Reflection\ClassInspectorProvider;
use Kinikit\Core\Reflection\Property;
use Kinikit\Core\Util\Primitive;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\Database\MetaData\UpdatableTableMetaData;
use Kinikit\Persistence\ORM\Interceptor\ORMInterceptorProcessor;
use Kinikit\Persistence\TableMapper\Mapper\TableMapping;
use Kinikit\Persistence\TableMapper\Mapper\TableQueryEngine;
use Kinikit\Persistence\TableMapper\Relationship\ManyToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\ManyToOneTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToOneTableRelationship;

class ORMMapping {

    /**
     * @var TableMapping
     */
    private $writeTableMapping;

    /**
     * @var TableMapping
     */
    private $readTableMapping;

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
     * @var ObjectBinder
     */
    private $objectBinder;


    /**
     * List of related entities for this class.  This
     * maps property names to class names.
     *
     * @var string[string]
     */
    private $relatedEntities = [];


    /**
     * Generated meta data, cached for performance and update.
     *
     * @var TableMetaData
     */
    private $generatedMetaData = null;


    /**
     * @var ORMMapping[]
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
        $this->objectBinder = Container::instance()->get(ObjectBinder::class);
    }


    /**
     * Get an ORM Mapping by class name - maintains an array for efficiency.
     *
     * @param $className
     * @return ORMMapping
     */
    public static function get($className) {
        $className = trim($className, "\\");
        if (!isset(self::$ormMappings[$className])) {

            // Handle mapped classes.
            $mappedClassName = Container::instance()->getClassMapping($className);

            self::$ormMappings[$className] = new ORMMapping($mappedClassName);
            self::$ormMappings[$className]->generateTableMapping();
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
    public function getWriteTableMapping() {
        return $this->writeTableMapping;
    }


    /**
     * Get the read table mapping (falls back to the write mapping if none defined).
     *
     * @return TableMapping
     */
    public function getReadTableMapping() {
        return $this->readTableMapping ?? $this->writeTableMapping;
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

            if (isset($this->relatedEntities[$propertyName]))
                continue;

            $offset = 0;

            while (preg_match("/(?<![\\w'])($propertyName)(?![\\w'])/", $string, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                if (isset($matches[0][1])) {
                    $columnName = $this->getColumnNameForProperty($matches[0][0]);
                    $position = $matches[0][1];
                    if (substr($string, $position - 1, 1) != ".")
                        $columnName = "_X." . $columnName;
                    $string = substr($string, 0, $position) . $columnName . substr($string, $position + strlen($matches[0][0]));
                }

                $offset = $position + strlen($matches[0][0]);
            }

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

            $string = preg_replace_callback("/(?<![\\w'])($columnName)(?![\\w'])/", function () use ($propertyName) {
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

            $className = trim($this->classInspector->getClassName(), "[]");
            $populateObject = isset($existingObjects[sizeof($returnObjects)]) ? $existingObjects[sizeof($returnObjects)] : Container::instance()->new($className);

            foreach ($this->classInspector->getProperties() as $propertyName => $property) {

                $isArray = strpos($property->getType(), "[");

                if (isset($this->relatedEntities[$propertyName])) {

                    $relatedClassName = $this->relatedEntities[$propertyName];
                    if (isset($row[$propertyName])) {

                        // Work out if single item.
                        $mapper = self::get($relatedClassName);


                        $populateProperty = $property->get($populateObject);

                        $existingPropertyObjects = $populateProperty ? ($isArray ? $populateProperty : [$populateProperty]) : null;

                        $propertyResults = $mapper->mapRowsToObjects($isArray ? $row[$propertyName] : [$row[$propertyName]], $existingPropertyObjects);

                        $propertyValue = null;
                        if ($propertyResults) {
                            $propertyValue = $isArray ? $propertyResults : array_pop($propertyResults);
                        } else {
                            if ($isArray) $propertyValue = [];
                        }

                        $property->set($populateObject, $propertyValue);

                    } else if ($isArray) {
                        $property->set($populateObject, []);
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

                if ($property->isStatic())
                    continue;

                $propertyValue = $property->get($object);
                $isArray = strpos($property->getType(), "[");

                if (isset($this->relatedEntities[$propertyName])) {

                    // Continue if read only relationship.
                    if (isset($property->getPropertyAnnotations()["readOnly"]))
                        continue;

                    $relatedClassName = $this->relatedEntities[$propertyName];
                    $relatedMapper = self::get($relatedClassName);
                    if ($isArray) {
                        $items = is_array($propertyValue) ? $propertyValue : [];
                        $row[$propertyName] = $relatedMapper->mapObjectsToRows($items, $operationType);
                    } else {
                        if ($propertyValue) {
                            $mappedRows = $relatedMapper->mapObjectsToRows([$propertyValue], $operationType);
                            $row[$propertyName] = $mappedRows[0];
                        } else {
                            $row[$propertyName] = null;
                        }
                    }
                } else {

                    if (!isset($property->getPropertyAnnotations()["unmapped"])) {
                        $columnName = $this->getColumnNameForProperty($property->getPropertyName());
                        $row[$columnName] = $this->mapPropertyToColumnValue($propertyValue, $property);
                    }
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }


    /**
     * Process post delete logic
     *
     * @param array $deleteRows
     */
    public function processPostDelete($deleteRows) {

        $this->ormInterceptorProcessor->processPostDeleteInterceptors($this->className, $deleteRows);

    }


    /**
     * Generate table meta data based upon the defined properties for e.g.
     * schema generation.  This returns an array of Table Meta Data objects
     * indexed by table name.  This is to allow multiple items to be returned in
     * e.g. Many to Many relationships where a link table needs to be created in
     * addition to the primary table.
     *
     * @return TableMetaData[string]
     */
    public function generateTableMetaData() {

        if ($this->generatedMetaData === null) {

            $this->generatedMetaData = [];

            $properties = $this->classInspector->getProperties();

            $tableName = $this->writeTableMapping->getTableName();

            // Loop through each property.
            $columns = [];
            $pkColumns = [];
            foreach ($properties as $propertyName => $property) {

                if ($property->isStatic())
                    continue;

                if (!isset($this->relatedEntities[$propertyName])) {

                    $annotations = $property->getPropertyAnnotations();
                    if (!isset($annotations["unmapped"])) {

                        // Gather column metrics.
                        $columnName = $this->getColumnNameForProperty($propertyName);

                        $primaryKey = isset($annotations["primaryKey"]);
                        $autoIncrement = isset($annotations["autoIncrement"]);
                        $required = isset($annotations["required"]);
                        $columnPrecision = null;

                        if (isset($annotations["sqlType"])) {

                            $sqlType = $annotations["sqlType"][0]->getValue();
                            $explodedType = explode("(", $sqlType);
                            $columnType = $explodedType[0];
                            $columnLength = null;
                            if (sizeof($explodedType) > 1) {
                                $params = explode(")", $explodedType[1])[0];
                                $explodedParams = explode(",", $params);
                                $columnLength = trim($explodedParams[0]);
                                if (sizeof($explodedParams) > 1)
                                    $columnPrecision = trim($explodedParams[1]);
                            }

                        } else {
                            $columnType = TableColumn::SQL_VARCHAR;
                            $columnLength = isset($annotations["maxLength"]) ? (int)$annotations["maxLength"][0]->getValue() : null;


                            switch ($property->getType()) {
                                case "integer":
                                case "int":
                                    $columnType = TableColumn::SQL_INTEGER;
                                    break;
                                case "float":
                                case "double":
                                    $columnType = TableColumn::SQL_FLOAT;
                                    break;
                                case "bool":
                                case "boolean":
                                    $columnType = TableColumn::SQL_TINYINT;
                                    break;
                                case "\\" . \DateTime::class:
                                    $columnType = TableColumn::SQL_DATE_TIME;
                                    break;
                            }
                        }
                        $columns[$columnName] = new TableColumn($columnName, $columnType, $columnLength, $columnPrecision, null, $primaryKey, $autoIncrement, $required);

                        if ($primaryKey) {
                            $pkColumns[] = $columns[$columnName];
                        };

                    }

                }
            }
            if (!$pkColumns && isset($columns["id"])) {
                $columns["id"] = new TableColumn("id", TableColumn::SQL_INTEGER, null, null, null, true, true, true);
                $pkColumns[] = $columns["id"];
            }

            // Create meta data to prevent issues downstream in relationships.
            $metaData = new UpdatableTableMetaData($tableName, $columns);
            $this->generatedMetaData[$tableName] = $metaData;

            // Now, process any relationship data.
            foreach ($this->writeTableMapping->getRelationships() as $relationship) {
                $relatedMetaData = self::get($this->relatedEntities[$relationship->getMappedMember()])->generateTableMetaData();
                $relatedMetaData = array_pop($relatedMetaData);

                // Add management columns to our set in many to one situations.
                if ($relationship instanceof ManyToOneTableRelationship) {
                    $parentJoinColumnNames = $relationship->getParentJoinColumnNames();

                    $index = 0;
                    foreach ($relatedMetaData->getPrimaryKeyColumns() as $primaryKeyColumn) {
                        if (isset($parentJoinColumnNames[$index])) {
                            $columnName = $parentJoinColumnNames[$index];
                            if (!isset($columns[$columnName])) {
                                $columns[$columnName] = new TableColumn($columnName, $primaryKeyColumn->getType(), $primaryKeyColumn->getLength(), $primaryKeyColumn->getPrecision(),
                                    $primaryKeyColumn->getDefaultValue(), false, false, false);
                                $metaData->addColumn($columns[$columnName]);
                            }
                        }
                        $index++;
                    }
                } // Inject management columns to the related type in OneTo* relationships.
                else if ($relationship instanceof OneToOneTableRelationship ||
                    $relationship instanceof OneToManyTableRelationship
                ) {

                    $childJoinColumnNames = $relationship->getChildJoinColumnNames();
                    $index = 0;
                    foreach ($pkColumns as $pkColumn) {
                        if (isset($childJoinColumnNames[$index])) {
                            $columnName = $childJoinColumnNames[$index];
                            if (!isset($relatedMetaData->getColumns()[$columnName]))
                                $relatedMetaData->addColumn(new TableColumn($columnName,
                                    $pkColumn->getType(), $pkColumn->getLength(), $pkColumn->getPrecision(),
                                    $pkColumn->getDefaultValue(), false, false, false));
                        }
                        $index++;
                    }


                } // Add a link table meta data if many to many.
                else if ($relationship instanceof ManyToManyTableRelationship) {

                    $linkColumns = [];

                    // Create link columns for our pk
                    foreach ($pkColumns as $pkColumn) {
                        $linkColumns[] = new TableColumn($tableName . "_" . $pkColumn->getName(),
                            $pkColumn->getType(), $pkColumn->getLength(), $pkColumn->getPrecision(), $pkColumn->getDefaultValue(), true, false, true);
                    }

                    // Create link columns for related pk
                    foreach ($relatedMetaData->getPrimaryKeyColumns() as $pkColumn) {
                        $linkColumns[] = new TableColumn($relatedMetaData->getTableName() . "_" . $pkColumn->getName(),
                            $pkColumn->getType(), $pkColumn->getLength(), $pkColumn->getPrecision(), $pkColumn->getDefaultValue(), true, false, true);
                    }

                    $linkTableMetaData = new TableMetaData($relationship->getLinkTableName(), $linkColumns);
                    $this->generatedMetaData[$relationship->getLinkTableName()] = $linkTableMetaData;

                }
            }


        }
        return $this->generatedMetaData;

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
        } else if (isset($property->getPropertyAnnotations()["json"])) {
            try {
                $propertyValue = json_decode($columnValue, true);
                $propertyValue = $this->objectBinder->bindFromArray($propertyValue, $property->getType(), false);
            } catch (ObjectBindingException $e) {
                $propertyValue = null;
            }
        } else {
            $propertyValue = Primitive::convertToPrimitive($property->getType(), $propertyValue);
        }

        $property->set($targetObject, $propertyValue);
    }


    private function mapPropertyToColumnValue($propertyValue, $property) {
        $columnValue = $propertyValue;
        if ($propertyValue instanceof \DateTime) {
            $columnValue = $propertyValue->format("Y-m-d H:i:s");
        } else if (isset($property->getPropertyAnnotations()["json"])) {
            $columnValue = $this->objectBinder->bindToArray($propertyValue, false);
            $columnValue = json_encode($columnValue);
        }

        return $columnValue;
    }


    // Generate the underlying table mapping
    private function generateTableMapping() {


        // Gather common items for below.
        $classAnnotations = $this->classInspector->getClassAnnotationsObject();
        $properties = $this->classInspector->getProperties();

        // Calculate the table name and primary keys etc up front.
        $customTableName = $classAnnotations->getClassAnnotationForMatchingTag("table");
        $tableName = $customTableName ? $customTableName->getValue() : $this->camelCaseToUnderscore($this->classInspector->getShortClassName());

        // Derive primary keys from object
        $primaryKeyColumns = [];
        foreach ($properties as $property) {
            $annotations = $property->getPropertyAnnotations();
            if (isset($annotations["primaryKey"])) {
                $primaryKeyColumns[] = $this->getColumnNameForProperty($property->getPropertyName());
            }
        }


        $this->writeTableMapping = new TableMapping($tableName, [], null, $primaryKeyColumns);
        $this->readTableMapping = new TableMapping($tableName, [], null, $primaryKeyColumns);


        // Resolve any relationships for each type
        $writeRelationships = [];
        $readRelationships = [];

        // MANY TO MANY
        $manyToManyFields = $classAnnotations->getFieldAnnotationsContainingMatchingTag("manyToMany");
        foreach ($manyToManyFields as $field => $annotations) {


            $relatedType = trim($properties[$field]->getType(), "[]");

            $relatedORM = self::get($relatedType);

            if ($relatedORM) {

                $linkTableName = isset($annotations["linkTable"][0]) ? $annotations["linkTable"][0]->getValue() : $this->camelCaseToUnderscore($this->className . $relatedType);

                if (!isset($annotations["readOnly"])) {
                    $writeRelationships[] = new ManyToManyTableRelationship($relatedORM->getWriteTableMapping(), $field, $linkTableName);
                }

                $manyToManyTableRelationship = new ManyToManyTableRelationship($relatedORM->getReadTableMapping(), $field, $linkTableName);

                // Ensure we set max depth if required.
                if (isset($annotations["maxDepth"])) $manyToManyTableRelationship->setMaxDepth($annotations["maxDepth"][0]->getValue());

                $readRelationships[] = $manyToManyTableRelationship;

                $this->relatedEntities[$field] = $relatedType;
            }
        }

        // ONE TO MANY / ONE TO ONE
        $toOneFields = $classAnnotations->getFieldAnnotationsContainingMatchingTag("oneToMany");
        $toOneFields = array_merge($toOneFields, $classAnnotations->getFieldAnnotationsContainingMatchingTag("oneToOne"));
        foreach ($toOneFields as $field => $annotations) {
            $relatedType = trim($properties[$field]->getType(), "[]");
            $relatedORM = self::get($relatedType);

            if ($relatedORM) {

                $relatedColumns = $this->expandRelationshipJoinColumnsString(isset($annotations["childJoinColumns"][0]) ? $annotations["childJoinColumns"][0]->getValue() : null,
                    $tableName, $this->writeTableMapping->getPrimaryKeyColumnNames());


                if (isset($annotations["oneToMany"])) {
                    if (!isset($annotations["readOnly"]))
                        $writeRelationships[] = new OneToManyTableRelationship($relatedORM->getWriteTableMapping(), $field, $relatedColumns);


                    $oneToManyTableRelationship = new OneToManyTableRelationship($relatedORM->getReadTableMapping(), $field, $relatedColumns);

                    // Ensure we set max depth if required.
                    if (isset($annotations["maxDepth"])) {
                        $oneToManyTableRelationship->setMaxDepth($annotations["maxDepth"][0]->getValue());
                    }

                    $readRelationships[] = $oneToManyTableRelationship;
                } else {

                    if (!isset($annotations["readOnly"]))
                        $writeRelationships[] = new OneToOneTableRelationship($relatedORM->getWriteTableMapping(), $field, $relatedColumns);


                    $oneToOneTableRelationship = new OneToOneTableRelationship($relatedORM->getReadTableMapping(), $field, $relatedColumns);

                    // Ensure we set max depth if required.
                    if (isset($annotations["maxDepth"])) $oneToOneTableRelationship->setMaxDepth($annotations["maxDepth"][0]->getValue());


                    $readRelationships[] = $oneToOneTableRelationship;

                }


                $this->relatedEntities[$field] = $relatedType;
            }
        }

        // MANY TO ONE
        $manyToOneFields = $classAnnotations->getFieldAnnotationsContainingMatchingTag("manyToOne");
        foreach ($manyToOneFields as $field => $annotations) {
            $relatedType = trim($properties[$field]->getType(), "[]");
            $relatedORM = self::get($relatedType);

            if ($relatedORM) {

                $relatedTableMapping = $relatedORM->getWriteTableMapping();

                // Expand join columns
                $relatedColumns = $this->expandRelationshipJoinColumnsString(isset($annotations["parentJoinColumns"][0]) ? $annotations["parentJoinColumns"][0]->getValue() : null,
                    $relatedTableMapping->getTableName(),
                    $relatedTableMapping->getPrimaryKeyColumnNames());

                if (!isset($annotations["readOnly"])) {
                    $writeRelationships[] = new ManyToOneTableRelationship($relatedTableMapping, $field, $relatedColumns, isset($annotations["saveCascade"]),isset($annotations["deleteCascade"]));

                }


                $manyToOneTableRelationship = new ManyToOneTableRelationship($relatedORM->getReadTableMapping(), $field, $relatedColumns);

                // Ensure we set max depth if required.
                if (isset($annotations["maxDepth"])) {
                    $manyToOneTableRelationship->setMaxDepth($annotations["maxDepth"][0]->getValue());
                }

                $readRelationships[] = $manyToOneTableRelationship;


                $this->relatedEntities[$field] = $relatedType;
            }
        }


        // Create read and write table mappings
        $this->writeTableMapping->setRelationships($writeRelationships);
        $this->readTableMapping->setRelationships($readRelationships);


    }

    // Expand join columns string into an array of mapped columns.
    private function expandRelationshipJoinColumnsString($joinColumnsString, $fallbackColumnPrefix, $fallbackPrimaryKeyColumns = []) {

        $relatedColumns = [];

        if ($joinColumnsString) {

            $explodedEntries = explode(",", str_replace(" ", "", $joinColumnsString));
            $relatedColumns = [];
            foreach ($explodedEntries as $explodedEntry) {
                $splitColumn = explode("=>", $explodedEntry);
                if (sizeof($splitColumn) == 2) {
                    $relatedColumns[$splitColumn[1]] = $splitColumn[0];
                } else {
                    $relatedColumns[] = $splitColumn[0];
                }
            }

        } else {
            foreach ($fallbackPrimaryKeyColumns as $name) {
                $relatedColumns[] = $fallbackColumnPrefix . "_" . $name;
            }
        }

        return $relatedColumns;

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
