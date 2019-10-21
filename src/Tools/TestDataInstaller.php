<?php


namespace Kinikit\Persistence\Tools;


use DirectoryIterator;
use Kinikit\Core\Binding\ObjectBinder;
use Kinikit\Core\Configuration\Configuration;
use Kinikit\Core\Configuration\FileResolver;
use Kinikit\Core\Configuration\SearchNamespaces;
use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Bootstrapper;
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
     * TestDataInstaller constructor.
     * @param ObjectBinder $objectBinder
     * @param ORM $orm
     * @param DBInstaller $dbInstaller
     * @param FileResolver $fileResolver
     * @param SearchNamespaces $searchNamespaces
     */
    public function __construct($objectBinder, $orm, $dbInstaller, $fileResolver, $searchNamespaces) {
        $this->objectBinder = $objectBinder;
        $this->orm = $orm;
        $this->dbInstaller = $dbInstaller;
        $this->fileResolver = $fileResolver;
        $this->searchNamespaces = $searchNamespaces;
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

        $directories = $this->fileResolver->getSearchPaths();

        foreach ($directories as $directory) {

            if (in_array($directory, $excludeTestDataPaths))
                continue;

            if (file_exists($directory . "/../test/TestData"))
                $this->processTestDataDirectory($directory . "/../test/TestData");
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
    private function processTestDataDirectory($baseDir, $suffix = "") {


        $iterator = new DirectoryIterator($baseDir . $suffix);
        $filepaths = array();
        foreach ($iterator as $item) {

            if ($item->isDot())
                continue;

            if ($item->isDir()) {
                $this->processTestDataDirectory($baseDir, $suffix . "/" . $item->getFilename());
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
                $this->orm->save($objects);
            }

        }


    }

}


