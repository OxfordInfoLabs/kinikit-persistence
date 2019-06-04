<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Utils;

use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Connection\TableMetaData;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMAmbiguousMapperSourceDefinitionException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMColumnDoesNotExistException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMRelationshipColumnDoesNotExistException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMRelationshipTableDoesNotExistException;
use Kinikit\Persistence\UPF\Engines\ORM\Exception\ORMTableDoesNotExistException;
use Kinikit\Persistence\UPF\Framework\ObjectPersistableField;

/**
 * Miscellaneous utilities used by persistence framework.
 *
 * @author mark
 *
 */
class ORMUtils {

    /**
     * Convert a camel case string to underscore form for use in e.g.
     * database inserts,
     */
    public static function convertCamelCaseToUnderscore($inputString) {
        $inputString = preg_replace("/(.+)([A-Z])([a-z])/", "$1_$2$3", $inputString);
        $inputString = strtolower(preg_replace("/([a-z])([A-Z])/", "$1_$2", $inputString));

        return $inputString;
    }

    /**
     * Lookup the table meta data for a given object mapper.
     *
     * This returns an array containing the table meta data objects for reading and writing as these can be different.
     *
     * If no OrmTable is set upon the mapper
     * directly, we assume a table name using an _ convention. Throw appropriate
     * exception if a problem
     * finding the table
     *
     * @param
     *            $objectMapper
     * @return TableMetaData
     */
    public static function getTableMetaDataForMapper($objectMapper, $databaseConnection) {

        // If we have both a view SQL and table definition, throw an ambiguous
        // source exception.
        if (($objectMapper->getOrmNoBackingObject() && ($objectMapper->getOrmViewSQL() || $objectMapper->getOrmTable() || $objectMapper->getOrmView()))
        ) {
            throw new ORMAmbiguousMapperSourceDefinitionException ($objectMapper->getClassName());
        }

        $tableMetaData = array();

        // If there is view SQL, return out with faux table meta-data.
        if ($objectMapper->getOrmViewSQL() || $objectMapper->getOrmNoBackingObject()) {


            // Loop through all fields defined, making a conversion from camel
            // case as required.
            $columns = array();
            foreach ($objectMapper->getFieldsByName() as $fieldName => $field) {
                $columnName = $field->getOrmColumn() ? $field->getOrmColumn() : ORMUtils::convertCamelCaseToUnderscore($fieldName);
                $columns [$columnName] = new TableColumn ($columnName, TableColumn::SQL_VARCHAR, 255);
            }

            if ($objectMapper->getOrmViewSQL()) {
                $tableMetaData["READ"] = new TableMetaData ("(" . $objectMapper->getOrmViewSQL() . ") X", $columns);
            } else {
                $tableMetaData["READ"] = new TableMetaData("", $columns);
            }
        }

        if ($objectMapper->getOrmView()) {

            $viewName = $objectMapper->getOrmView();

            // Get the view meta data
            try {
                $tableMetaData["READ"] = $databaseConnection->getTableMetaData($viewName);
            } catch (SQLException $e) {
                if (strpos($e->getMessage(), "doesn't exist")) {
                    throw new ORMTableDoesNotExistException ($objectMapper->getClassName(), $viewName);
                } else {
                    throw $e;
                }
            }

        }


        if ($objectMapper->getOrmTable() || (!$objectMapper->getReadOnly() && !$objectMapper->getOrmViewSQL() && !$objectMapper->getOrmNoBackingObject())) {

            // Grab the table name.
            if ($objectMapper->getOrmTable()) {
                $tableName = $objectMapper->getOrmTable();
            } else {
                $explodedTableName = explode("\\", $objectMapper->getClassName());
                $tableName = array_pop($explodedTableName);
                $tableName = ORMUtils::convertCamelCaseToUnderscore(strtolower($tableName [0]) . substr($tableName, 1));
            }
            // Get the table meta data
            try {
                $tableMetaData["WRITE"] = $databaseConnection->getTableMetaData($tableName);

            } catch (SQLException $e) {
                if (strpos($e->getMessage(), "Table") && strpos($e->getMessage(), "doesn't exist")) {
                    throw new ORMTableDoesNotExistException ($objectMapper->getClassName(), $tableName);
                }
            }

        }


        // If no read meta data resolved, set it to the same as write.
        if (!isset($tableMetaData["READ"]))
            $tableMetaData["READ"] = $tableMetaData["WRITE"];


        return $tableMetaData;
    }

