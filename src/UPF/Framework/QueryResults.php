<?php
/**
 * Created by PhpStorm.
 * User: nathanalan
 * Date: 22/08/2018
 * Time: 12:32
 */

namespace Kinikit\Persistence\UPF\Framework;


interface QueryResults {

    public function processResults($results, $persistenceCoordinator, $objectClass);

}