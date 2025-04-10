<?php


namespace Kinikit\Persistence\Tools;


use DirectoryIterator;
use Kinikit\Core\Binding\ObjectBinder;
use Kinikit\Core\Bootstrapper;
use Kinikit\Core\Configuration\FileResolver;
use Kinikit\Core\Configuration\SearchNamespaces;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Reflection\ClassInspectorProvider;
use Kinikit\Persistence\Database\Connection\BaseDatabaseConnection;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\ORM\Interceptor\ORMInterceptorProcessor;
use Kinikit\Persistence\ORM\ORM;

/**
 * @noProxy
 *
 * Class TestDataInstaller
 * @package Kinikit\Persistence\Tools
 */
class TestDataInstaller {

    /**
     * @var ObjectBinder
     */
    private $objectBinder;

    /**
     * @var ORM
     */
    private $orm;


    /**
     * @var DBInstaller
     */
    private $dbInstaller;


    /**
     * @var FileResolver
     */
    private $fileResolver;


    /**
     * @var SearchNamespaces
     */
    private $searchNamespaces;

    /**
     * @var ORMInterceptorProcessor
     */
    private $ormInterceptorProcessor;


    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;

    /**
     * TestDataInstaller constructor.
     * @param ObjectBinder $objectBinder
     * @param ORM $orm
     * @param DBInstaller $dbInstaller
     * @param FileResolver $fileResolver
     * @param SearchNamespaces $searchNamespaces
     * @param ORMInterceptorProcessor $ormInterceptorProcessor
     * @param DatabaseConnection $databaseConnection
     */
    public function __construct($objectBinder, $orm, $dbInstaller, $fileResolver, $searchNamespaces, $ormInterceptorProcessor, $databaseConnection) {
        $this->objectBinder = $objectBinder;
        $this->orm = $orm;
        $this->dbInstaller = $dbInstaller;
        $this->fileResolver = $fileResolver;
        $this->searchNamespaces = $searchNamespaces;
        $this->ormInterceptorProcessor = $ormInterceptorProcessor;
        $this->databaseConnection = $databaseConnection;
    }


    /**
     * Install test data.  This will look for json structured test data in folders under the
     * test directory (sibling of src).  The structure should match the object structure exactly
     * in the source directory such that the items can be resolved effectively.
     *
     * If Install DB is passed this will also install the db first.
     */
    public function run($installDB = true, $excludeTestDataPaths = []) {


        $cwd = getcwd();

        if ($installDB) {
            chdir("../src");
            $this->dbInstaller->run();
            chdir($cwd);
        }


        // If we subclass the base database connection, clear meta data cache
        if ($this->databaseConnection instanceof BaseDatabaseConnection) {
            $this->databaseConnection->clearMetaDataCache();
        }

        // Add test as a search path
        $directories = $this->fileResolver->getSearchPaths();
        $directories = array_reverse($directories);

        // Disable interceptors whilst inserting test data.
        $this->ormInterceptorProcessor->setEnabled(false);

        foreach ($directories as $directory) {

            if (in_array($directory, $excludeTestDataPaths))
                continue;

            if (file_exists($directory . "/../test/TestData"))
                $this->processTestDataDirectory($directory . "/../test/TestData");

        }


        // Re-enable interceptors after inserting test data.
        $this->ormInterceptorProcessor->setEnabled(true);


        // Load bootstrap scripts to ensure we are fully ready.
        Container::instance()->get(Bootstrapper::class);

        // Process any test scripts
        foreach ($directories as $directory) {

            if (file_exists($directory . "/../test/TestScripts"))
                $this->processTestScriptsDirectory($directory, "/../test/TestScripts");

        }



    }


    // Install test data
    public static function runFromComposer($event) {


        $sourceDirectory = $event && isset($event->getComposer()->getPackage()->getConfig()["source-directory"]) ?
            $event->getComposer()->getPackage()->getConfig()["source-directory"] : "src";


        $excludeTestDataPaths = $event->getComposer()->getPackage()->getConfig()["exclude-test-data-paths"] ?? "";
        $excludeTestDataPaths = explode(",", $excludeTestDataPaths);

        chdir($sourceDirectory);

        $installer = Container::instance()->get(TestDataInstaller::class);
        $installer->run(true, $excludeTestDataPaths);

    }


    // Process test data directory looking for objects.
    private function processTestDataDirectory($baseDir, $suffix = "", &$processed = []) {


        $iterator = new DirectoryIterator($baseDir . $suffix);
        $filepaths = array();
        foreach ($iterator as $item) {

            if ($item->isDot())
                continue;

            if ($item->isDir()) {
                $this->processTestDataDirectory($baseDir, $suffix . "/" . $item->getFilename(), $processed);
                continue;
            }

            if ($item->getExtension() != "json")
                continue;

            $filepaths[] = explode(".", $item->getFilename())[0];


        }

        sort($filepaths);


        foreach ($filepaths as $filepath) {


            $trimmedPath = ltrim($suffix . "/$filepath", "/");

            $targetClass = str_replace("/", "\\", $trimmedPath);

            // If this is not an explicitly namespaced class, attempt to find one.,
            if (!class_exists($targetClass)) {
                foreach ($this->searchNamespaces->getNamespaces() as $namespace) {
                    if (class_exists($namespace . "\\" . $targetClass)) {
                        $targetClass = $namespace . "\\" . $targetClass;
                        break;
                    }
                }
            }

            if (class_exists($targetClass)) {

                $items = json_decode(file_get_contents($baseDir . "/" . $trimmedPath . ".json"), true);
                $objects = $this->objectBinder->bindFromArray($items, $targetClass . "[]", false);

                // Save the objects.
                $this->orm->save($objects, true);
            }

        }


    }

    /**
     * Process test scripts
     *
     * @param mixed $directory
     * @param string $string
     * @return void
     */
    private function processTestScriptsDirectory($baseDir, $suffix = "") {

        $iterator = new DirectoryIterator($baseDir . $suffix);
        foreach ($iterator as $item) {

            if ($item->isDot())
                continue;

            if ($item->isDir()) {
                $this->processTestScriptsDirectory($baseDir, $suffix . "/" . $item->getFilename());
                continue;
            }

            if ($item->getExtension() == "php")
                include $baseDir . $suffix . "/" . $item->getFilename();

        }
    }


}


