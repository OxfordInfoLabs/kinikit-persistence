<?php

namespace Kinikit\Persistence\UPF\Engines\ORM\Query\Filters;

use Kinikit\Persistence\Database\Connection\DefaultDB;

include_once "autoloader.php";

class NotNullFilterTest extends \PHPUnit\Framework\TestCase {

    public function testCanGetNotNullClause() {

        $filter = new NotNullFilter();
        $this->assertEquals("bobby IS NOT NULL", $filter->evaluateFilterClause("bobby", null));
        $this->assertEquals("mary IS NOT NULL", $filter->evaluateFilterClause("mary", null));
        $this->assertEquals("john IS NOT NULL", $filter->evaluateFilterClause("john", null));
    }

} 