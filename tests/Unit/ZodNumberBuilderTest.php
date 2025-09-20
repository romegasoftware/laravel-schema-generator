<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodNumberBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class ZodNumberBuilderTest extends TestCase
{
    protected ZodNumberBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ZodNumberBuilder;
    }

    #[Test]
    public function test_basic_number_validation()
    {
        $schema = $this->builder->build();
        $this->assertEquals('z.number()', $schema);
    }

    #[Test]
    public function test_integer_validation()
    {
        $schema = $this->builder->validateInteger([], 'Must be an integer')->build();
        $this->assertStringContainsString('z.number({ error: ', $schema);
        $this->assertStringContainsString('Must be an integer', $schema);
    }

    #[Test]
    public function test_min_max_validation()
    {
        $schema = $this->builder->validateMin([5])->validateMax([10])->build();
        $this->assertStringContainsString('.min(5)', $schema);
        $this->assertStringContainsString('.max(10)', $schema);
    }

    #[Test]
    public function test_comparison_validations()
    {
        $schema = $this->builder
            ->validateGt([0])
            ->validateGte([1])
            ->validateLt([100])
            ->validateLte([99])
            ->build();

        $this->assertStringContainsString('.gt(0)', $schema);
        $this->assertStringContainsString('.gte(1)', $schema);
        $this->assertStringContainsString('.lt(100)', $schema);
        $this->assertStringContainsString('.lte(99)', $schema);
    }

    #[Test]
    public function test_multiple_of_validation()
    {
        $schema = $this->builder->validateMultipleOf([5])->build();
        $this->assertStringContainsString('.multipleOf(5)', $schema);
    }

    #[Test]
    public function test_decimal_exact_places()
    {
        $schema = $this->builder->validateDecimal([2])->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('parts[1].length === 2', $schema);
    }

    #[Test]
    public function test_decimal_range()
    {
        $schema = $this->builder->validateDecimal([2, 4])->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('decimals >= 2 && decimals <= 4', $schema);
    }

    #[Test]
    public function test_digits_exact()
    {
        $schema = $this->builder->validateDigits([5])->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('str.length === 5', $schema);
    }

    #[Test]
    public function test_digits_between()
    {
        $schema = $this->builder->validateDigitsBetween([3, 6])->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('str.length >= 3', $schema);
        $this->assertStringContainsString('str.length <= 6', $schema);
    }

    #[Test]
    public function test_max_digits()
    {
        $schema = $this->builder->validateMaxDigits([4])->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('str.length <= 4', $schema);
    }

    #[Test]
    public function test_min_digits()
    {
        $schema = $this->builder->validateMinDigits([2])->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('str.length >= 2', $schema);
    }

    #[Test]
    public function test_combined_validations()
    {
        $schema = $this->builder
            ->validateInteger([], 'Must be integer')
            ->validateMin([1])
            ->validateMax([100])
            ->validateMultipleOf([5])
            ->build();

        $this->assertStringContainsString('z.number({ error:', $schema);
        $this->assertStringContainsString('.min(1)', $schema);
        $this->assertStringContainsString('.max(100)', $schema);
        $this->assertStringContainsString('.multipleOf(5)', $schema);
    }

    #[Test]
    public function test_custom_messages()
    {
        $schema = $this->builder
            ->validateMin([5], 'Must be at least 5')
            ->validateMax([10], 'Must be at most 10')
            ->build();

        $this->assertStringContainsString("'Must be at least 5'", $schema);
        $this->assertStringContainsString("'Must be at most 10'", $schema);
    }
}
