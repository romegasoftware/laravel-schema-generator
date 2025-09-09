<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;

class ZodV4ErrorHandlingTest extends TestCase
{
    private UniversalTypeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new UniversalTypeHandler;
    }

    #[Test]
    public function it_generates_required_field_with_zod_v4_error_callback(): void
    {
        $builder = new ZodStringBuilder;
        $result = $builder->required('Username is required')->trim()->build();

        $this->assertStringContainsString('z.string({', $result);
        $this->assertStringContainsString('error: (iss) => iss.input === undefined || iss.input === null || iss.input === \'\' ? \'Username is required\' : \'Invalid input.\'', $result);
        $this->assertStringContainsString('.trim()', $result);
    }

    #[Test]
    public function it_generates_required_plus_min_as_separate_validations(): void
    {
        $builder = new ZodStringBuilder;
        $result = $builder->required('Title is required')
            ->trim()
            ->min(5, 'Title must be at least 5 characters')
            ->build();

        // Should have required in base string definition
        $this->assertStringContainsString('z.string({', $result);
        $this->assertStringContainsString('Title is required', $result);

        // Should have separate min validation
        $this->assertStringContainsString('.min(5, \'Title must be at least 5 characters\')', $result);

        // Should have trim
        $this->assertStringContainsString('.trim()', $result);
    }

    #[Test]
    public function it_generates_min_only_validation_without_required(): void
    {
        $builder = new ZodStringBuilder;
        $result = $builder->trim()->min(10, 'Must be at least 10 characters')->build();

        // Should NOT have error callback in base definition
        $this->assertStringStartsWith('z.string()', $result);
        $this->assertStringNotContainsString('error:', $result);

        // Should have min validation
        $this->assertStringContainsString('.min(10, \'Must be at least 10 characters\')', $result);
    }

    #[Test]
    public function it_uses_universal_type_handler_for_required_field(): void
    {
        $validationRules = ResolvedValidationSet::make('test_field', [
            new ResolvedValidation('required', [], 'Custom required message', true, false),
        ], 'string');

        $propertyData = new SchemaPropertyData(
            name: 'test_field',
            type: 'string',
            isOptional: false,
            validations: $validationRules
        );

        $this->assertTrue($this->handler->canHandleProperty($propertyData));

        $builder = $this->handler->handle($propertyData);
        $result = $builder->build();

        // Should use custom required message in error callback
        $this->assertStringContainsString('Custom required message', $result);
        $this->assertStringContainsString('z.string({', $result);
    }

    #[Test]
    public function it_uses_laravel_default_message_when_no_custom_message(): void
    {
        $validationRules = ResolvedValidationSet::make('user_name', [
            new ResolvedValidation('required', [], null, true, false),
        ], 'string');

        $propertyData = new SchemaPropertyData(
            name: 'user_name',
            type: 'string',
            isOptional: false,
            validations: $validationRules
        );

        $builder = $this->handler->handle($propertyData);
        $result = $builder->build();

        // Should use Laravel-style default message
        $this->assertStringContainsString('User name field is required', $result);
    }

    #[Test]
    public function it_handles_required_plus_min_validation_separately(): void
    {
        $validationRules = ResolvedValidationSet::make('password', [
            new ResolvedValidation('required', [], 'Password is mandatory', true, false),
            new ResolvedValidation('min', [8], 'Password must be at least 8 characters', false, false),
        ], 'string');

        $propertyData = new SchemaPropertyData(
            name: 'password',
            type: 'string',
            isOptional: false,
            validations: $validationRules
        );

        $builder = $this->handler->handle($propertyData);
        $result = $builder->build();

        // Should have both validations
        $this->assertStringContainsString('Password is mandatory', $result); // Required message
        $this->assertStringContainsString('Password must be at least 8 characters', $result); // Min message
        $this->assertStringContainsString('.min(8,', $result); // Min validation
        $this->assertStringContainsString('z.string({', $result); // Required validation
    }

    #[Test]
    public function it_handles_optional_field_with_min_validation(): void
    {
        $validationRules = ResolvedValidationSet::make('nickname', [
            new ResolvedValidation('min', [3], 'Must be at least 3 characters if provided', false, false),
        ], 'string');

        $propertyData = new SchemaPropertyData(
            name: 'nickname',
            type: 'string',
            isOptional: true,
            validations: $validationRules
        );

        $builder = $this->handler->handle($propertyData);
        $result = $builder->build();

        // Should NOT have error callback for required
        $this->assertStringStartsWith('z.string()', $result);
        $this->assertStringNotContainsString('error:', $result);

        // Should have min validation and optional
        $this->assertStringContainsString('.min(3,', $result);
        $this->assertStringContainsString('Must be at least 3 characters if provided', $result);
        $this->assertStringContainsString('.optional()', $result);
    }

    #[Test]
    public function it_escapes_javascript_special_characters_in_messages(): void
    {
        $builder = new ZodStringBuilder;
        $result = $builder->required("Title can't be empty or contain \"quotes\"")
            ->trim()
            ->build();

        // Should properly escape quotes and apostrophes for JavaScript
        $this->assertStringContainsString("Title can\\'t be empty or contain \\\"quotes\\\"", $result);
        $this->assertStringContainsString('z.string({', $result); // Should have error callback
    }
}
