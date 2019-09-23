<?php

namespace Kinikit\Persistence\ORM\SchemaGenerator;

use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\Reflection\ClassInspectorProvider;
use Kinikit\Persistence\Database\MetaData\TableMetaData;
use Kinikit\Persistence\ORM\Mapping\ORMMapping;

/**
 * Schema Generator - attempts to generate schema for all objects found in the
 * passed directory (defaulting to the application Objects directory).
 */
class SchemaGenerator {

    /**
     * @var ClassInspectorProvider
     */
    private $classInspectorProvider;


    /**
     * SchemaGenerator constructor.
     *
     * @param ClassInspectorProvider $classInspectorProvider
     */
    public function __construct($classInspectorProvider) {
        $this->classInspectorProvider = $classInspectorProvider;
    }

    /**
     * Generate the schema as an array of table meta data objects for insert (indexed by
     * table name)
     *
     * @param string $rootPath
     * @param string $rootPathNamespace
     *
     * @return TableMetaData[string]
     */
    public function generateSchema($rootPath = "./Objects", $rootPathNamespace = null) {

        // Grab the table definitions for all tables recursively (indexed by class name)
        $matchedObjects = $this->getMatchingObjects($rootPath, $rootPathNamespace);


        foreach ($matchedObjects as $key => $columns) {

            echo "\nCreating table $key";

//            // Drop unless flag supplied as false
//            if ($dropTables) {
//                try {
//                    $this->databaseConnection->query("DROP TABLE {$definition->getTableName()}",);
//                } catch (SQLException $e) {
//                    // Continue as likely table doesn't exist.
//                }
//            }
//
//            // Create the table from the meta data definition.
//            $this->databaseConnection->createTable($definition);

        }


    }


    /**
     * Get all matching objects
     *
     * @param string $rootPath
     * @param string $rootPathNamespace
     *
     * @return TableMetaData[]
     */
    public function getMatchingObjects($rootPath = "./Objects", $rootPathNamespace = null) {

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

                        // Read the table mapping
                        $tableMapping = ORMMapping::get($className)->getTableMapping();
                        
                    }
                }

                // If directory, run this recursively.
                if ($item->isDir()) {
                    $subDefs = $this->getMatchingObjects($rootPath . "/" . $item->getFilename(),
                        $rootPathNamespace . "\\" . $item->getFilename());

                    $tableDefinitions = array_merge($tableDefinitions, $subDefs);
                }


            }

        }

        return $tableDefinitions;

    }


    /**
     * Process any relationships, effectively augmenting the data as required.
     *
     * @param $tableDefinitions
     */
    public function processRelationships(&$tableDefinitions) {

    }


}