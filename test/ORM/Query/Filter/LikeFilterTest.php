<?php

namespace Kinikit\Persistence\ORM\Query\Filter;

use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class LikeFilterTest extends TestCase {

    public function testLikeFilterProducesConcatenatedLikeIfMultipleColumnsSupplied() {

        // Check single one
        $likeFilter = new LikeFilter("test", "hello*");
        $this->assertEquals("test LIKE ?", $likeFilter->getSQLClause());
        $this->assertEquals(["hello*"], $likeFilter->getParameterValues());

        // Check multi column one
        $likeFilter = new LikeFilter(["test", "test2", "test3"], "hello*");
        $this->assertEquals("CONCAT(IFNULL(test,''),IFNULL(test2,''),IFNULL(test3,'')) LIKE ?", $likeFilter->getSQLClause());
        $this->assertEquals(["hello*"], $likeFilter->getParameterValues());

    }

}