    /**
     * Convert an array of field values keyed in by field name into a structured
     * array also keyed in by field name of entries where each entry is a 2
     * value array as follows
     * [0] => TableColumn object representing the actual column which is
     * discovered either by the OrmColumn property on the field or by an _
     * convention.
     *
     * [1] => the field value returned intact.
     *
     * @param
     *            $tableMetaData
     * @param
     *            $arrayOfFieldValues
     */
    public static function getFieldColumnValueMapForMapperTableAndValues($objectMapper, $tableMetaData, $arrayOfFieldValues, $prepareValuesForSQL = true) {

        $tableName = $tableMetaData["WRITE"]->getTableName();

        $columnMap = array();
        foreach ($arrayOfFieldValues as $fieldName => $fieldValue) {

            $fieldsByName = $objectMapper->getFieldsByName();
            $columnField = isset ($fieldsByName [$fieldName]) ? $fieldsByName [$fieldName] : null;

            if (!$columnField || !$columnField->getReadOnly()) {

                $columnName = ($columnField && $columnField->getOrmColumn()) ? $columnField->getOrmColumn() : ORMUtils::convertCamelCaseToUnderscore($fieldName);
                $columnMetaData = $tableMetaData["WRITE"]->getColumn($columnName);

                // Throw nicely if no column exists to bind to.
                if (!$columnMetaData)
                    throw new ORMColumnDoesNotExistException ($objectMapper->getClassName(), $fieldName, $tableName, $columnName);

                $columnMap [$fieldName] = array($columnField, $columnMetaData, $prepareValuesForSQL ? $columnMetaData->getSQLValue($fieldValue) : $fieldValue);

            }
        }

        return $columnMap;

    }

    /**
     * Get the table info including any statically defined columns data using
     * the object mapper and database connection
     *
     * @param $objectMapper ObjectMapper
     * @param $databaseConnection DatabaseConnection
     *
     * @return ORMTableInfo
     */
    public static function getStaticObjectTableInfo($objectMapper, $databaseConnection) {

        // Get the table meta data
        $tableMetaData = ORMUtils::getTableMetaDataForMapper($objectMapper, $databaseConnection);


        $allStaticFields = $objectMapper->getFields() ? $objectMapper->getFields() : $objectMapper->getPrimaryKeyFields();

        $fieldColumnMapRead = array();
        $fieldColumnMapWrite = array();

        if ($allStaticFields) {
            foreach ($allStaticFields as $field) {

                $field = is_object($field) ? $field : new ObjectPersistableField ($field);

                if ($field->getRelationship() && $field->getRelationship()->getIsMultiple())
                    continue;

                $targetColumnName = ($field && $field->getOrmColumn()) ? $field->getOrmColumn() : ORMUtils::convertCamelCaseToUnderscore($field->getFieldName());

                // Handle READ and WRITE scenarios seperately.
                $targetColumnRead = $tableMetaData["READ"]->getColumn($targetColumnName);

                // Throw if a problem
                if (!$targetColumnRead)
                    throw new ORMColumnDoesNotExistException ($objectMapper->getClassName(), $field->getFieldName(), $tableMetaData["READ"]->getTableName(), $targetColumnName);
                else
                    $fieldColumnMapRead [$field->getFieldName()] = $targetColumnRead;


                if (isset($tableMetaData["WRITE"]) && !$field->getReadOnly()) {
                    $targetColumnWrite = $tableMetaData["WRITE"]->getColumn($targetColumnName);

                    // Throw if a problem
                    if (!$targetColumnWrite)
                        throw new ORMColumnDoesNotExistException ($objectMapper->getClassName(), $field->getFieldName(), $tableMetaData["WRITE"]->getTableName(), $targetColumnName);
                    else
                        $fieldColumnMapWrite [$field->getFieldName()] = $targetColumnWrite;
                }
            }
        }

        $returnArray = array("READ" => new ORMTableInfo ($tableMetaData["READ"], $fieldColumnMapRead));

        if (isset($tableMetaData["WRITE"]))
            $returnArray["WRITE"] = new ORMTableInfo($tableMetaData["WRITE"], $fieldColumnMapWrite);

        return $returnArray;

    }

