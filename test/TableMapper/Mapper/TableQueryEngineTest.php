<?php

namespace Kinikit\Persistence\TableMapper\Query;

use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Mapper\TableMapper;
use Kinikit\Persistence\TableMapper\Mapper\TableMapping;
use Kinikit\Persistence\TableMapper\Mapper\TableQueryEngine;
use Kinikit\Persistence\TableMapper\Relationship\ManyToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\ManyToOneTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToOneTableRelationship;

include_once "autoloader.php";

class TableQueryEngineTest extends \PHPUnit\Framework\TestCase {


    private $databaseConnection;

    public function setUp(): void {

        $this->databaseConnection = Container::instance()->get(DatabaseConnection::class);
        $this->databaseConnection->executeScript(file_get_contents(__DIR__ . "/../Mapper/tablemapper.sql"));

    }


    public function testCanQueryForRowsUsingDefaultConnection() {

        // Create a basic mapper
        $queryEngine = new TableQueryEngine();
        $this->assertEquals([
            1 => ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"]
        ], $queryEngine->query("example", "WHERE name = ?", ["Mark"]));


        $this->assertEquals([
            1 => ["id" => 1, "name" => "Mark", "last_modified" => "2010-01-01"],
            3 => ["id" => 3, "name" => "Dave", "last_modified" => "2014-01-01"]
        ], $queryEngine->query("example", "WHERE name = ? or name = ? ORDER by id", ["Mark", "Dave"]));


    }


    public function testIfManyToOneRelationshipIsDefinedQueriesAlsoQueryRelatedEntitiesViaJoin() {

        // Create a mapper with a one to one table relationship with another child.
        $queryEngine = new TableQueryEngine();

        $tableMapping = new TableMapping("example_parent",
            [new ManyToOneTableRelationship(new TableMapping("example_child"),
                "child1", "child_id")]);


        // Check some primary key fetches
        $this->assertEquals([1 => ["id" => 1, "name" => "Mary Jones", "child_id" => null]], $queryEngine->query($tableMapping, "WHERE id = 1"));
        $this->assertEquals([2 => ["id" => 2, "name" => "Jane Walsh", "child_id" => 1, "child1" =>
            ["id" => 1, "description" => "Washing", "child2_id" => null]]], $queryEngine->query($tableMapping, "WHERE id = 2"));

        $this->assertEquals([3 => ["id" => 3, "name" => "James Smith", "child_id" => 2, "child1" =>
            ["id" => 2, "description" => "Swimming", "child2_id" => null]]], $queryEngine->query($tableMapping, "WHERE id = 3"));


        // Now do a regular query
        $results = $queryEngine->query($tableMapping, "WHERE name LIKE ? OR child_id = ?", "JA%", 1);
        $this->assertEquals(["id" => 2, "name" => "Jane Walsh", "child_id" => 1, "child1" =>
            ["id" => 1, "description" => "Washing", "child2_id" => null]], $results[2]);

        $this->assertEquals(["id" => 3, "name" => "James Smith", "child_id" => 2, "child1" =>
            ["id" => 2, "description" => "Swimming", "child2_id" => null]], $results[3]);


        // Now do a nested one to one
        $queryEngine = new TableQueryEngine();

        $tableMapping = new TableMapping("example_parent",
            [new ManyToOneTableRelationship(
                new TableMapping("example_child",
                    [new ManyToOneTableRelationship(new TableMapping("example_child2"), "child2", "child2_id")]
                ),
                "child1", "child_id")]);

        $this->assertEquals([4 => ["id" => 4, "name" => "Heather Wright", "child_id" => 3, "child1" =>
            ["id" => 3, "description" => "Cooking", "child2_id" => 1, "child2" => [
                "id" => 1, "profession" => "Doctor"]
            ]]], $queryEngine->query($tableMapping, "WHERE id = 4"));


        // Now attempt to execute a query with nested where clause constraints
        $this->assertEquals([4 => ["id" => 4, "name" => "Heather Wright", "child_id" => 3, "child1" =>
            ["id" => 3, "description" => "Cooking", "child2_id" => 1, "child2" => [
                "id" => 1, "profession" => "Doctor"]
            ]]], $queryEngine->query($tableMapping, "WHERE child1.description = 'Cooking'"));


        $this->assertEquals([4 => ["id" => 4, "name" => "Heather Wright", "child_id" => 3, "child1" =>
            ["id" => 3, "description" => "Cooking", "child2_id" => 1, "child2" => [
                "id" => 1, "profession" => "Doctor"]
            ]]], $queryEngine->query($tableMapping, "WHERE child1.child2.id = 1"));


    }


