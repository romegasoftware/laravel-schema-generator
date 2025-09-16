<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit\Builders\Zod;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodObjectBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class ZodObjectBuilderTest extends TestCase
{
    #[Test]
    public function it_creates_builder_with_schema_reference(): void
    {
        $builder = new ZodObjectBuilder('UserSchema');
        
        $this->assertInstanceOf(ZodObjectBuilder::class, $builder);
        $this->assertEquals('UserSchema', $builder->getSchemaReference());
    }

    #[Test]
    public function it_returns_schema_reference_as_base_type(): void
    {
        $builder = new ZodObjectBuilder('AddressSchema');
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('getBaseType');
        $method->setAccessible(true);
        
        $this->assertEquals('AddressSchema', $method->invoke($builder));
    }

    #[Test]
    public function it_builds_simple_schema_reference(): void
    {
        $builder = new ZodObjectBuilder('ProfileSchema');
        
        $result = $builder->build();
        
        $this->assertEquals('ProfileSchema', $result);
    }

    #[Test]
    public function it_builds_nullable_schema_reference(): void
    {
        $builder = new ZodObjectBuilder('UserSchema');
        $builder->nullable();
        
        $result = $builder->build();
        
        $this->assertEquals('UserSchema.nullable()', $result);
    }

    #[Test]
    public function it_builds_optional_schema_reference(): void
    {
        $builder = new ZodObjectBuilder('CompanySchema');
        $builder->optional();
        
        $result = $builder->build();
        
        $this->assertEquals('CompanySchema.optional()', $result);
    }

    #[Test]
    public function it_builds_nullable_and_optional_schema_reference(): void
    {
        $builder = new ZodObjectBuilder('OrderSchema');
        $builder->nullable()->optional();
        
        $result = $builder->build();
        
        $this->assertEquals('OrderSchema.nullable().optional()', $result);
    }

    #[Test]
    public function it_validates_and_updates_schema_reference(): void
    {
        $builder = new ZodObjectBuilder('InitialSchema');
        
        // Validate with new schema reference
        $builder->validateSchemaReference(['UpdatedSchema'], 'Custom message');
        
        $this->assertEquals('UpdatedSchema', $builder->getSchemaReference());
        $this->assertEquals('UpdatedSchema', $builder->build());
    }

    #[Test]
    public function it_chains_validate_schema_reference_method(): void
    {
        $builder = new ZodObjectBuilder('FirstSchema');
        
        $result = $builder
            ->validateSchemaReference(['SecondSchema'])
            ->nullable()
            ->optional();
        
        $this->assertSame($builder, $result);
        $this->assertEquals('SecondSchema.nullable().optional()', $builder->build());
    }

    #[Test]
    public function it_handles_array_destructuring_in_validate_schema_reference(): void
    {
        $builder = new ZodObjectBuilder('OldSchema');
        
        // Test with multiple parameters (only first is used)
        $builder->validateSchemaReference(['NewSchema', 'ExtraParam', 'AnotherParam']);
        
        $this->assertEquals('NewSchema', $builder->getSchemaReference());
    }

    #[Test]
    public function it_handles_null_parameters_in_validate_schema_reference(): void
    {
        $builder = new ZodObjectBuilder('OriginalSchema');
        
        // When null parameters are passed with at least one element
        $builder->validateSchemaReference(['']);
        
        // The schema reference should be empty string
        $this->assertEquals('', $builder->getSchemaReference());
    }

    #[Test]
    public function it_handles_empty_string_in_validate_schema_reference(): void
    {
        $builder = new ZodObjectBuilder('TestSchema');
        
        // When array with empty string is passed
        $builder->validateSchemaReference(['']);
        
        // The schema reference would be empty string
        $this->assertEquals('', $builder->getSchemaReference());
        $this->assertEquals('', $builder->build());
    }

    #[Test]
    public function it_inherits_macroable_trait(): void
    {
        // Test that the builder can use macros from parent class
        ZodObjectBuilder::macro('customMethod', function() {
            return 'custom_value';
        });
        
        $builder = new ZodObjectBuilder('Schema');
        
        $this->assertEquals('custom_value', $builder->customMethod());
    }

    #[Test]
    public function it_sets_property_data(): void
    {
        $builder = new ZodObjectBuilder('UserSchema');
        
        $propertyData = new SchemaPropertyData(
            name: 'user',
            validator: null,
            isOptional: false,
            validations: ResolvedValidationSet::make('user', [], 'object')
        );
        
        $builder->setProperty($propertyData);
        
        $this->assertSame($propertyData, $builder->property);
    }

    #[Test]
    public function it_handles_complex_schema_references(): void
    {
        // Test with various schema reference formats
        $testCases = [
            'UserDataSchema' => 'UserDataSchema',
            'App.Models.User' => 'App.Models.User',
            'z.object({})' => 'z.object({})',
            'CustomSchema.extend({})' => 'CustomSchema.extend({})',
        ];
        
        foreach ($testCases as $reference => $expected) {
            $builder = new ZodObjectBuilder($reference);
            $this->assertEquals($expected, $builder->build(), "Failed for reference: $reference");
        }
    }

    #[Test]
    public function it_maintains_immutability_of_schema_reference_in_build(): void
    {
        $builder = new ZodObjectBuilder('ImmutableSchema');
        
        // First build
        $result1 = $builder->build();
        
        // Add nullable
        $builder->nullable();
        $result2 = $builder->build();
        
        // Add optional
        $builder->optional();
        $result3 = $builder->build();
        
        // Verify each build returns the correct result
        $this->assertEquals('ImmutableSchema', $result1);
        $this->assertEquals('ImmutableSchema.nullable()', $result2);
        $this->assertEquals('ImmutableSchema.nullable().optional()', $result3);
    }

    #[Test]
    public function it_handles_translator_setting(): void
    {
        $builder = new ZodObjectBuilder('TranslatedSchema');
        
        // Create a mock translator
        $translator = $this->createMock(\Illuminate\Contracts\Translation\Translator::class);
        
        $result = $builder->setTranslator($translator);
        
        // Should return self for chaining
        $this->assertSame($builder, $result);
    }

    #[Test]
    public function it_calls_setup_method(): void
    {
        $builder = new ZodObjectBuilder('SetupSchema');
        
        $result = $builder->setup();
        
        // Default setup just returns self
        $this->assertSame($builder, $result);
    }

    #[Test]
    public function it_sets_field_name_context(): void
    {
        $builder = new ZodObjectBuilder('ContextSchema');
        
        $result = $builder->setFieldName('user_profile');
        
        // Should return self for chaining
        $this->assertSame($builder, $result);
    }

    #[Test]
    public function it_builds_with_all_inherited_methods(): void
    {
        $builder = new ZodObjectBuilder('CompleteSchema');
        
        $propertyData = new SchemaPropertyData(
            name: 'complete',
            validator: null,
            isOptional: true,
            validations: ResolvedValidationSet::make('complete', [
                new ResolvedValidation('nullable', [], null, false, true),
            ], 'object')
        );
        
        $builder
            ->setProperty($propertyData)
            ->setFieldName('complete')
            ->nullable()
            ->optional();
        
        $result = $builder->build();
        
        $this->assertEquals('CompleteSchema.nullable().optional()', $result);
    }
}