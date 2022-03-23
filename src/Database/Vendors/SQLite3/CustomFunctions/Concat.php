<?php


namespace Kinikit\Persistence\Database\Vendors\SQLite3\CustomFunctions;


use Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3CustomFunction;

class Concat implements SQLite3CustomFunction {

    public function getName() {
        return "CONCAT";
    }

    /**
     * Simply join together all arguments
     *
     * @param mixed ...$arguments
     * @return mixed|string
     */
    public function execute(...$arguments) {
        return join("", $arguments);
    }
}