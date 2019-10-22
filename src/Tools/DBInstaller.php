<?php

namespace Kinikit\Persistence\Tools;

use DirectoryIterator;
use Kinikit\Core\Configuration\FileResolver;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Bootstrapper;
use Kinikit\Core\Init;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\ORM\Tools\SchemaGenerator;

/**
 * @noProxy
 *
 * Class DBInstaller
 * @package Kinikit\Persistence\Tools
 */
class DBInstaller {


    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;


    /**
     * @var SchemaGenerator
     */
    private $schemaGenerator;


    /**
     * @var FileResolver
     */
    private $fileResolver;

    /**
     * DBInstaller constructor.
     *
     * @param DatabaseConnection $databaseConnection
     * @param SchemaGenerator $schemaGenerator
     * @param FileResolver $fileResolver
     */
    public function __construct($databaseConnection, $schemaGenerator, $fileResolver) {
        $this->databaseConnection = $databaseConnection;
        $this->schemaGenerator = $schemaGenerator;
        $this->fileResolver = $fileResolver;
    }

    /**
     * Run the db installer.  This will resolve all included search paths for
     * objects in the supplied directories (default to objects).
     */
    public function run($objectPaths = ["."]) {

        // Ensure basic initialisation has occurred.
        Container::instance()->get(Init::class);

        // Execute the create schema for both the core and application
        $this->schemaGenerator->createSchema($objectPaths);

        $directories = $this->fileResolver->getSearchPaths();

        // Run core (and application) DB installs
        foreach ($directories as $directory) {
            if (file_exists($directory . "/DB")) {
                $directoryIterator = new DirectoryIterator($directory . "/DB");
                foreach ($directoryIterator as $item) {
                    if ($item->isDot()) continue;
                    if ($item->getExtension() != "sql") continue;
                    $this->databaseConnection->executeScript(file_get_contents($item->getRealPath()));
                }
            }
        }
    }


    /**
     * Main clean function.
     */
    public static function runFromComposer($event) {

        $sourceDirectory = $event && isset($event->getComposer()->getPackage()->getConfig()["source-directory"]) ?
            $event->getComposer()->getPackage()->getConfig()["source-directory"] : "src";

        chdir($sourceDirectory);

        $installer = Container::instance()->get(DBInstaller::class);
        $installer->run();


    }

}
