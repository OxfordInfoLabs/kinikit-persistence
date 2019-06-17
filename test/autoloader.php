<?php


include_once __DIR__ . "/../vendor/autoload.php";

/**
 * Test autoloader - includes src one as well.
 */
spl_autoload_register(function ($class) {
    $class = str_replace("Kinikit\\Persistence\\", "", $class);
    $file = DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    if (file_exists(__DIR__ . $file)) {
        require __DIR__ . $file;
        return true;
    } else if (file_exists(__DIR__ . "/../src$file")) {
        require __DIR__ . "/../src$file";
        return true;
    } else
        return false;
});
