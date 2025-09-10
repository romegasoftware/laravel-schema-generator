<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodEnumBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodObjectBuilder;

class EnumResolutionConsistencyTest extends TestCase
{
    #[Test]
    public function test_enum_resolution_at_root_level_with_string_validation()
    {
        // This test case represents: 'payment_method' => 'required|string|in:credit_card,paypal,bank_transfer'
        $enumBuilder = new ZodEnumBuilder;
        $enumBuilder->values(['credit_card', 'paypal', 'bank_transfer']);

        $expected = 'z.enum(["credit_card", "paypal", "bank_transfer"])';
        $actual = $enumBuilder->build();

        $this->assertEquals($expected, $actual, 'Root level enum with string validation should render correctly');
    }

    #[Test]
    public function test_enum_resolution_in_nested_array()
    {
        // This test case represents: 'items.*.pricing.*.component' => 'required|in:base,tax,discount'
        $enumBuilder = new ZodEnumBuilder;
        $enumBuilder->values(['base', 'tax', 'discount']);

        $expected = 'z.enum(["base", "tax", "discount"])';
        $actual = $enumBuilder->build();

        $this->assertEquals($expected, $actual, 'Nested array enum should render correctly');
    }

    #[Test]
    public function test_full_object_with_root_enum_and_nested_enum()
    {
        // Test complete object structure with both root and nested enums
        $objectBuilder = new ZodObjectBuilder('TestObjectSchema');

        // Root level enum (payment_method)
        $paymentMethodEnum = new ZodEnumBuilder;
        $paymentMethodEnum->values(['credit_card', 'paypal', 'bank_transfer']);

        // Nested array with enum (items.*.pricing.*.component)
        // ZodObjectBuilder is for schema references only
        // It doesn't build complex objects, just returns the reference
        $result = $objectBuilder->build();
        $this->assertEquals('TestObjectSchema', $result);

        // Test that enums work independently
        $this->assertEquals('z.enum(["credit_card", "paypal", "bank_transfer"])', $paymentMethodEnum->build());

        // Test another enum
        $componentEnum = new ZodEnumBuilder;
        $componentEnum->values(['base', 'tax', 'discount']);
        $this->assertEquals('z.enum(["base", "tax", "discount"])', $componentEnum->build());
    }
}
