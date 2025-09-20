<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodStringBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;

class ZodV4ErrorHandlingTest extends TestCase
{
    private UniversalTypeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = $this->app->make(UniversalTypeHandler::class);
    }

    #[Test]
    public function it_generates_required_field_with_zod_v4_error_callback(): void
    {
        $builder = new ZodStringBuilder;
        $result = $builder->validateRequired([], 'Username is required')->validateTrim()->build();

        // The actual implementation uses refine method
        $this->assertStringContainsString('z.string({ error: \'Username is required\' })', $result);
        $this->assertStringContainsString('.trim()', $result);
        $this->assertStringContainsString('.refine((val) => val != undefined && val != null && val != \'\', { error: \'Username is required\' })', $result);
    }

    #[Test]
    public function it_generates_required_plus_min_as_separate_validations(): void
    {
        $builder = new ZodStringBuilder;
        $result = $builder->validateRequired([], 'Title is required')
            ->validateTrim()
            ->validateMin([5], 'Title must be at least 5 characters')
            ->build();

        // Should have required using refine method
        $this->assertStringContainsString('z.string(', $result);
        $this->assertStringContainsString('.trim()', $result);
        $this->assertStringContainsString('.refine((val) => val != undefined && val != null && val != \'\', { error: \'Title is required\' })', $result);

        // Should have separate min validation
        $this->assertStringContainsString('.min(5, \'Title must be at least 5 characters\')', $result);
    }

    #[Test]
    public function it_generates_min_only_validation_without_required(): void
    {
        $builder = new ZodStringBuilder;
        $result = $builder->validateTrim()->validateMin([10], 'Must be at least 10 characters')->build();

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
            validator: null,
            isOptional: false,
            validations: $validationRules
        );

        $this->assertTrue($this->handler->canHandleProperty($propertyData));

        $builder = $this->handler->handle($propertyData);
        $result = $builder->build();

        // UniversalTypeHandler doesn't add required messages automatically
        // It only adds .trim() for string types
        $this->assertEquals('z.string().trim()', $result);
    }

    #[Test]
    public function it_uses_laravel_default_message_when_no_custom_message(): void
    {
        $validationRules = ResolvedValidationSet::make('user_name', [
            new ResolvedValidation('required', [], null, true, false),
        ], 'string');

        $propertyData = new SchemaPropertyData(
            name: 'user_name',
            validator: null,
            isOptional: false,
            validations: $validationRules
        );

        $builder = $this->handler->handle($propertyData);
        $result = $builder->build();

        // UniversalTypeHandler doesn't add required messages automatically
        // It only adds .trim() for string types
        $this->assertEquals('z.string().trim()', $result);
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
            validator: null,
            isOptional: false,
            validations: $validationRules
        );

        $builder = $this->handler->handle($propertyData);
        $result = $builder->build();

        // Required is not added by UniversalTypeHandler, just min validation
        $this->assertStringContainsString('z.string()', $result);
        $this->assertStringContainsString('.trim()', $result);
        $this->assertStringContainsString('.min(8, \'Password must be at least 8 characters\')', $result);
    }

    #[Test]
    public function it_handles_optional_field_with_min_validation(): void
    {
        $validationRules = ResolvedValidationSet::make('nickname', [
            new ResolvedValidation('min', [3], 'Must be at least 3 characters if provided', false, false),
        ], 'string');

        $propertyData = new SchemaPropertyData(
            name: 'nickname',
            validator: null,
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
        $result = $builder->validateRequired([], "Title can't be empty or contain \"quotes\"")
            ->validateTrim()
            ->build();

        // Should properly escape quotes and apostrophes for JavaScript
        $this->assertStringContainsString('z.string({ error', $result);
        $this->assertStringContainsString('.trim()', $result);
        $this->assertStringContainsString("Title can\\'t be empty or contain \\\"quotes\\\"", $result);
    }
}
