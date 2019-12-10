<?php


namespace Kinikit\Persistence\TableMapper\Mapper;


use Kinikit\Core\DependencyInjection\Container;
use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\TableMapper\Relationship\ManyToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\ManyToOneTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToManyTableRelationship;
use Kinikit\Persistence\TableMapper\Relationship\OneToOneTableRelationship;

include_once "autoloader.php";

class TablePersistenceEngineTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var TablePersistenceEngine
     */
    private $persistenceEngine;

    /**
     * @var TableQueryEngine
     */
    private $queryEngine;


    /**
     * @var DatabaseConnection
     */
    private $databaseConnection;

    public function setUp(): void {
        parent::setUp();
        $this->persistenceEngine = new TablePersistenceEngine();
        $this->queryEngine = new TableQueryEngine();

        $this->databaseConnection = Container::instance()->get(DatabaseConnection::class);
        $this->databaseConnection->executeScript(file_get_contents(__DIR__ . "/tablemapper.sql"));
    }


    public function testCanInsertDataForSimpleTable() {

        // Create a basic mapping
        $tableMapping = new TableMapping("example");

        $this->persistenceEngine->saveRows($tableMapping, ["name" => "Conrad"], TablePersistenceEngine::SAVE_OPERATION_INSERT);

        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE name = 'Conrad'")));

        $this->persistenceEngine->saveRows($tableMapping, [["name" => "Stephen"], ["name" => "Willis"], ["name" => "Pedro"]], TablePersistenceEngine::SAVE_OPERATION_INSERT);

        $this->assertEquals(3, sizeof($this->queryEngine->query($tableMapping, "WHERE name In ('Stephen', 'Willis', 'Pedro')")));

    }


    public function testCanInsertRelationalDataForRelationshipsAsWellIfSupplied() {


        // ONE TO ONE RELATIONSHIPS

        $childMapper = new TableMapping("example_child_with_parent_key");
        $tableMapper = new TableMapping("example_parent", [new OneToOneTableRelationship($childMapper, "child", "parent_id")]);


        $insertData = [
            "name" => "Michael",
            "child" => [
                "description" => "Swimming Lanes"
            ]];


        $this->persistenceEngine->saveRows($tableMapper, $insertData, TablePersistenceEngine::SAVE_OPERATION_INSERT);


        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapper, "WHERE name = 'Michael'")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE description = 'Swimming Lanes' AND parent_id = 5")));


        // Now try a double nested one.
        $childMapper = new TableMapping("example_child_with_parent_key", [
            new OneToOneTableRelationship("example_child_with_parent_key", "child", "parent_id")
        ]);

        $tableMapper = new TableMapping("example_parent", [
            new OneToOneTableRelationship($childMapper, "child", "parent_id")
        ]);


        $insertData = [
            "name" => "Stephanie",
            "child" => [
                "description" => "Cycling Lanes",
                "child" => [
                    "description" => "Jumping up and down"
                ]
            ]];


        $savedRows = $this->persistenceEngine->saveRows($tableMapper, $insertData, TablePersistenceEngine::SAVE_OPERATION_INSERT);


        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapper, "WHERE name = 'Stephanie'")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE description = 'Cycling Lanes' AND parent_id = 6")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE description = 'Jumping up and down' AND parent_id = 9")));


        // MANY TO ONE RELATIONSHIPS
        $child2Mapper = new TableMapping("example_child2");
        $childMapper = new TableMapping("example_child", [new ManyToOneTableRelationship($child2Mapper, "child", "child2_id", true)]);
        $tableMapper = new TableMapping("example_parent", [new ManyToOneTableRelationship($childMapper, "child", "child_id", true)]);

        $insertData = [
            "name" => "Bonzo",
            "child" => [
                "description" => "Dog Catching",
                "child" => [
                    "profession" => "Dentist"
                ]
            ]];

        $result = $this->persistenceEngine->saveRows($tableMapper, $insertData, TablePersistenceEngine::SAVE_OPERATION_INSERT);

        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapper, "WHERE name = 'Bonzo' AND child_id IS NOT NULL")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE description = 'Dog Catching' AND child2_id IS NOT NULL")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE id = 8 AND profession = 'Dentist'")));


        // ONE TO MANY RELATIONSHIPS
        $this->databaseConnection->query("DELETE FROM example_child_with_parent_key");

        $child2Mapper = new TableMapping("example_child_with_parent_key");
        $childMapper = new TableMapping("example_child_with_parent_key", [new OneToManyTableRelationship($child2Mapper, "children", "parent_id")]);
        $tableMapper = new TableMapping("example", [new OneToManyTableRelationship($childMapper, "children", "parent_id")]);

        $insertData = [
            "name" => "Pickachoo",
            "children" => [
                [
                    "description" => "Jason",
                    "children" => [
                        [
                            "description" => "Fisher"
                        ]
                    ]
                ],
                [
                    "description" => "Joan",
                    "children" => [
                        [
                            "description" => "Rock Climber"
                        ],
                        [
                            "description" => "Helicopter"
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->persistenceEngine->saveRows($tableMapper, $insertData, TablePersistenceEngine::SAVE_OPERATION_INSERT);


        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapper, "WHERE name = 'Pickachoo' AND id = 4")));
        $this->assertEquals(2, sizeof($this->queryEngine->query($childMapper, "WHERE parent_id = 4")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($child2Mapper, "WHERE parent_id = 11")));
        $this->assertEquals(2, sizeof($this->queryEngine->query($child2Mapper, "WHERE parent_id = 12")));


        // MANY TO MANY RELATIONSHIPS
        $this->databaseConnection->query("DELETE FROM example_many_to_many_link");


        $child2Mapper = new TableMapping("example_parent");
        $childMapper = new TableMapping("example_child2", [new ManyToManyTableRelationship($child2Mapper, "children", "example_many_to_many_link")]);
        $tableMapper = new TableMapping("example_parent", [new ManyToManyTableRelationship($childMapper, "children", "example_many_to_many_link")]);

        $insertData = [
            "name" => "Flash Gordon",
            "children" => [
                [
                    "profession" => "Jason",
                    "children" => [
                        [
                            "name" => "Fisher"
                        ]
                    ]
                ],
                [
                    "profession" => "Joan",
                    "children" => [
                        [
                            "name" => "Rock Climber"
                        ],
                        [
                            "name" => "Helicopter"
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->persistenceEngine->saveRows($tableMapper, $insertData, TablePersistenceEngine::SAVE_OPERATION_INSERT);

        $remapped = $this->queryEngine->query($tableMapper, "WHERE name = 'Flash Gordon'");
        $this->assertEquals(8, $remapped["8"]["id"]);
        $this->assertEquals("Flash Gordon", $remapped["8"]["name"]);
        $this->assertEquals(2, sizeof($remapped["8"]["children"]));
        $this->assertEquals("Jason", $remapped["8"]["children"][0]["profession"]);
        $this->assertEquals("Joan", $remapped["8"]["children"][1]["profession"]);

    }


    public function testCanUpdateDataForSimpleTable() {

        // Create a basic mapping
        $tableMapping = new TableMapping("example");

        $this->persistenceEngine->saveRows($tableMapping, ["id" => 1, "name" => "Conrad"], TablePersistenceEngine::SAVE_OPERATION_UPDATE);

        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1 AND name = 'Conrad'")));

        $this->persistenceEngine->saveRows($tableMapping, [["id" => 2, "name" => "Stephen"], ["id" => 3, "name" => "Willis"], ["id" => 4, "name" => "Pedro"]], TablePersistenceEngine::SAVE_OPERATION_UPDATE);

        $this->assertEquals(2, sizeof($this->queryEngine->query($tableMapping, "WHERE (id = 2 AND name ='Stephen') OR (id = 3 AND name='Willis') OR (id = 5 AND name = 'Pedro')")));

    }


    public function testCanUpdateRelationalDataForRelationshipsAsWellIfSupplied() {

        $childMapper = new TableMapping("example_child_with_parent_key");
        $tableMapper = new TableMapping("example_parent", [new OneToOneTableRelationship($childMapper, "child", "parent_id")]);


        $updateData = [
            "id" => 1,
            "name" => "Michael",
            "child" => [
                "id" => 2,
                "description" => "Swimming Lanes",
                "parent_id" => 1
            ]];


        $this->persistenceEngine->saveRows($tableMapper, $updateData, TablePersistenceEngine::SAVE_OPERATION_UPDATE);


        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapper, "WHERE id = 1 AND name = 'Michael'")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE id = 2 AND description = 'Swimming Lanes' AND parent_id = 1")));


        // Now try a double nested one.
        $childMapper = new TableMapping("example_child_with_parent_key", [
            new OneToOneTableRelationship("example_child_with_parent_key", "child", "parent_id")
        ]);

        $tableMapper = new TableMapping("example_parent", [
            new OneToOneTableRelationship($childMapper, "child", "parent_id")
        ]);


        $updateData = [
            "id" => 1,
            "name" => "Stephanie",
            "child" => [
                "id" => 2,
                "description" => "Cycling Lanes",
                "child" => [
                    "id" => 3,
                    "description" => "Jumping up and down"
                ]
            ]];


        $this->persistenceEngine->saveRows($tableMapper, $updateData);


        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapper, "WHERE id = 1 AND name = 'Stephanie'")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE id = 2 AND description = 'Cycling Lanes' AND parent_id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE id = 3 AND description = 'Jumping up and down' AND parent_id = 2")));

    }


    public function testCanReplaceDataForSimpleTable() {

        // Create a basic mapping
        $tableMapping = new TableMapping("example");

        $this->persistenceEngine->saveRows($tableMapping, [["id" => 1, "name" => "Conrad"], ["name" => "Pierre"]], TablePersistenceEngine::SAVE_OPERATION_REPLACE);

        $this->assertEquals(2, sizeof($this->queryEngine->query($tableMapping, "WHERE (id = 1 AND name = 'Conrad') OR (id = 4 AND name='Pierre')")));

        $this->persistenceEngine->saveRows($tableMapping, [["id" => 2, "name" => "Stephen"], ["id" => 3, "name" => "Willis"], ["name" => "Pedro"]], TablePersistenceEngine::SAVE_OPERATION_REPLACE);

        $this->assertEquals(3, sizeof($this->queryEngine->query($tableMapping, "WHERE (id = 2 AND name ='Stephen') OR (id = 3 AND name='Willis') OR (id = 5 AND name = 'Pedro')")));

    }


    public function testCanReplaceRelationalDataForRelationshipsAsWellIfSupplied() {

        $childMapper = new TableMapping("example_child_with_parent_key");
        $tableMapper = new TableMapping("example_parent", [new OneToOneTableRelationship($childMapper, "child", "parent_id")]);


        $insertData = [
            "id" => 1,
            "name" => "Michael",
            "child" => [
                "description" => "Swimming Lanes"
            ]];


        $this->persistenceEngine->saveRows($tableMapper, $insertData, TablePersistenceEngine::SAVE_OPERATION_REPLACE);


        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapper, "WHERE id = 1 AND name = 'Michael'")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE id = 8 AND description = 'Swimming Lanes' AND parent_id = 1")));


        // Now try a double nested one.
        $childMapper = new TableMapping("example_child_with_parent_key", [
            new OneToOneTableRelationship("example_child_with_parent_key", "child", "parent_id")
        ]);

        $tableMapper = new TableMapping("example_parent", [
            new OneToOneTableRelationship($childMapper, "child", "parent_id")
        ]);


        $insertData = [
            "name" => "Stephanie",
            "child" => [
                "description" => "Cycling Lanes",
                "child" => [
                    "description" => "Jumping up and down"
                ]
            ]];


        $this->persistenceEngine->saveRows($tableMapper, $insertData, TablePersistenceEngine::SAVE_OPERATION_INSERT);


        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapper, "WHERE name = 'Stephanie'")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE description = 'Cycling Lanes' AND parent_id = 5")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapper, "WHERE description = 'Jumping up and down' AND parent_id = 9")));

    }


    public function testCanSaveSimpleObjects() {

        // Create a basic mapping
        $tableMapping = new TableMapping("example");


        $data = [
            "name" => "Piglet",
            "last_modified" => "2020-01-01"
        ];

        $this->persistenceEngine->saveRows($tableMapping, $data);

        $reData = $this->queryEngine->query($tableMapping, "WHERE name = 'Piglet'");
        $this->assertEquals([4 => ["id" => 4, "name" => "Piglet", "last_modified" => "2020-01-01"]], $reData);

        $reData[4]["name"] = "Poppet";
        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE name = 'Poppet'");
        $this->assertEquals([4 => ["id" => 4, "name" => "Poppet", "last_modified" => "2020-01-01"]], $reData);


        $data = [
            [
                "name" => "Pooh",
                "last_modified" => "2021-01-01"
            ],
            [
                "name" => "Christopher Robin",
                "last_modified" => "2022-01-01"
            ]
        ];

        $this->persistenceEngine->saveRows($tableMapping, $data);

        $reData = $this->queryEngine->query($tableMapping, "WHERE name IN ('Pooh', 'Christopher Robin')");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pooh", "last_modified" => "2021-01-01"],
            6 => ["id" => 6, "name" => "Christopher Robin", "last_modified" => "2022-01-01"]], $reData);


    }


    public function testCanSaveOneToOneRelationships() {

        $childMapping = new TableMapping("example_child_with_parent_key");

        $tableMapping = new TableMapping("example_parent", [
            new OneToOneTableRelationship($childMapping,
                "child", "parent_id")
        ]);


        // Do a create
        $data = [
            "name" => "Pickle",
            "child" => [
                "description" => "Pineapple Farming"
            ]
        ];

        $this->persistenceEngine->saveRows($tableMapping, $data);

        $reData = $this->queryEngine->query($tableMapping, "WHERE name = 'Pickle'");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle", "child" => ["id" => 8, "parent_id" => 5, "description" => "Pineapple Farming"], "child_id" => null]], $reData);


        // Do an update
        $reData[5]["child"]["description"] = "Finger Chopping";

        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE name = 'Pickle'");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle", "child" => ["id" => 8, "parent_id" => 5, "description" => "Finger Chopping"], "child_id" => null]], $reData);


        // Do an unlink (set child to null)
        $reData[5]["child"] = null;

        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE name = 'Pickle'");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle", "child_id" => null]], $reData);

        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 8")));


    }


    public function testCanSaveOneToManyRelationships() {

        $childMapping = new TableMapping("example_child_with_parent_key");

        $tableMapping = new TableMapping("example_parent", [
            new OneToManyTableRelationship($childMapping,
                "child", "parent_id")
        ]);


        // Do a create
        $data = [
            "name" => "Pickle",
            "child" => [
                [
                    "description" => "Pineapple Farming"
                ],
                [
                    "description" => "Wind Surfing"
                ],
                [
                    "description" => "Archery"
                ]
            ]
        ];

        $result = $this->persistenceEngine->saveRows($tableMapping, $data);


        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");

        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "child" => [
                ["id" => 8, "parent_id" => 5, "description" => "Pineapple Farming"],
                ["id" => 9, "parent_id" => 5, "description" => "Wind Surfing"],
                ["id" => 10, "parent_id" => 5, "description" => "Archery"]
            ],

            "child_id" => null]], $reData);


        // Do an update
        $reData[5]["child"][1]["description"] = "Finger Chopping";
        $reData[5]["child"][2]["description"] = "Jogging";


        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "child" => [
                ["id" => 8, "parent_id" => 5, "description" => "Pineapple Farming"],
                ["id" => 9, "parent_id" => 5, "description" => "Finger Chopping"],
                ["id" => 10, "parent_id" => 5, "description" => "Jogging"]
            ],

            "child_id" => null]], $reData);


        // Remove one from the stack
        array_splice($reData[5]["child"], 1, 1);

        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "child" => [
                ["id" => 8, "parent_id" => 5, "description" => "Pineapple Farming"],
                ["id" => 10, "parent_id" => 5, "description" => "Jogging"]
            ],

            "child_id" => null]], $reData);


        // Do an unlink (set child to null)
        $reData[5]["child"] = null;

        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE name = 'Pickle'");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle", "child_id" => null]], $reData);

        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id IN (8,9,10)")));


    }

    public function testCanSaveOneToManyRelationshipsWithStaticChildMappings() {

        $childMapping = new TableMapping("example_child_with_parent_and_type");

        $tableMapping = new TableMapping("example_parent", [
            new OneToManyTableRelationship($childMapping,
                "notes", ["parent_id", "type=NOTE"]),
            new OneToManyTableRelationship($childMapping,
                "comments", ["parent_id", "type=COMMENT"]),

        ]);


        // Do a create
        $data = [
            "name" => "Pickle",
            "notes" => [
                [
                    "description" => "Brave"
                ],
                [
                    "description" => "Courageous"
                ],
                [
                    "description" => "Dynamic"
                ]
            ],
            "comments" => [
                [
                    "description" => "Kick boxer"
                ],
                [
                    "description" => "Martial arts expert"
                ]
            ]
        ];

        $result = $this->persistenceEngine->saveRows($tableMapping, $data);


        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");

        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "notes" => [
                ["id" => 8, "parent_id" => 5, "description" => "Brave", "type" => "NOTE"],
                ["id" => 9, "parent_id" => 5, "description" => "Courageous", "type" => "NOTE"],
                ["id" => 10, "parent_id" => 5, "description" => "Dynamic", "type" => "NOTE"]
            ],
            "comments" => [
                ["id" => 11, "parent_id" => 5, "description" => "Kick boxer", "type" => "COMMENT"],
                ["id" => 12, "parent_id" => 5, "description" => "Martial arts expert", "type" => "COMMENT"],
            ],

            "child_id" => null]], $reData);


        // Do an update
        $reData[5]["notes"][1]["description"] = "Strong";
        $reData[5]["comments"][1]["description"] = "Karate Master";


        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");

        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "notes" => [
                ["id" => 8, "parent_id" => 5, "description" => "Brave", "type" => "NOTE"],
                ["id" => 9, "parent_id" => 5, "description" => "Strong", "type" => "NOTE"],
                ["id" => 10, "parent_id" => 5, "description" => "Dynamic", "type" => "NOTE"]
            ],
            "comments" => [
                ["id" => 12, "parent_id" => 5, "description" => "Karate Master", "type" => "COMMENT"],
                ["id" => 11, "parent_id" => 5, "description" => "Kick boxer", "type" => "COMMENT"]
            ],

            "child_id" => null]], $reData);


        // Remove one from the stack
        array_splice($reData[5]["notes"], 1, 1);
        array_splice($reData[5]["comments"], 1, 1);

        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");

        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "notes" => [
                ["id" => 8, "parent_id" => 5, "description" => "Brave", "type" => "NOTE"],
                ["id" => 10, "parent_id" => 5, "description" => "Dynamic", "type" => "NOTE"]
            ],
            "comments" => [
                ["id" => 12, "parent_id" => 5, "description" => "Karate Master", "type" => "COMMENT"]
            ],

            "child_id" => null]], $reData);

        // Do an unlink (set child to null)
        $reData[5]["notes"] = null;

        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE name = 'Pickle'");

        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "comments" => [
                ["id" => 12, "parent_id" => 5, "description" => "Karate Master", "type" => "COMMENT"]
            ],
            "child_id" => null]], $reData);

        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id IN (8,9,10)")));


    }


    public function testCanSaveManyToOneRelationships() {

        $childMapping = new TableMapping("example_child");

        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new ManyToOneTableRelationship($childMapping,
                "child", "child_id", true, true)]);


        $data = [
            "name" => "Bonzo",
            "child" => [
                "description" => "Dog Catching"
            ]
        ];

        $result = $this->persistenceEngine->saveRows($tableMapping, $data);

        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");
        $this->assertEquals([5 => ["id" => 5, "name" => "Bonzo",
            "child" =>
                ["id" => 8, "description" => "Dog Catching", "child2_id" => null],
            "child_id" => 8]], $reData);


        $reData[5]["child"]["description"] = "Cat Stroking";
        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");
        $this->assertEquals([5 => ["id" => 5, "name" => "Bonzo",
            "child" =>
                ["id" => 8, "description" => "Cat Stroking", "child2_id" => null],
            "child_id" => 8]], $reData);


        $reData[5]["child"] = null;
        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");
        $this->assertEquals([5 => ["id" => 5, "name" => "Bonzo",
            "child_id" => null]], $reData);


    }


    public function testCanSaveManyToManyRelationships() {

        $childMapping = new TableMapping("example_child2");
        $tableMapping = new TableMapping("example_parent", [new ManyToManyTableRelationship($childMapping, "children", "example_many_to_many_link")]);


        // Do a create
        $data = [
            "name" => "Pickle",
            "children" => [
                [
                    "profession" => "Pineapple Farming"
                ],
                [
                    "profession" => "Wind Surfing"
                ],
                [
                    "profession" => "Archery"
                ]
            ]
        ];


        $this->persistenceEngine->saveRows($tableMapping, $data);


        // Check update worked and order remains the same.
        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "children" =>
                [
                    ["id" => 6, "profession" => "Pineapple Farming"],
                    ["id" => 7, "profession" => "Wind Surfing"],
                    ["id" => 8, "profession" => "Archery"],
                ],
            "child_id" => null]], $reData);


        $reData[5]["children"][1] = [
            "profession" => "Chatting"
        ];

        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "children" =>
                [
                    ["id" => 6, "profession" => "Pineapple Farming"],
                    ["id" => 9, "profession" => "Chatting"],
                    ["id" => 8, "profession" => "Archery"],
                ],
            "child_id" => null]], $reData);


        // Now remove an item and check update is removed correctly.
        array_splice($reData[5]["children"], 1, 1);

        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "children" =>
                [
                    ["id" => 6, "profession" => "Pineapple Farming"],
                    ["id" => 8, "profession" => "Archery"],
                ],
            "child_id" => null]], $reData);


        $reData[5]["children"] = [];

        $this->persistenceEngine->saveRows($tableMapping, array_values($reData));

        $reData = $this->queryEngine->query($tableMapping, "WHERE id = 5");
        $this->assertEquals([5 => ["id" => 5, "name" => "Pickle",
            "child_id" => null]], $reData);


    }


    public function testCanDeleteSimpleObjects() {


        // Create a basic mapping
        $tableMapping = new TableMapping("example");

        // Delete a single row.
        $data = [
            "id" => 1
        ];

        // Check delete works for single row
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1")));
        $this->persistenceEngine->deleteRows($tableMapping, $data);
        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1")));


        // Delete a single row.
        $data = [
            [
                "id" => 2
            ],
            [
                "id" => 3
            ]
        ];

        // Check delete works for multiple rows
        $this->assertEquals(2, sizeof($this->queryEngine->query($tableMapping, "WHERE id IN (2,3)")));
        $this->persistenceEngine->deleteRows($tableMapping, $data);
        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id IN (2,3)")));


    }


    public function testCanDeleteObjectsWithMultiplePrimaryKeyValuesIncludingNulls() {


        $tableMapping = new TableMapping("example_multi_key");

        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE key_1 = 1 AND key_2 = 1 AND key_3 = 1")));

        $data = [
            "key_1" => 1,
            "key_2" => 1,
            "key_3" => 1
        ];

        $this->persistenceEngine->deleteRows($tableMapping, $data);

        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE key_1 = 1 AND key_2 = 1 AND key_3 = 1")));


        // Null one next
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE key_1 = 1 AND key_2 = 1 AND key_3 IS NULL")));

        $data = [
            "key_1" => 1,
            "key_2" => 1,
            "key_3" => null
        ];

        $this->persistenceEngine->deleteRows($tableMapping, $data);

        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE key_1 = 1 AND key_2 = 1 AND key_3 IS NULL")));


        // All Nulls last
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE key_1 IS NULL AND key_2 IS NULL AND key_3 IS NULL")));


        $data = [
            "key_1" => null,
            "key_2" => null,
            "key_3" => null
        ];

        $this->persistenceEngine->deleteRows($tableMapping, $data);

        // All Nulls last
        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE key_1 IS NULL AND key_2 IS NULL AND key_3 IS NULL")));


    }


    public function testOneToOneRelatedEntitiesAreDeletedOrUnrelatedOnDelete() {

        $childMapping = new TableMapping("example_child_with_parent_key");

        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new OneToOneTableRelationship($childMapping,
                "child1", "parent_id")]);


        // Get the full row.
        $data = $this->queryEngine->query($tableMapping, "WHERE id = 1");

        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 2")));
        $this->persistenceEngine->deleteRows($tableMapping, $data[1]);
        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 2")));


        // Switch off delete cascade
        $tableMapping = new TableMapping("example_parent",
            [new OneToOneTableRelationship($childMapping,
                "child1", "parent_id", false, false)]);

        $data = $this->queryEngine->query($tableMapping, "WHERE id = 2");

        // Check unrelation only when delete cascade is disabled.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 3")));

        $this->persistenceEngine->deleteRows($tableMapping, $data[2]);

        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2")));
        $childRows = $this->queryEngine->query($childMapping, "WHERE id = 3");
        $this->assertEquals(1, sizeof($childRows));
        $this->assertNull($childRows[3]["parent_id"]);

    }


    public function testOneToOneRelatedEntitiesAreDeletedOrUnrelatedOnDeleteWhenShallowParentSuppliedToDelete() {

        $childMapping = new TableMapping("example_child_with_parent_key");

        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new OneToOneTableRelationship($childMapping,
                "child1", "parent_id")]);


        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 2")));
        $this->persistenceEngine->deleteRows($tableMapping, ["id" => 1]);
        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 2")));


        // Switch off delete cascade
        $tableMapping = new TableMapping("example_parent",
            [new OneToOneTableRelationship($childMapping,
                "child1", "parent_id", false, false)]);

        // Check unrelation only when delete cascade is disabled.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 3")));

        $this->persistenceEngine->deleteRows($tableMapping, ["id" => 2]);

        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2")));
        $childRows = $this->queryEngine->query($childMapping, "WHERE id = 3");
        $this->assertEquals(1, sizeof($childRows));
        $this->assertNull($childRows[3]["parent_id"]);

    }


    public function testOneToManyRelatedEntitiesAreDeletedOnDeleteIfDeleteCascadeSet() {

        $childMapping = new TableMapping("example_child_with_parent_key");

        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new OneToManyTableRelationship($childMapping,
                "child1", "parent_id")]);


        // Get the full row.
        $data = $this->queryEngine->query($tableMapping, "WHERE id = 3");

        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 3")));
        $this->assertEquals(3, sizeof($this->queryEngine->query($childMapping, "WHERE parent_id = 3")));
        $this->persistenceEngine->deleteRows($tableMapping, $data[3]);
        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 3")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE parent_id = 3")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id IN (4,5,6)")));


    }


    public function testOneToManyRelatedEntitiesAreUnrelatedOnDeleteIfDeleteCascadeNotSet() {

        $childMapping = new TableMapping("example_child_with_parent_key");

        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new OneToManyTableRelationship($childMapping,
                "child1", "parent_id", false, false)]);


        // Get the full row.
        $data = $this->queryEngine->query($tableMapping, "WHERE id = 3");

        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 3")));
        $this->assertEquals(3, sizeof($this->queryEngine->query($childMapping, "WHERE parent_id = 3")));
        $this->persistenceEngine->deleteRows($tableMapping, $data[3]);
        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 3")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE parent_id = 3")));

        $childRecords = $this->queryEngine->query($childMapping, "WHERE id IN (4,5,6)");
        $this->assertEquals(3, sizeof($childRecords));
        $this->assertNull($childRecords[4]["parent_id"]);
        $this->assertNull($childRecords[5]["parent_id"]);
        $this->assertNull($childRecords[6]["parent_id"]);

    }


    public function testOneToManyRelatedEntitiesAreDeletedOnDeleteWhenShallowParentSuppliedToDelete() {

        $childMapping = new TableMapping("example_child_with_parent_key");

        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new OneToManyTableRelationship($childMapping,
                "child1", "parent_id")]);


        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 3")));
        $this->assertEquals(3, sizeof($this->queryEngine->query($childMapping, "WHERE parent_id = 3")));
        $this->persistenceEngine->deleteRows($tableMapping, ["id" => 3]);
        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 3")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE parent_id = 3")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id IN (4,5,6)")));


    }


    public function testManyToOneRelationshipEntitiesAreDeletedOnDeleteIfDeleteCascadeSet() {

        $childMapping = new TableMapping("example_child");

        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new ManyToOneTableRelationship($childMapping,
                "child1", "child_id", false, true)]);


        // Get the full row.
        $data = $this->queryEngine->query($tableMapping, "WHERE id = 2");

        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2 AND child_id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));

        $this->persistenceEngine->deleteRows($tableMapping, $data[2]);

        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2 AND child_id = 1")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));


    }

    public function testManyToOneRelationshipEntitiesAreNotDeletedOnDeleteIfDeleteCascadeUnSet() {

        $childMapping = new TableMapping("example_child");

        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new ManyToOneTableRelationship($childMapping,
                "child1", "child_id")]);


        // Get the full row.
        $data = $this->queryEngine->query($tableMapping, "WHERE id = 2");

        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2 AND child_id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));

        $this->persistenceEngine->deleteRows($tableMapping, $data[2]);

        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2 AND child_id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));


    }

    public function testManyToOneRelationshipEntitiesAreDeletedOnDeleteWhenShallowParentSupplied() {

        $childMapping = new TableMapping("example_child");

        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new ManyToOneTableRelationship($childMapping,
                "child1", "child_id", false, true)]);


        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2 AND child_id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));

        $this->persistenceEngine->deleteRows($tableMapping, ["id" => 2]);

        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2 AND child_id = 1")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));


    }


    public function testManyToOneRelationshipEntitiesAreDeletedOnDeleteWhenShallowParentSuppliedWithChildKey() {

        $childMapping = new TableMapping("example_child");

        // Create a mapper with a one to one table relationship with another child.
        $tableMapping = new TableMapping("example_parent",
            [new ManyToOneTableRelationship($childMapping,
                "child1", "child_id", false, true)]);


        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2 AND child_id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));

        $this->persistenceEngine->deleteRows($tableMapping, ["id" => 2, "child_id" => 1]);

        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 2 AND child_id = 1")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));


    }


    public function testManyToManyRelationshipEntitiesAreDeletedOnDeleteIfDeleteCascadeSet() {

        $childMapping = new TableMapping("example_child2");

        $tableMapping = new TableMapping("example_parent",
            [new ManyToManyTableRelationship($childMapping, "manytomany", "example_many_to_many_link", false, true)]);


        // Get the full row.
        $data = $this->queryEngine->query($tableMapping, "WHERE id = 1");

        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 2")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 3")));

        $results = $this->databaseConnection->query("SELECT COUNT(*) total FROM example_many_to_many_link WHERE example_parent_id = 1");
        $this->assertEquals(3, $results->nextRow()["total"]);


        $this->persistenceEngine->deleteRows($tableMapping, $data[1]);

        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 2")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 3")));

        $results = $this->databaseConnection->query("SELECT COUNT(*) total FROM example_many_to_many_link WHERE example_parent_id = 1");
        $this->assertEquals(0, $results->nextRow()["total"]);


    }


    public function testManyToManyRelationshipEntitiesAreDeletedOnDeleteIfDeleteCascadeWhenShallowParentSupplied() {

        $childMapping = new TableMapping("example_child2");

        $tableMapping = new TableMapping("example_parent",
            [new ManyToManyTableRelationship($childMapping, "manytomany", "example_many_to_many_link", false, true)]);


        // Check delete works including related entity when delete cascade is set.
        $this->assertEquals(1, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 2")));
        $this->assertEquals(1, sizeof($this->queryEngine->query($childMapping, "WHERE id = 3")));

        $results = $this->databaseConnection->query("SELECT COUNT(*) total FROM example_many_to_many_link WHERE example_parent_id = 1");
        $this->assertEquals(3, $results->nextRow()["total"]);


        $this->persistenceEngine->deleteRows($tableMapping, ["id" => 1]);

        $this->assertEquals(0, sizeof($this->queryEngine->query($tableMapping, "WHERE id = 1")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 1")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 2")));
        $this->assertEquals(0, sizeof($this->queryEngine->query($childMapping, "WHERE id = 3")));

        $results = $this->databaseConnection->query("SELECT COUNT(*) total FROM example_many_to_many_link WHERE example_parent_id = 1");
        $this->assertEquals(0, $results->nextRow()["total"]);

    }

}
