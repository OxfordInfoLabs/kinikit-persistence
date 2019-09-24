<?php

namespace Kinikit\Persistence\ORM\SchemaGenerator;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\Reflection\ClassInspectorProvider;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\ORM\Mapping\ORMMapping;

/**
 * Schema Generator - attempts to generate schema for all objects found in the
 * passed directory (defaulting to the application Objects directory).
 *
 * @noProxy
 */
class SchemaGenerator {

    /**
     * @var ClassInspectorProvider
     */
    private $classInspectorProvider;

    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;


    /**
     * SchemaGenerator constructor.
     *
     * @param ClassInspectorProvider $classInspectorProvider
     * @param DatabaseConnection $databaseConnection
     */
    public function __construct($classInspectorProvider, $databaseConnection) {
        $this->classInspectorProvider = $classInspectorProvider;
        $this->databaseConnection = $databaseConnection;
    }


    /**
     * Get all matching objects
     *
     * @param string $rootPath
     * @param string $rootPathNamespace
     *
     * @return TableMetaData[]
     */
    public function generateTableMetaData($rootPath = "./Objects", $rootPathNamespace = null) {

        if (!$rootPathNamespace) {
            $rootPathNamespace = Configuration::readParameter("application.namespace") . "\\Objects";
        }

        $tableMetaData = array();

        if (file_exists($rootPath)) {

            $directory = new \DirectoryIterator($rootPath);
            foreach ($directory as $item) {

                if ($item->isDot())
                    continue;

                // if this is a class file, check it.
                if ($item->getExtension() == "php") {
                    $className = $rootPathNamespace . "\\" . $item->getBasename(".php");
                    if (class_exists($className)) {

                        // Read the table mapping
                        $classInspector = $this->classInspectorProvider->getClassInspector($className);
                        if (!isset($classInspector->getClassAnnotations()["noGenerate"])) {
                            $mapper = ORMMapping::get($className);
                            $tableMetaData = array_merge($tableMetaData, $mapper->generateTableMetaData());
                        }

                    }
                }

                // If directory, run this recursively.
                if ($item->isDir()) {
                    $subDefs = $this->generateTableMetaData($rootPath . "/" . $item->getFilename(),
                        $rootPathNamespace . "\\" . $item->getFilename());

                    $tableMetaData = array_merge($tableMetaData, $subDefs);
                }


            }

        }

        return $tableMetaData;

    }

    /**
     * Create schema for objects starting at the root path and namespace.
     *
     * @param string $rootPath
     * @param null $rootPathNamespace
     */
    public function createSchema($rootPath = "./Objects", $rootPathNamespace = null, $dropIfExists = true) {

        // Get the generated meta data.
        $generatedMetaData = $this->generateTableMetaData($rootPath, $rootPathNamespace);

        // Now loop through and create the schema using the default database connection.
        foreach ($generatedMetaData as $tableMetaData) {

            $sql = "";
            if ($dropIfExists) {
                $sql = "DROP TABLE IF EXISTS {$tableMetaData->getTableName()};";
            }

            $sql .= "CREATE TABLE {$tableMetaData->getTableName()} (\n";

            $columnLines = array();
            $pks = array();
            foreach ($tableMetaData->getColumns() as $column) {

                $line = $column->getName() . " " . $column->getType();
                if ($column->getLength()) {
                    $line .= "(" . $column->getLength();
                    if ($column->getPrecision()) {
                        $line .= "," . $column->getPrecision();
                    }
                    $line .= ")";
                }
                if ($column->isNotNull()) $line .= " NOT NULL";
                if ($column->isPrimaryKey()) {
                    if ($column->isAutoIncrement())
                        $line .= ' PRIMARY KEY';
                    else
                        $pks[] = $column->getName();
                }
                if ($column->isAutoIncrement()) $line .= ' AUTOINCREMENT';

                $columnLines[] = $line;
            }


            $sql .= join(",\n", $columnLines);

            if (sizeof($pks) > 0) {
                $sql .= ",\nPRIMARY KEY (" . join(",", $pks) . ")";
            }

            $sql .= "\n)";

            $this->databaseConnection->executeScript($sql);

        }

    }

}