<?php


namespace Kinikit\Persistence\ORM\Mapping;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Reflection\ClassInspector;
use Kinikit\Core\Reflection\ClassInspectorProvider;
use Kinikit\Persistence\TableMapper\Mapper\TableMapping;

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
     * Construct with a class name.
     *
     * ORMMapping constructor.
     *
     * @param $className
     */
    public function __construct($className) {
        $this->className = $className;
        $classInspectorProvider = Container::instance()->get(ClassInspectorProvider::class);
        $this->classInspector = $classInspectorProvider->getClassInspector($className);
        $this->generateTableMapping();
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
            $string = preg_replace_callback("/(?<![\\S'])($propertyName)(?![\\S'])/", function () use ($property, $propertyName) {
                $customColumnName = isset($property->getPropertyAnnotations()["column"]) ? $property->getPropertyAnnotations()["column"][0]->getValue() : null;
                $columnName = $customColumnName ?? $this->camelCaseToUnderscore($propertyName);
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
            $customColumnName = isset($property->getPropertyAnnotations()["column"]) ? $property->getPropertyAnnotations()["column"][0]->getValue() : null;
            $columnName = $customColumnName ?? $this->camelCaseToUnderscore($propertyName);

            $string = preg_replace_callback("/(?<![\\S'])($columnName)(?![\\S'])/", function () use ($propertyName) {
                return $propertyName;
            }, $string);

        }

        return $string;

    }


    /**
     * Map row data to objects  Pass an indicator as to whether or not this is a single item or multiple.
     *
     * @param $rowData
     * @param bool $singleItem
     */
    public function mapRowsToObjects($rowData, $singleItem = false, $existingObjects = null) {

        $rows = $singleItem ? [$rowData] : $rowData;

        // Loop through each row and map it.
        $returnObjects = [];
        foreach ($rows as $pk => $row) {

            $populateObject = isset($existingObjects[sizeof($returnObjects)]) ? $existingObjects[sizeof($returnObjects)] : $this->classInspector->createInstance([]);

            foreach ($this->classInspector->getProperties() as $property) {
                $customColumnName = isset($property->getPropertyAnnotations()["column"]) ? $property->getPropertyAnnotations()["column"][0]->getValue() : null;
                $columnName = $customColumnName ?? $this->camelCaseToUnderscore($property->getPropertyName());
                if (isset($row[$columnName])) {
                    $property->set($populateObject, $row[$columnName]);
                }
            }

            if ($singleItem)
                return $populateObject;
            else
                $returnObjects[$pk] = $populateObject;
        }

        return $returnObjects;

    }


    /**
     * Map objects to rows.  If an array of
     *
     * @param $objects
     */
    public function mapObjectsToRows($objects) {

        $rows = [];
        foreach ($objects as $object) {
            $row = [];

            foreach ($this->classInspector->getProperties() as $property) {
                $customColumnName = isset($property->getPropertyAnnotations()["column"]) ? $property->getPropertyAnnotations()["column"][0]->getValue() : null;
                $columnName = $customColumnName ?? $this->camelCaseToUnderscore($property->getPropertyName());
                $row[$columnName] = $property->get($object);
            }

            $rows[] = $row;
        }

        return $rows;
    }


    // Generate the underlying table mapping
    private function generateTableMapping() {
        $customTableName = $this->classInspector->getClassAnnotationsObject()->getClassAnnotationForMatchingTag("table");
        $this->tableMapping = new TableMapping($customTableName ? $customTableName->getValue() : $this->camelCaseToUnderscore($this->classInspector->getShortClassName()));
    }


    // Convert a camel case item to underscore.
    private function camelCaseToUnderscore($camelCase) {
        return strtolower(substr($camelCase, 0, 1) . preg_replace("/([A-Z0-9])/", "_$1", substr($camelCase, 1)));
    }


}
