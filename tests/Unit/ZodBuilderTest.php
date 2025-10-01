<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodEmailBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodEnumBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodNumberBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodStringBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodUrlBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class ZodBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_string_validations_fluently(): void
    {
        $builder = new ZodStringBuilder;

        $result = $builder->validateTrim()
            ->validateMin([5], 'Too short')
            ->validateMax([100], 'Too long')
            ->validateRegex(['/^[A-Z]+$/'], 'Must be uppercase')
            ->build();

        $this->assertEquals("z.string().min(5, 'Too short').max(100, 'Too long').regex(/^[A-Z]+$/, 'Must be uppercase').trim()", $result);
    }

    #[Test]
    public function it_handles_nullable_and_optional_modifiers(): void
    {
        $builder = new ZodStringBuilder;

        $result = $builder->validateMin([1], 'Required')
            ->nullable()
            ->optional()
            ->build();

        $this->assertEquals("z.string().min(1, 'Required').trim().nullable().optional()", $result);
    }

    #[Test]
    public function it_replaces_duplicate_validation_rules(): void
    {
        $builder = new ZodStringBuilder;

        $result = $builder->validateMin([1])
            ->validateMin([5], 'Updated message')  // Should replace the first min
            ->validateMax([10])
            ->validateMax([20])  // Should replace the first max
            ->build();

        $this->assertEquals("z.string().min(5, 'Updated message').max(20).trim()", $result);
    }

    #[Test]
    public function it_builds_number_validations(): void
    {
        $builder = new ZodNumberBuilder;

        $result = $builder->validateMin([0])
            ->validateMax([100])
            ->validateInteger()
            ->build();

        // Note: integer() modifies the base type, not adds a chain
        $this->assertStringContainsString('z.number()', $result);
        $this->assertStringContainsString('.min(0)', $result);
        $this->assertStringContainsString('.max(100)', $result);
    }

    #[Test]
    public function it_builds_email_validations(): void
    {
        $builder = new ZodEmailBuilder;

        $result = $builder
            ->validateMin([1], 'Email required')
            ->validateMax([255])
            ->build();

        $this->assertEquals("z.email().trim().min(1, 'Email required').max(255)", $result);
    }

    #[Test]
    public function it_builds_email_with_required_message_using_zod_v4_approach(): void
    {
        $builder = new ZodEmailBuilder;

        $result = $builder->validateRequired([], 'Email is required')
            ->build();

        // Should use the Zod v4 error callback approach for required messages
        $this->assertEquals("z.email({ error: 'Email is required' }).trim().min(1, 'Email is required')", $result);
    }

    #[Test]
    public function it_builds_email_with_both_custom_email_and_required_messages(): void
    {
        $builder = new ZodEmailBuilder;

        $result = $builder->validateRequired([], 'Email field is mandatory')
            ->validateMax([255], 'Email too long')
            ->build();

        // Should combine both approaches: Zod v4 error callback for required, and custom email message
        $this->assertEquals("z.email({ error: 'Email field is mandatory' }).trim().min(1, 'Email field is mandatory').max(255, 'Email too long')", $result);
    }

    #[Test]
    public function it_builds_email_with_escaped_quotes_in_messages(): void
    {
        $builder = new ZodEmailBuilder;

        $result = $builder->validateRequired([], "Email field can't be blank")
            ->build();

        // Should properly escape quotes in JavaScript strings
        $this->assertEquals("z.email({ error: 'Email field can\\'t be blank' }).trim().min(1, 'Email field can\\'t be blank')", $result);
    }

    #[Test]
    public function it_builds_array_validations(): void
    {
        $factory = app(\RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory::class);
        $universalTypeHandler = app(\RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler::class);
        $factory->setUniversalTypeHandler($universalTypeHandler);

        $builder = $factory->createArrayBuilder('z.string()');

        $result = $builder->validateMin([1], 'At least one required')
            ->validateMax([10])
            ->build();

        $this->assertEquals("z.array(z.string()).min(1, 'At least one required').max(10)", $result);
    }

    #[Test]
    public function it_builds_enum_with_values(): void
    {
        $builder = new ZodEnumBuilder(['active', 'inactive', 'pending']);

        $result = $builder->validateRequired([], 'Invalid status')
            ->nullable()
            ->build();

        $this->assertEquals('z.enum(["active", "inactive", "pending"], { message: "Invalid status" }).nullable()', $result);
    }

    #[Test]
    public function it_builds_enum_with_reference(): void
    {
        $builder = new ZodEnumBuilder([], 'App.StatusEnum');

        $result = $builder->validateRequired([], 'Invalid status')
            ->build();

        $this->assertEquals('z.enum(App.StatusEnum, { message: "Invalid status" })', $result);
    }

    #[Test]
    public function it_escapes_quotes_in_messages(): void
    {
        $builder = new ZodStringBuilder;

        $result = $builder->validateMin([1], "Don't forget quotes")
            ->build();

        $this->assertEquals("z.string().min(1, 'Don\\'t forget quotes').trim()", $result);
    }

    #[Test]
    public function it_builds_url_with_custom_message_and_single_protocol(): void
    {
        $builder = new ZodUrlBuilder;

        $result = $builder
            ->validateUrl(['https'], 'Must use HTTPS')
            ->optional()
            ->build();

        $this->assertEquals("z.url({ error: 'Must use HTTPS', protocol: /^https$/ }).optional()", $result);
    }

    #[Test]
    public function it_builds_url_with_multiple_protocols(): void
    {
        $builder = new ZodUrlBuilder;

        $result = $builder
            ->validateUrl(['http', 'https'])
            ->build();

        $this->assertEquals('z.url({ protocol: /^(?:http|https)$/ })', $result);
    }
}
