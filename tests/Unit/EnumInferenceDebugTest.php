<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;

class EnumInferenceDebugTest extends TestCase
{
    public function test_enum_type_handler_with_inferred_enum()
    {
        $factory = new ZodBuilderFactory();
        $handler = new UniversalTypeHandler($factory);
        $factory->setUniversalTypeHandler($handler);
        
        // Create a property with enum type
        $validations = ResolvedValidationSet::make(
            'payment_method',
            [
                new ResolvedValidation('required', []),
                new ResolvedValidation('string', []),
                new ResolvedValidation('in', ['credit_card', 'paypal', 'bank_transfer']),
            ],
            'enum:credit_card,paypal,bank_transfer'
        );
        
        $property = new SchemaPropertyData(
            name: 'payment_method',
            validator: null,
            isOptional: false,
            validations: $validations
        );
        
        // Debug: Let's see what createBuilderForType returns
        $handler->setProperty($property);
        $builder = $handler->createBuilderForType();
        
        echo "\nBuilder class: " . get_class($builder) . "\n";
        if ($builder instanceof \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodEnumBuilder) {
            echo "Values: " . json_encode($builder->getValues()) . "\n";
            echo "Enum Reference: " . ($builder->getEnumReference() ?? 'null') . "\n";
        }
        
        $result = $handler->handle($property)->build();
        
        echo "Result: $result\n";
        
        $this->assertStringContainsString("z.enum(['credit_card', 'paypal', 'bank_transfer'])", $result);
    }
}