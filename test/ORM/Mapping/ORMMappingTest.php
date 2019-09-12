<?php


namespace Kinikit\Persistence\ORM\Mapping;

use Kinikit\Persistence\ORM\Address;
use Kinikit\Persistence\TableMapper\Mapper\TableMapping;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";


class ORMMappingTest extends TestCase {


    public function testCanGenerateTableMappingForSimpleObject() {

        $mapping = new ORMMapping(Address::class);
        $this->assertEquals(new TableMapping("address"), $mapping->getTableMapping());

    }

}
