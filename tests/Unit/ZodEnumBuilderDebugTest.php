<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodEnumBuilder;

class ZodEnumBuilderDebugTest extends TestCase
{
    public function test_enum_builder_with_string_values()
    {
        $builder = new ZodEnumBuilder;
        $builder->setValues('enum:credit_card,paypal,bank_transfer');

        $result = $builder->build();

        // Debug output
        var_dump([
            'values' => $builder->getValues(),
            'enumReference' => $builder->getEnumReference(),
            'result' => $result,
        ]);

        $this->assertEquals('z.enum(["credit_card", "paypal", "bank_transfer"])', $result);
    }

    public function test_enum_builder_with_array_values()
    {
        $builder = new ZodEnumBuilder;
        $builder->values(['credit_card', 'paypal', 'bank_transfer']);

        $result = $builder->build();

        $this->assertEquals('z.enum(["credit_card", "paypal", "bank_transfer"])', $result);
    }

    public function test_enum_builder_with_enum_reference()
    {
        $builder = new ZodEnumBuilder;
        $builder->enumReference('App.PaymentMethod');

        $result = $builder->build();

        $this->assertEquals('z.enum(App.PaymentMethod)', $result);
    }
}
