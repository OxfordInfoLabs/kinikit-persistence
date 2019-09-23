<?php

namespace Kinikit\Persistence\ORM\SchemaGenerator;

use Kinikit\Core\DependencyInjection\Container;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

/**
 * Created by PhpStorm.
 * User: mark
 * Date: 23/09/2019
 * Time: 14:16
 */
class SchemaGeneratorTest extends TestCase {


    public function testCanGenerateSchemaForTreeOfObjects() {

        /**
         * @var SchemaGenerator $schemaGenerator
         */
        $schemaGenerator = Container::instance()->get(SchemaGenerator::class);
        $generatedSchema = $schemaGenerator->generateSchema();

        $this->assertEquals(5, sizeof($generatedSchema));

    }

}