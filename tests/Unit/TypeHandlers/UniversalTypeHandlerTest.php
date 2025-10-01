<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit\TypeHandlers;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodArrayBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodBooleanBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodEmailBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodEnumBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodNumberBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodStringBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodUrlBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;

class UniversalTypeHandlerTest extends TestCase
{
    private UniversalTypeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = $this->app->make(UniversalTypeHandler::class);
    }

    #[Test]
    public function it_can_handle_any_type()
    {
        $this->assertTrue($this->handler->canHandle('string'));
        $this->assertTrue($this->handler->canHandle('number'));
        $this->assertTrue($this->handler->canHandle('boolean'));
        $this->assertTrue($this->handler->canHandle('array'));
        $this->assertTrue($this->handler->canHandle('custom'));
    }

    #[Test]
    public function it_can_handle_property_with_resolved_validation_set()
    {
        $validationSet = ResolvedValidationSet::make('test', [], 'string');
        $property = new SchemaPropertyData(
            name: 'test',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $this->assertTrue($this->handler->canHandleProperty($property));
    }

    #[Test]
    public function it_creates_string_builder_for_string_type()
    {
        $validationSet = ResolvedValidationSet::make('name', [], 'string');
        $property = new SchemaPropertyData(
            name: 'name',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);

        $this->assertInstanceOf(ZodStringBuilder::class, $builder);
    }

    #[Test]
    public function it_creates_number_builder_for_number_type()
    {
        $validationSet = ResolvedValidationSet::make('age', [], 'number');
        $property = new SchemaPropertyData(
            name: 'age',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);

        $this->assertInstanceOf(ZodNumberBuilder::class, $builder);
    }

    #[Test]
    public function it_creates_boolean_builder_for_boolean_type()
    {
        $validationSet = ResolvedValidationSet::make('is_active', [], 'boolean');
        $property = new SchemaPropertyData(
            name: 'is_active',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);

        $this->assertInstanceOf(ZodBooleanBuilder::class, $builder);
    }

    #[Test]
    public function it_creates_array_builder_for_array_type()
    {
        $validationSet = ResolvedValidationSet::make('tags', [], 'array');
        $property = new SchemaPropertyData(
            name: 'tags',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);

        $this->assertInstanceOf(ZodArrayBuilder::class, $builder);
    }

    #[Test]
    public function it_creates_email_builder_for_email_type()
    {
        $validationSet = ResolvedValidationSet::make('email', [], 'email');
        $property = new SchemaPropertyData(
            name: 'email',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);

        $this->assertInstanceOf(ZodEmailBuilder::class, $builder);
    }

    #[Test]
    public function it_creates_enum_builder_for_enum_type()
    {
        $validationSet = ResolvedValidationSet::make('status', [], 'enum:pending,approved,rejected');
        $property = new SchemaPropertyData(
            name: 'status',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);

        $this->assertInstanceOf(ZodEnumBuilder::class, $builder);
    }

    #[Test]
    public function it_applies_validations_to_string_builder()
    {
        $validations = [
            new ResolvedValidation('required', [], null, true),
            new ResolvedValidation('min', [3], 'Must be at least 3 characters'),
            new ResolvedValidation('max', [50], 'Must not exceed 50 characters'),
        ];

        $validationSet = ResolvedValidationSet::make('username', $validations, 'string');
        $property = new SchemaPropertyData(
            name: 'username',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);
        $result = $builder->build();

        $this->assertInstanceOf(ZodStringBuilder::class, $builder);
        $this->assertStringContainsString('z.string()', $result);
        $this->assertStringContainsString('.min(3', $result);
        $this->assertStringContainsString('.max(50', $result);
    }

    #[Test]
    public function it_applies_numeric_validations_to_number_builder()
    {
        $validations = [
            new ResolvedValidation('required', [], null, true),
            new ResolvedValidation('integer', []),
            new ResolvedValidation('min', [0]),
            new ResolvedValidation('max', [100]),
        ];

        $validationSet = ResolvedValidationSet::make('score', $validations, 'number');
        $property = new SchemaPropertyData(
            name: 'score',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);
        $result = $builder->build();

        $this->assertInstanceOf(ZodNumberBuilder::class, $builder);
        // Numbers with integer validation use z.number({error:...})
        $this->assertStringContainsString('z.number({ error:', $result);
    }

    #[Test]
    public function it_handles_optional_fields()
    {
        $validationSet = ResolvedValidationSet::make('optional_field', [], 'string');
        $property = new SchemaPropertyData(
            name: 'optional_field',
            validator: null,
            isOptional: true,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);
        $result = $builder->build();

        $this->assertStringContainsString('.optional()', $result);
    }

    #[Test]
    public function it_handles_nullable_fields()
    {
        $validations = [
            new ResolvedValidation('nullable', [], null, false, true),
        ];

        $validationSet = ResolvedValidationSet::make('nullable_field', $validations, 'string');
        $property = new SchemaPropertyData(
            name: 'nullable_field',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);
        $result = $builder->build();

        $this->assertStringContainsString('.nullable()', $result);
    }

    #[Test]
    public function it_converts_php_regex_to_javascript()
    {
        $validations = [
            new ResolvedValidation('regex', ['/^[A-Z]{2,4}$/'], 'Invalid format'),
        ];

        $validationSet = ResolvedValidationSet::make('code', $validations, 'string');
        $property = new SchemaPropertyData(
            name: 'code',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);
        $result = $builder->build();

        $this->assertStringContainsString('/^[A-Z]{2,4}$/', $result);
    }

    #[Test]
    public function it_applies_email_validation()
    {
        $validations = [
            new ResolvedValidation('required', [], null, true),
            new ResolvedValidation('email', []),
        ];

        $validationSet = ResolvedValidationSet::make('email', $validations, 'email');
        $property = new SchemaPropertyData(
            name: 'email',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);

        $this->assertInstanceOf(ZodEmailBuilder::class, $builder);
    }

    #[Test]
    public function it_applies_url_validation()
    {
        $validations = [
            new ResolvedValidation('url', [], 'Must be a valid URL'),
        ];

        $validationSet = ResolvedValidationSet::make('website', $validations, 'url');
        $property = new SchemaPropertyData(
            name: 'website',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);
        $result = $builder->build();

        $this->assertInstanceOf(ZodStringBuilder::class, $builder);
        $this->assertStringContainsString('z.url(', $result);
    }

    #[Test]
    public function it_applies_uuid_validation()
    {
        $validations = [
            new ResolvedValidation('uuid', [], 'Must be a valid UUID'),
        ];

        $validationSet = ResolvedValidationSet::make('id', $validations, 'string');
        $property = new SchemaPropertyData(
            name: 'id',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        $builder = $this->handler->handle($property);
        $result = $builder->build();

        $this->assertInstanceOf(ZodStringBuilder::class, $builder);
        $this->assertStringContainsString('.uuid(', $result);
    }

    #[Test]
    public function it_skips_unknown_validations_gracefully()
    {
        $validations = [
            new ResolvedValidation('required', [], null, true),
            new ResolvedValidation('unknown_rule', ['param1', 'param2']),
            new ResolvedValidation('min', [5]),
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations, 'string');
        $property = new SchemaPropertyData(
            name: 'field',
            validator: null,
            isOptional: false,
            validations: $validationSet
        );

        // Should not throw an exception
        $builder = $this->handler->handle($property);
        $result = $builder->build();

        $this->assertInstanceOf(ZodStringBuilder::class, $builder);
        $this->assertStringContainsString('.min(5', $result);
    }

    #[Test]
    public function it_has_lowest_priority()
    {
        $this->assertEquals(1, $this->handler->getPriority());
    }
}
