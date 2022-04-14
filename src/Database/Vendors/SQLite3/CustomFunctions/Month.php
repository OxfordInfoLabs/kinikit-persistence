<?php


namespace Kinikit\Persistence\Database\Vendors\SQLite3\CustomFunctions;


use Kinikit\Persistence\Database\Vendors\SQLite3\SQLite3CustomFunction;

class Month implements SQLite3CustomFunction {

    public function getName() {
        return "MONTH";
    }

    /**
     * Execute year function
     *
     * @param mixed ...$arguments
     * @return mixed|void
     */
    public function execute(...$arguments) {
        $date = date_create($arguments[0]);
        return $date->format("m");
    }
}