    /**
     * Get the meta data for an in use relational table, derived from the parent
     * and child mappers and the defined relationship.
     * Essentially, we expect a relational table to exist following a naming
     * convention (parent table _ child table) if no explicit table
     * has been defined against the relationship.
     *
     * @param $parentMapper ObjectMapper
     * @param $childMapper ObjectMapper
     * @param $relationship ObjectPersistableFieldRelationship
     * @param $databaseConnection BaseConnection
     *
     * @return ORMTableInfo
     */
    public static function getRelationalTableInfo($parentMapper, $childMapper, $relationship, $databaseConnection) {

        // Grab the parent table meta data as well.
        $parentTableMetaData = ORMUtils::getTableMetaDataForMapper($parentMapper, $databaseConnection);
        $childTableMetaData = ORMUtils::getTableMetaDataForMapper($childMapper, $databaseConnection);

        // Create the table name from the parent table name concatenated with
        // the child table name.
        $tableName = $relationship->getOrmTable() ? $relationship->getOrmTable() : $parentTableMetaData->getTableName() . "_" . $childTableMetaData->getTableName();

        // Grab the meta data.
        try {
            $tableMetaData = $databaseConnection->getTableMetaData($tableName);
        } catch (SQLException $e) {
            if (strpos($e->getMessage(), "Table") && strpos($e->getMessage(), "doesn't exist")) {
                throw new ORMRelationshipTableDoesNotExistException ($parentMapper->getClassName(), $relationship->getRelatedClassName(), $tableName);
            }
        }

        $fieldColumnMappings = array();

        // Look up and evaluate the field -> column mappings.
        $parentPrimaryKeyFields = $parentMapper->getPrimaryKeyFields();
        $childPrimaryKeyFields = $childMapper->getPrimaryKeyFields();

        foreach ($parentPrimaryKeyFields as $field) {
            $field = is_object($field) ? $field : new ObjectPersistableField ($field);

            // Check for a parent relational field.
            $relationalField = $relationship->getRelationalFieldByNameAndType($field->getFieldName(), "parent");
            $targetColumnName = ($relationalField && $relationalField->getOrmColumn()) ? $relationalField->getOrmColumn() : ($parentTableMetaData->getTableName() . "_" . ORMUtils::convertCamelCaseToUnderscore($field->getFieldName()));
            $targetColumn = $tableMetaData->getColumn($targetColumnName);

            // Throw if a problem
            if (!$targetColumn)
                throw new ORMRelationshipColumnDoesNotExistException ($parentMapper->getClassName(), $relationship->getRelatedClassName(), $tableName, $targetColumnName);
            else
                $fieldColumnMappings ["Parent:" . $field->getFieldName()] = $targetColumn;
        }

        foreach ($childPrimaryKeyFields as $field) {
            $field = is_object($field) ? $field : new ObjectPersistableField ($field);

            // Check for a child relational field.
            $relationalField = $relationship->getRelationalFieldByNameAndType($field->getFieldName(), "child");
            $targetColumnName = ($relationalField && $relationalField->getOrmColumn()) ? $relationalField->getOrmColumn() : ($childTableMetaData->getTableName() . "_" . ORMUtils::convertCamelCaseToUnderscore($field->getFieldName()));
            $targetColumn = $tableMetaData->getColumn($targetColumnName);

            // Throw if a problem
            if (!$targetColumn)
                throw new ORMRelationshipColumnDoesNotExistException ($parentMapper->getClassName(), $relationship->getRelatedClassName(), $tableName, $targetColumnName);
            else
                $fieldColumnMappings ["Child:" . $field->getFieldName()] = $targetColumn;
        }

        // Return the info structure for use in the persistence engine above.
        return new ORMTableInfo ($tableMetaData, $fieldColumnMappings);

    }

}

?>