    public function testIfOneToOneRelationshipDefinedQueriesAlsoQueryRelatedEntities() {

        // Create a mapper with a one to one table relationship with another child.
        $queryEngine = new TableQueryEngine();


        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new OneToOneTableRelationship(new TableMapping("example_child_with_parent_key"),
                "child1", "parent_id")]);


        $this->assertEquals([["id" => 1, "name" => "Mary Jones", "child_id" => null,
            "child1" => ["id" => 2, "description" => "Swimming", "parent_id" => 1]]], array_values($queryEngine->query($tableMapping, "WHERE id = 1")));


        $this->assertEquals([["id" => 2, "name" => "Jane Walsh", "child_id" => 1,
            "child1" => ["id" => 3, "description" => "Cooking", "parent_id" => 2]]], array_values($queryEngine->query($tableMapping, "WHERE id = 2")));


        // Try a filter query.
        $results = $queryEngine->query($tableMapping, "WHERE name LIKE ?", "JA%");

        $this->assertEquals(["id" => 2, "name" => "Jane Walsh", "child_id" => 1, "child1" =>
            ["id" => 3, "description" => "Cooking", "parent_id" => 2]], $results[2]);

        $this->assertEquals(["id" => 3, "name" => "James Smith", "child_id" => 2, "child1" =>
            ["id" => 4, "description" => "Laughing", "parent_id" => 3]], $results[3]);


        // Now attempt to execute a query with nested where clause constraints
        $this->assertEquals([["id" => 3, "name" => "James Smith", "child_id" => 2, "child1" =>
            ["id" => 6, "description" => "Walking", "parent_id" => 3]
        ]], array_values($queryEngine->query($tableMapping, "WHERE child1.description = 'Walking'")));


    }


    public function testIfOneToManyRelationshipDefinedQueriesAlsoQueryRelatedEntities() {


        // Create a mapper with a one to one table relationship with another child.
        $queryEngine = new TableQueryEngine();


        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new OneToManyTableRelationship(new TableMapping("example_child_with_parent_key"),
                "child1", "parent_id")]);


        $this->assertEquals([["id" => 1, "name" => "Mary Jones", "child_id" => null,
            "child1" => [["id" => 2, "description" => "Swimming", "parent_id" => 1]]]], array_values($queryEngine->query($tableMapping, "WHERE id = 1")));


        $this->assertEquals([["id" => 3, "name" => "James Smith", "child_id" => 2,
            "child1" => [["id" => 4, "description" => "Laughing", "parent_id" => 3],
                ["id" => 5, "description" => "Smiling", "parent_id" => 3],
                ["id" => 6, "description" => "Walking", "parent_id" => 3]]]], array_values($queryEngine->query($tableMapping, "WHERE id = 3")));


        // Try a filter query.
        $results = $queryEngine->query($tableMapping, "WHERE name LIKE ?", "JA%");

        $this->assertEquals(["id" => 2, "name" => "Jane Walsh", "child_id" => 1, "child1" =>
            [["id" => 3, "description" => "Cooking", "parent_id" => 2]]], $results[2]);

        $this->assertEquals(["id" => 3, "name" => "James Smith", "child_id" => 2,
            "child1" => [["id" => 4, "description" => "Laughing", "parent_id" => 3],
                ["id" => 5, "description" => "Smiling", "parent_id" => 3],
                ["id" => 6, "description" => "Walking", "parent_id" => 3]]], $results[3]);


        // Now attempt to execute a query with nested where clause constraints
        // This is a special case because it requires a 2 hit query to achieve the right result.
        $this->assertEquals([["id" => 3, "name" => "James Smith", "child_id" => 2,
            "child1" => [["id" => 4, "description" => "Laughing", "parent_id" => 3],
                ["id" => 5, "description" => "Smiling", "parent_id" => 3],
                ["id" => 6, "description" => "Walking", "parent_id" => 3]]]],
            array_values($queryEngine->query($tableMapping, "WHERE child1.description = 'Walking'")));


        // Now attempt an offset / limit query where nested results
        $this->assertEquals(4, sizeof($queryEngine->query($tableMapping, "LIMIT 4")));


    }


    public function testIfOneToManyRelationshipDefinedWithStaticMappedFieldsTheseAreQueriedAsWell() {

        // Create a mapper with a one to one table relationship with another child.
        $queryEngine = new TableQueryEngine();


        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new OneToManyTableRelationship(new TableMapping("example_child_with_parent_and_type"),
                "notes", ["parent_id", "type=NOTE"]),
                new OneToManyTableRelationship(new TableMapping("example_child_with_parent_and_type"),
                    "comments", ["parent_id", "type=COMMENT"])
            ]);


        $this->assertEquals([["id" => 1, "name" => "Mary Jones", "child_id" => null,
            "notes" => [["id" => 1, "description" => "Happy", "parent_id" => 1, "type" => "NOTE"], ["id" => 2, "description" => "Excited", "parent_id" => 1, "type" => "NOTE"]],
            "comments" => [["id" => 5, "description" => "Extrovert", "parent_id" => 1, "type" => "COMMENT"]]
        ]],
            array_values($queryEngine->query($tableMapping, "WHERE id = 1")));


        $this->assertEquals([["id" => 2, "name" => "Jane Walsh", "child_id" => 1,
            "notes" => [["id" => 3, "description" => "Grumpy", "parent_id" => 2, "type" => "NOTE"],
                ["id" => 4, "description" => "Sad", "parent_id" => 2, "type" => "NOTE"]],
            "comments" => [
                ["id" => 7, "description" => "Introvert", "parent_id" => 2, "type" => "COMMENT"],
                ["id" => 6, "description" => "Musician", "parent_id" => 2, "type" => "COMMENT"]]
        ]],
            array_values($queryEngine->query($tableMapping, "WHERE id = 2")));


    }


    public function testIfManyToManyRelationshipDefinedQueriesAlsoQueryRelatedEntities() {

        // Create a mapper with a one to one table relationship with another child.
        $queryEngine = new TableQueryEngine();

        $tableMapping = new TableMapping("example_parent",
            [new ManyToManyTableRelationship(new TableMapping("example_child2"),
                "manytomany", "example_many_to_many_link")]);


        $this->assertEquals([
            ["id" => 1, "name" => "Mary Jones", "child_id" => null, "manytomany" =>
                [
                    ["id" => 1, "profession" => "Doctor"],
                    ["id" => 2, "profession" => "Teacher"],
                    ["id" => 3, "profession" => "Nurse"],
                ]
            ]
        ], array_values($queryEngine->query($tableMapping, "WHERE id = 1")));


        $results = $queryEngine->query($tableMapping, "WHERE manytomany.profession = ?", "Car Mechanic");
        $this->assertEquals(2, sizeof($results));
        $this->assertEquals($queryEngine->query($tableMapping, "WHERE id IN (2, 3)"), $results);


        // Now attempt an offset / limit query where nested results
        $this->assertEquals(4, sizeof($queryEngine->query($tableMapping, "LIMIT 4")));


    }


    public function testCanQueryForValueExpressions() {

        $queryEngine = new TableQueryEngine();

        $tableMapping = new TableMapping("example");

        $this->assertEquals([["name" => "Mark"], ["name" => "John"], ["name" => "Dave"]], $queryEngine->query($tableMapping, "SELECT name FROM example"));

        $tableMapping = new TableMapping("example_parent",
            [new OneToManyTableRelationship(new TableMapping("example_child_with_parent_key"),
                "child1", "parent_id")]);

        $this->assertEquals([["name" => "Mary Jones", "description" => "Swimming"], ["name" => "Jane Walsh", "description" => "Cooking"]],
            $queryEngine->query($tableMapping, "SELECT name, child1.description FROM example_parent WHERE id IN (1, 2)"));


    }


    public function testCanQueryUsingSubqueries() {

        $queryEngine = new TableQueryEngine();

        $tableMapping = new TableMapping("example");

        $this->assertEquals([["name" => "Mark"], ["name" => "John"], ["name" => "Dave"]],
            $queryEngine->query($tableMapping, "SELECT name FROM example WHERE name IN (SELECT 'Mark'
UNION SELECT 'John'
UNION SELECT 'Dave'
WHERE 1 = 1)"));


    }


    public function testRecursiveNestedRelatedEntitiesAreOnlyQueriedToMaxDepthOf5ByDefault() {

        $queryEngine = new TableQueryEngine();

        $tableMapping = new TableMapping("example_recursive");
        $tableMapping->setRelationships([new OneToManyTableRelationship($tableMapping, "children", ["parent_id"])]);

        $result = $queryEngine->query($tableMapping, "WHERE id = 1");

        $this->assertEquals([1 => [
            "id" => 1,
            "note" => "Top level",
            "children" => [
                [
                    "id" => 2,
                    "note" => "First level",
                    "children" => [
                        [
                            "id" => 3,
                            "note" => "Second level",
                            "children" => [
                                [
                                    "id" => 4,
                                    "note" => "Third level",
                                    "children" => [
                                        [
                                            "id" => 5,
                                            "note" => "Fourth level",
                                            "parent_id" => 4
                                        ]
                                    ],
                                    "parent_id" => 3
                                ]
                            ],
                            "parent_id" => 2
                        ]
                    ],
                    "parent_id" => 1
                ]
            ],
            "parent_id" => null
        ]], $result);
    }


    public function testRecursiveNestedRelatedEntitiesAreOnlyQueriedToMaxDepthIfSetToOtherValue() {

        $queryEngine = new TableQueryEngine();

        $tableMapping = new TableMapping("example_recursive");
        $oneToManyTableRelationship = new OneToManyTableRelationship($tableMapping, "children", ["parent_id"]);
        $oneToManyTableRelationship->setMaxDepth(2);
        $tableMapping->setRelationships([$oneToManyTableRelationship]);

        $result = $queryEngine->query($tableMapping, "WHERE id = 1");

        $this->assertEquals([1 => [
            "id" => 1,
            "note" => "Top level",
            "children" => [
                [
                    "id" => 2,
                    "note" => "First level",
                    "parent_id" => 1
                ]
            ],
            "parent_id" => null
        ]], $result);
    }

}
