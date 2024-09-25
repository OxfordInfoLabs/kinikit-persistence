<?php

namespace Kinikit\Persistence\ORM\Tools;

use Kiniauth\DB\DBInstaller;
use Kinikit\Core\Configuration\FileResolver;
use Kinikit\Core\Reflection\ClassInspectorProvider;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Generator\TableDDLGenerator;
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
     * SchemaGenerator constructor.
     *
     * @param ClassInspectorProvider $classInspectorProvider
     * @param DatabaseConnection $databaseConnection
     * @param FileResolver $fileResolver
     * @param TableDDLGenerator $tableDDLGenerator
     */
    public function __construct(
        private $classInspectorProvider,
        private $databaseConnection,
        private $fileResolver,
        private $tableDDLGenerator) {
    }


    /**
     * Generate schema for all objects using the file resolver.
     */
    public function createSchema($objectPaths = ["."], $dropIfExists = true) {

        foreach ($this->fileResolver->getSearchPaths() as $searchPath) {

            foreach ($objectPaths as $objectPath) {
                $this->createSchemaForPath($searchPath . "/" . $objectPath, $dropIfExists);
            }
        }

    }


    /**
     * Generate table meta data for all objects found in the path using a defined root namespace.
     *
     * @param string $rootPath
     * @param string $rootPathNamespace
     *
     * @return TableMetaData[]
     */
    public function generateTableMetaData($rootPath = ".") {

        $tableMetaData = array();

        if (file_exists($rootPath)) {

            $directory = new \DirectoryIterator($rootPath);
            foreach ($directory as $item) {

                if ($item->isDot())
                    continue;

                // if this is a class file, check it.
                if ($item->getExtension() == "php") {

                    $fileContents = file_get_contents($item->getRealPath());
                    preg_match("/namespace (.*?);/", $fileContents, $matches);

                    $className = "";
                    if (sizeof($matches) == 2)
                        $className = trim($matches[1]) . "\\" . (explode(".", $item->getFilename())[0]);


                    if (class_exists($className)) {

                        // Read the table mapping
                        $classInspector = $this->classInspectorProvider->getClassInspector($className);
                        if (isset($classInspector->getClassAnnotations()["generate"])) {
                            $mapper = ORMMapping::get($className);
                            $tableMetaData = array_merge($tableMetaData, $mapper->generateTableMetaData());
                        }

                    }
                }

                // If directory, run this recursively.
                if ($item->isDir()) {
                    $subDefs = $this->generateTableMetaData($rootPath . "/" . $item->getFilename());

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
     * @param bool $dropIfExists
     */
    public function createSchemaForPath($rootPath = "./Objects", $dropIfExists = true) {

        // Get the generated meta data.
        $generatedMetaData = $this->generateTableMetaData($rootPath);

        // Now loop through and create the schema using the default database connection.
        foreach ($generatedMetaData as $tableMetaData) {

            $sql = "";
            if ($dropIfExists) {
                $sql = "DROP TABLE IF EXISTS {$tableMetaData->getTableName()};";
            }

            $sql .= $this->tableDDLGenerator->generateTableCreateSQL($tableMetaData, $this->databaseConnection);

            $this->databaseConnection->executeScript($sql);

        }

    }


}
