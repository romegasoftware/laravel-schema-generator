<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodEnumBuilder;

class EnumBuilderConstructionTest extends TestCase
{
    public function test_what_produces_malformed_enum()
    {
        // Test if passing values to constructor creates the issue
        $builder1 = new ZodEnumBuilder(['credit_card', 'paypal', 'bank_transfer']);
        $result1 = $builder1->build();
        echo "Constructor with array: $result1\n";
        
        // Test if passing values as second parameter creates issue
        $builder2 = new ZodEnumBuilder([], 'App.credit_card,paypal,bank_transfer');
        $result2 = $builder2->build();
        echo "Constructor with enum reference: $result2\n";
        
        // Test what happens when values look like enum reference
        $builder3 = new ZodEnumBuilder();
        $builder3->enumReference('App.credit_card,paypal,bank_transfer');
        $result3 = $builder3->build();
        echo "Setting enum reference: $result3\n";
        
        $this->assertEquals('z.enum(App.credit_card,paypal,bank_transfer)', $result3);
    }
}