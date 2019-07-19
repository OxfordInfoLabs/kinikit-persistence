<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\SchemaGenerator;

use Kinikit\Core\Configuration;
use Kinikit\Core\Util\Annotation\ClassAnnotationParser;
use Kinikit\Core\Util\Annotation\ClassAnnotations;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Connection\TableColumn;
use Kinikit\Persistence\Database\Connection\TableMetaData;
use Kinikit\Persistence\Database\Exception\SQLException;
use Kinikit\Persistence\UPF\Engines\ORM\Utils\ORMUtils;
use Kinikit\Persistence\UPF\Framework\ObjectMapper;
use Kinikit\Persistence\UPF\Object\ActiveRecord;

/**
 * Generate a SQL table definition from
 *
 * Class SchemaGenerator
 */
class SchemaGenerator {

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;

    /**
     * Construct with a database connection.  Useful for testing etc.
     *
     * SchemaGenerator constructor.
     * @param $databaseConnection
     */
    public function __construct($databaseConnection) {
        $this->databaseConnection = $databaseConnection;
    }


    /**
     * Create the schema including inserting into the database using the currently configured
     * database connection.  By default this will drop tables before attempting to create.
     *
     * @param string $rootPath
     * @param null $rootPathNamespace
     */
    public function createSchema($rootPath = "./Objects", $rootPathNamespace = null, $dropTables = true) {

        $tableDefinitions = $this->generateTableDefinitionsForAllActiveRecordObjects($rootPath, $rootPathNamespace);

        foreach ($tableDefinitions as $definition) {

            // Drop unless flag supplied as false
            if ($dropTables) {
                try {
                    $this->databaseConnection->query("DROP TABLE {$definition->getTableName()}",);
                } catch (SQLException $e) {
                    // Continue as likely table doesn't exist.
                }
            }

            // Create the table from the meta data definition.
            $this->databaseConnection->createTable($definition);

        }


    }


    /**
     * Generate all table definitions for all filesystem objects starting at a root path.  If no namespace is supplied
     * it will default to the configured application namespace appended with \Objects
     *
     * @param string $rootPath
     * @param string $rootPathNamespace
     *
     * @return TableMetaData[]
     */
    public function generateTableDefinitionsForAllActiveRecordObjects($rootPath = "./Objects", $rootPathNamespace = null) {

        if (!$rootPathNamespace) {
            $rootPathNamespace = Configuration::readParameter("application.namespace") . "\\Objects";
        }

        $tableDefinitions = array();

        if (file_exists($rootPath)) {

            $directory = new \DirectoryIterator($rootPath);
            foreach ($directory as $item) {

                if ($item->isDot())
                    continue;

                // if this is a class file, check it.
                if ($item->getExtension() == "php") {
                    $className = $rootPathNamespace . "\\" . $item->getBasename(".php");
                    if (class_exists($className)) {
                        $reflectionClass = new \ReflectionClass($className);
                        if ($reflectionClass->isSubclassOf(ActiveRecord::class)) {

                            $classAnnotations = ClassAnnotationParser::instance()->parse($className);
                            if (!$classAnnotations->getClassAnnotationForMatchingTag("noGeneratedTable")) {
                                $tableDefinitions[] = $this->generateTableDefinitionForClass($className);
                            }
                        }
                    }
                }

                // If directory, run this recursively.
                if ($item->isDir()) {
                    $subDefs = $this->generateTableDefinitionsForAllActiveRecordObjects($rootPath . "/" . $item->getFilename(),
                        $rootPathNamespace . "\\" . $item->getFilename());

                    $tableDefinitions = array_merge($tableDefinitions, $subDefs);
                }


            }

        }

        return $tableDefinitions;

    }


    /**
     * Generate a create table for a class
     *
     * @param $className
     * @return TableMetaData
     */
    public function generateTableDefinitionForClass($className) {

        $mapper = new ObjectMapper($className);

        // Derive the name of the table to create.
        $explodedClassName = explode("\\", $className);
        $tableName = $mapper->getOrmTable() ? $mapper->getOrmTable() : ORMUtils::convertCamelCaseToUnderscore(array_pop($explodedClassName));

        $columns = array();
        $hasPkField = false;
        $idColumn = null;
        foreach ($mapper->getFields() as $field) {

            if ($field->getReadOnly() && !$field->getGeneratedColumn())
                continue;

            $columnName = $field->getOrmColumn() ? $field->getOrmColumn() : ORMUtils::convertCamelCaseToUnderscore($field->getFieldName());
            if (isset($field["ormType"])) {
                preg_match("/(.*)\((.*)?\)/", $field["ormType"], $matches);
                if (sizeof($matches) == 3) {
                    list($columnType, $columnLength) = array($matches[1], $matches[2]);
                } else {
                    list($columnType, $columnLength) = array($field["ormType"], null);
                }
            } else {
                list($columnType, $columnLength) = TableColumn::getSQLTypeForPHPType($field->getType());
                if ($field->getLength()) $columnLength = $field->getLength();
            }

            $notNull = $field->getRequired();
            $autoIncrement = $field->getAutoIncrement();
            $primaryKey = $field->getPrimaryKey();

            if ($primaryKey) $hasPkField = true;

            $column = new TableColumn($columnName, $columnType, $columnLength, "", $primaryKey, $autoIncrement, $notNull);

            if ($columnName == "id") $idColumn = $column;

            $columns[] = $column;
        }

        // If no pk field but an id column, set it to be pk, auto increment, required
        if (!$hasPkField && $idColumn) {
            $idColumn->setNotNull(true);
            $idColumn->setPrimaryKey(true);
            $idColumn->setAutoIncrement(true);
        }


        return new TableMetaData($tableName, $columns);

    }


}
