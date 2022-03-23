<?php


namespace Kinikit\Persistence\Database\Vendors\SQLite3;


class TestCustomFunction implements SQLite3CustomFunction {




    public function execute(...$arguments) {
        return $arguments[0] * 2;
    }

    public function getName() {
        return "TESTCUSTOM";
    }
}