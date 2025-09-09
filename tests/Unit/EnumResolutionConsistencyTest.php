<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodArrayBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodEnumBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodObjectBuilder;

class EnumResolutionConsistencyTest extends TestCase
{
    public function test_enum_resolution_at_root_level_with_string_validation()
    {
        // This test case represents: 'payment_method' => 'required|string|in:credit_card,paypal,bank_transfer'
        $enumBuilder = new ZodEnumBuilder;
        $enumBuilder->values(['credit_card', 'paypal', 'bank_transfer']);

        $expected = "z.enum(['credit_card', 'paypal', 'bank_transfer'])";
        $actual = $enumBuilder->build();

        $this->assertEquals($expected, $actual, 'Root level enum with string validation should render correctly');
    }

    public function test_enum_resolution_in_nested_array()
    {
        // This test case represents: 'items.*.pricing.*.component' => 'required|in:base,tax,discount'
        $enumBuilder = new ZodEnumBuilder;
        $enumBuilder->values(['base', 'tax', 'discount']);

        $expected = "z.enum(['base', 'tax', 'discount'])";
        $actual = $enumBuilder->build();

        $this->assertEquals($expected, $actual, 'Nested array enum should render correctly');
    }

    public function test_full_object_with_root_enum_and_nested_enum()
    {
        // Test complete object structure with both root and nested enums
        $objectBuilder = new ZodObjectBuilder;

        // Root level enum (payment_method)
        $paymentMethodEnum = new ZodEnumBuilder;
        $paymentMethodEnum->values(['credit_card', 'paypal', 'bank_transfer']);
        $objectBuilder->addProperty('payment_method', $paymentMethodEnum);

        // Nested array with enum (items.*.pricing.*.component)
        $pricingObjectBuilder = new ZodObjectBuilder;
        $componentEnum = new ZodEnumBuilder;
        $componentEnum->values(['base', 'tax', 'discount']);
        $pricingObjectBuilder->addProperty('component', $componentEnum);

        $pricingArrayBuilder = new ZodArrayBuilder;
        $pricingArrayBuilder->of($pricingObjectBuilder);

        $itemObjectBuilder = new ZodObjectBuilder;
        $itemObjectBuilder->addProperty('pricing', $pricingArrayBuilder);

        $itemsArrayBuilder = new ZodArrayBuilder;
        $itemsArrayBuilder->of($itemObjectBuilder);

        $objectBuilder->addProperty('items', $itemsArrayBuilder);

        $result = $objectBuilder->build();

        // Check that both enums are rendered consistently
        $this->assertStringContainsString("payment_method: z.enum(['credit_card', 'paypal', 'bank_transfer'])", $result);
        $this->assertStringContainsString("component: z.enum(['base', 'tax', 'discount'])", $result);

        // Ensure no malformed enum like "z.enum(App.credit_card, paypal, bank_transfer)"
        $this->assertStringNotContainsString('z.enum(App.', $result);
        $this->assertStringNotContainsString('z.enum(credit_card', $result);
    }
}
