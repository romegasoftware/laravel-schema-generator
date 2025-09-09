<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;
use PHPUnit\Framework\Attributes\Test;

use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodNumberBuilder;

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
        $schema = $this->builder->integer('Must be an integer')->build();
        $this->assertStringContainsString('z.number({error: (val)', $schema);
        $this->assertStringContainsString('Must be an integer', $schema);
    }

    #[Test]
    public function test_min_max_validation()
    {
        $schema = $this->builder->min(5)->max(10)->build();
        $this->assertStringContainsString('.min(5)', $schema);
        $this->assertStringContainsString('.max(10)', $schema);
    }

    #[Test]
    public function test_comparison_validations()
    {
        $schema = $this->builder
            ->gt(0)
            ->gte(1)
            ->lt(100)
            ->lte(99)
            ->build();

        $this->assertStringContainsString('.gt(0)', $schema);
        $this->assertStringContainsString('.gte(1)', $schema);
        $this->assertStringContainsString('.lt(100)', $schema);
        $this->assertStringContainsString('.lte(99)', $schema);
    }

    #[Test]
    public function test_multiple_of_validation()
    {
        $schema = $this->builder->multipleOf(5)->build();
        $this->assertStringContainsString('.multipleOf(5)', $schema);
    }

    #[Test]
    public function test_decimal_exact_places()
    {
        $schema = $this->builder->decimal(2)->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('parts[1].length === 2', $schema);
    }

    #[Test]
    public function test_decimal_range()
    {
        $schema = $this->builder->decimal(2, 4)->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('decimals >= 2 && decimals <= 4', $schema);
    }

    #[Test]
    public function test_digits_exact()
    {
        $schema = $this->builder->digits(5)->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('str.length === 5', $schema);
    }

    #[Test]
    public function test_digits_between()
    {
        $schema = $this->builder->digitsBetween(3, 6)->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('len >= 3 && len <= 6', $schema);
    }

    #[Test]
    public function test_max_digits()
    {
        $schema = $this->builder->maxDigits(4)->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('str.length <= 4', $schema);
    }

    #[Test]
    public function test_min_digits()
    {
        $schema = $this->builder->minDigits(2)->build();
        $this->assertStringContainsString('.refine((val) => {', $schema);
        $this->assertStringContainsString('str.length >= 2', $schema);
    }

    #[Test]
    public function test_positive_negative_validations()
    {
        $positive = $this->builder->positive()->build();
        $negative = (new ZodNumberBuilder)->negative()->build();
        $nonNegative = (new ZodNumberBuilder)->nonNegative()->build();
        $nonPositive = (new ZodNumberBuilder)->nonPositive()->build();

        $this->assertStringContainsString('.positive()', $positive);
        $this->assertStringContainsString('.negative()', $negative);
        $this->assertStringContainsString('.nonnegative()', $nonNegative);
        $this->assertStringContainsString('.nonpositive()', $nonPositive);
    }

    #[Test]
    public function test_finite_validation()
    {
        $schema = $this->builder->finite()->build();
        $this->assertStringContainsString('.finite()', $schema);
    }

    #[Test]
    public function test_combined_validations()
    {
        $schema = $this->builder
            ->integer('Must be integer')
            ->min(1)
            ->max(100)
            ->multipleOf(5)
            ->build();

        $this->assertStringContainsString('z.number({error:', $schema);
        $this->assertStringContainsString('.min(1)', $schema);
        $this->assertStringContainsString('.max(100)', $schema);
        $this->assertStringContainsString('.multipleOf(5)', $schema);
    }

    #[Test]
    public function test_custom_messages()
    {
        $schema = $this->builder
            ->min(5, 'Must be at least 5')
            ->max(10, 'Must be at most 10')
            ->build();

        $this->assertStringContainsString("'Must be at least 5'", $schema);
        $this->assertStringContainsString("'Must be at most 10'", $schema);
    }
}
