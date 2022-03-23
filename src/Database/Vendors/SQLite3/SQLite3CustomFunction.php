<?php


namespace Kinikit\Persistence\Database\Vendors\SQLite3;


interface SQLite3CustomFunction {

    /**
     * @return string
     */
    public function getName();


    /**
     * Execute the custom function
     *
     * @param ...$arguments
     * @return mixed
     */
    public function execute(...$arguments);


}