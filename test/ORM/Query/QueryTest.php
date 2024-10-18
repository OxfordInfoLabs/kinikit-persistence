<?php

namespace Kinikit\Persistence\ORM\Query;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Core\Testing\MockObject;
use Kinikit\Core\Testing\MockObjectProvider;
use Kinikit\Persistence\ORM\Address;
use Kinikit\Persistence\ORM\Contact;
use Kinikit\Persistence\ORM\ORM;
use Kinikit\Persistence\ORM\Query\Filter\EqualsFilter;
use Kinikit\Persistence\ORM\Query\Filter\LikeFilter;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class QueryTest extends TestCase {

    /**
     * @var MockObject
     */
    private $orm;

    public function setUp(): void {
        $this->orm = MockObjectProvider::instance()->getMockInstance(ORM::class);
    }

    public function testCanQueryWthNoFilters() {

        $query = new Query(Address::class, $this->orm);

        $expectedReturn = [new Address(12, "Mark Test", "1 Street")];

        $this->orm->returnValue("filter", $expectedReturn, [
            Address::class, "", []
        ]);

        $this->assertEquals($expectedReturn, $query->query(["street1" => [], "street2" => []]));

        $this->assertEquals($expectedReturn, $query->query(["street1" => null, "street2" => null]));

    }

    public function testCanQueryWithSimpleEqualsFiltersWhereValuePassedDirectlyAsString() {

        $query = new Query(Address::class, $this->orm);

        $expectedReturn = [new Address(12, "Mark Test", "1 Street")];

        $this->orm->returnValue("filter", $expectedReturn, [
            Address::class, "WHERE street1 = ? AND street2 = ?", ["SOMEWHERE", "NOWHERE"]
        ]);

        $this->assertEquals($expectedReturn, $query->query(["street1" => "SOMEWHERE", "street2" => "NOWHERE"]));

    }

    public function testCanQueryWithSimpleInFilterWhereValuesPassedAsArrayDirectly() {

        $query = new Query(Address::class, $this->orm);

        $expectedReturn = [new Address(12, "Mark Test", "1 Street")];

        $this->orm->returnValue("filter", $expectedReturn, [
            Address::class, "WHERE street1 IN (?,?,?) AND street2 = ?", ["SOMEWHERE", "SOMEWHERE2", "SOMEWHERE3", "NOWHERE"]
        ]);

        $this->assertEquals($expectedReturn, $query->query(["street1" => ["SOMEWHERE", "SOMEWHERE2", "SOMEWHERE3"], "street2" => "NOWHERE"]));

    }

    public function testNullCaseAddedExplicitlyIfIncludedInValuesArray(){

        $query = new Query(Address::class, $this->orm);

        $expectedReturn = [new Address(12, "Mark Test", "1 Street")];

        $this->orm->returnValue("filter", $expectedReturn, [
            Address::class, "WHERE (street1 IN (?,?) OR street1 IS NULL) AND street2 = ?", ["SOMEWHERE", "SOMEWHERE3", "NOWHERE"]
        ]);

        $values = $query->query(["street1" => ["SOMEWHERE", null, "SOMEWHERE3"], "street2" => "NOWHERE"]);

        $this->assertEquals($expectedReturn, $values);

    }


    public function testCanQueryUsingLikeFilterIfWildcardsPassedInDirectString() {

        $query = new Query(Address::class, $this->orm);

        $expectedReturn = [new Address(12, "Mark Test", "1 Street")];

        $this->orm->returnValue("filter", $expectedReturn, [
            Address::class, "WHERE street1 LIKE ?", ["%SOMEWHERE"]
        ]);

        $this->assertEquals($expectedReturn, $query->query(["street1" => "%SOMEWHERE"]));

        $this->assertEquals($expectedReturn, $query->query(["street1" => "*SOMEWHERE"]));

    }


    public function testCanQueryUsingFilterInstancesWithOffsetAndLimit() {

        $query = new Query(Address::class, $this->orm);

        $expectedReturn = [new Address(12, "Mark Test", "1 Street")];

        $this->orm->returnValue("filter", $expectedReturn, [
            Address::class, "WHERE street1 LIKE ? AND street2 = ? LIMIT ? OFFSET ?", ["SOMEWHERE%", "NOWHERE", 25, 5]
        ]);

        $this->assertEquals($expectedReturn, $query->query([new LikeFilter("street1", "SOMEWHERE%"), new EqualsFilter("street2", "NOWHERE")], [], 25, 5));

    }

    public function testCanQueryUsingFilterInstancesWithOffsetAndLimitAndOrderings() {

        $query = new Query(Address::class, $this->orm);

        $expectedReturn = [new Address(12, "Mark Test", "1 Street")];

        $this->orm->returnValue("filter", $expectedReturn, [
            Address::class, "WHERE street1 LIKE ? AND street2 = ? ORDER BY id DESC, name LIMIT ? OFFSET ?", ["SOMEWHERE%", "NOWHERE", 25, 5]
        ]);

        $this->assertEquals($expectedReturn, $query->query([new LikeFilter("street1", "SOMEWHERE%"), new EqualsFilter("street2", "NOWHERE")], ["id DESC", "name"], 25, 5));

    }


    public function testCanSummariseByMember() {
        $query = new Query(Address::class, $this->orm);

        $expectedReturn = [
            [
                "id" => 1,
                "COUNT(DISTINCT(id))" => 5
            ],
            [
                "id" => 2,
                "COUNT(DISTINCT(id))" => 6
            ]
        ];


        $this->orm->returnValue("values", $expectedReturn, [
            Address::class, ["id", "COUNT(DISTINCT(id))"], "WHERE text LIKE ? GROUP BY id HAVING id IS NOT NULL", ["mark%"]
        ]);

        $expectedItems = [new SummarisedValue(1, 5), new SummarisedValue(2, 6)];
        $this->assertEquals($expectedItems, $query->summariseByMember("id", ["text" => "mark%"]));


        $expectedReturn = [
            [
                "id" => 1,
                "SUM(*)" => 5
            ],
            [
                "id" => 2,
                "SUM(*)" => 6
            ]
        ];


        $this->orm->returnValue("values", $expectedReturn, [
            Address::class, ["id", "SUM(*)"], "WHERE text LIKE ? GROUP BY id HAVING id IS NOT NULL", ["mark%"]
        ]);

        $expectedItems = [new SummarisedValue(1, 5), new SummarisedValue(2, 6)];
        $this->assertEquals($expectedItems, $query->summariseByMember("id", ["text" => "mark%"], "SUM(*)"));

    }


}