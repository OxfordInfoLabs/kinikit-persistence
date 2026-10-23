<?php

namespace Kinikit\Persistence\Database\PreparedStatement;

use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class BindParameterTest extends TestCase {

    public function testCreateInfersTypesForSupportedValues(): void {
        $blob = new BlobWrapper("blob content");
        $testCases = [
            [123, BindParameterType::TYPE_INT],
            [1.25, BindParameterType::TYPE_FLOAT],
            [true, BindParameterType::TYPE_BOOL],
            ["text", BindParameterType::TYPE_STRING],
            ["1.25", BindParameterType::TYPE_FLOAT],
            [["value"], BindParameterType::TYPE_ARRAY],
            [$blob, BindParameterType::TYPE_BLOB],
            [null, BindParameterType::TYPE_OTHER],
            [new \stdClass(), BindParameterType::TYPE_OTHER]
        ];

        foreach ($testCases as [$value, $expectedType]) {
            $parameter = BindParameter::create($value);

            $this->assertSame($expectedType, $parameter->getType());
            $this->assertSame($value, $parameter->getValue());
        }
    }

    public function testCreateReturnsExistingBindParameterUnchanged(): void {
        $parameter = new BindParameter("2026-10-23", BindParameterType::TYPE_DATE);

        $this->assertSame($parameter, BindParameter::create($parameter));
    }

    public function testCreateAllConvertsValuesAndPreservesKeys(): void {
        $existingParameter = new BindParameter("explicit", BindParameterType::TYPE_STRING);
        $values = [
            "first" => 123,
            "second" => $existingParameter
        ];

        $parameters = BindParameter::createAll($values);

        $this->assertSame(["first", "second"], array_keys($parameters));
        $this->assertSame(BindParameterType::TYPE_INT, $parameters["first"]->getType());
        $this->assertSame(123, $parameters["first"]->getValue());
        $this->assertSame($existingParameter, $parameters["second"]);
    }
}
