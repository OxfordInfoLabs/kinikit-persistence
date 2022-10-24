<?php


namespace Kinikit\Persistence\Database\Vendors\SQLite3\CustomFunctions;


use Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3CustomFunction;

class Now implements SQLite3CustomFunction {

    public function getName() {
        return "NOW";
    }

    /**
     * Implement the now function
     *
     * @param mixed ...$arguments
     * @return false|mixed|string
     */
    public function execute(...$arguments) {
        return date('Y-m-d H:i:s');
    }
}