<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodEmailBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodEnumBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodNumberBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;

class ZodBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_string_validations_fluently(): void
    {
        $builder = new ZodStringBuilder;

        $result = $builder->trim()
            ->min(5, 'Too short')
            ->max(100, 'Too long')
            ->regex('/^[A-Z]+$/', 'Must be uppercase')
            ->build();

        $this->assertEquals("z.string().trim().min(5, 'Too short').max(100, 'Too long').regex(/^[A-Z]+$/, 'Must be uppercase')", $result);
    }

    #[Test]
    public function it_handles_nullable_and_optional_modifiers(): void
    {
        $builder = new ZodStringBuilder;

        $result = $builder->min(1, 'Required')
            ->nullable()
            ->optional()
            ->build();

        $this->assertEquals("z.string().min(1, 'Required').nullable().optional()", $result);
    }

    #[Test]
    public function it_replaces_duplicate_validation_rules(): void
    {
        $builder = new ZodStringBuilder;

        $result = $builder->min(1)
            ->min(5, 'Updated message')  // Should replace the first min
            ->max(10)
            ->max(20)  // Should replace the first max
            ->build();

        $this->assertEquals("z.string().min(5, 'Updated message').max(20)", $result);
    }

    #[Test]
    public function it_builds_number_validations(): void
    {
        $builder = new ZodNumberBuilder;

        $result = $builder->min(0)
            ->max(100)
            ->int()
            ->build();

        $this->assertEquals('z.number().min(0).max(100).int()', $result);
    }

    #[Test]
    public function it_builds_email_validations(): void
    {
        $builder = new ZodEmailBuilder;

        $result = $builder->trim()
            ->min(1, 'Email required')
            ->max(255)
            ->build();

        $this->assertEquals("z.email().trim().min(1, 'Email required').max(255)", $result);
    }

    #[Test]
    public function it_builds_email_with_custom_email_message(): void
    {
        $builder = new ZodEmailBuilder;

        $result = $builder->emailMessage('Please enter a valid email address')
            ->build();

        $this->assertEquals("z.email().email('Please enter a valid email address')", $result);
    }

    #[Test]
    public function it_builds_email_with_required_message_using_zod_v4_approach(): void
    {
        $builder = new ZodEmailBuilder;

        $result = $builder->required('Email is required')
            ->build();

        // Should use the Zod v4 error callback approach for required messages
        $this->assertEquals("z.email({ error: (val) => (val != undefined && val != null ? 'Email is required' : undefined) }).min(1, 'Email is required')", $result);
    }

    #[Test]
    public function it_builds_email_with_both_custom_email_and_required_messages(): void
    {
        $builder = new ZodEmailBuilder;

        $result = $builder->required('Email field is mandatory')
            ->emailMessage('Must be a valid email format')
            ->max(255, 'Email too long')
            ->build();

        // Should combine both approaches: Zod v4 error callback for required, and custom email message
        $this->assertEquals("z.email({ error: (val) => (val != undefined && val != null ? 'Email field is mandatory' : undefined) }).min(1, 'Email field is mandatory').email('Must be a valid email format').max(255, 'Email too long')", $result);
    }

    #[Test]
    public function it_builds_email_with_escaped_quotes_in_messages(): void
    {
        $builder = new ZodEmailBuilder;

        $result = $builder->emailMessage("Can't be empty or invalid")
            ->required("Email field can't be blank")
            ->build();

        // Should properly escape quotes in JavaScript strings
        $this->assertEquals("z.email({ error: (val) => (val != undefined && val != null ? 'Email field can\\'t be blank' : undefined) }).email('Can\\'t be empty or invalid').min(1, 'Email field can\\'t be blank')", $result);
    }

    #[Test]
    public function it_builds_nullable_optional_email_with_messages(): void
    {
        $builder = new ZodEmailBuilder;

        $result = $builder->emailMessage('Invalid email format')
            ->nullable()
            ->optional()
            ->build();

        $this->assertEquals("z.email().email('Invalid email format').nullable().optional()", $result);
    }

    #[Test]
    public function it_builds_array_validations(): void
    {
        $factory = app(\RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory::class);
        $builder = $factory->createArrayBuilder('z.string()');

        $result = $builder->min(1, 'At least one required')
            ->max(10)
            ->build();

        $this->assertEquals("z.array(z.string()).min(1, 'At least one required').max(10)", $result);
    }

    #[Test]
    public function it_builds_enum_with_values(): void
    {
        $builder = new ZodEnumBuilder(['active', 'inactive', 'pending']);

        $result = $builder->message('Invalid status')
            ->nullable()
            ->build();

        $this->assertEquals('z.enum(["active", "inactive", "pending"], { message: "Invalid status" }).nullable()', $result);
    }

    #[Test]
    public function it_builds_enum_with_reference(): void
    {
        $builder = new ZodEnumBuilder([], 'App.StatusEnum');

        $result = $builder->message('Invalid status')
            ->build();

        $this->assertEquals('z.enum(App.StatusEnum, { message: "Invalid status" })', $result);
    }

    #[Test]
    public function it_escapes_quotes_in_messages(): void
    {
        $builder = new ZodStringBuilder;

        $result = $builder->min(1, "Don't forget quotes")
            ->build();

        $this->assertEquals("z.string().min(1, 'Don\\'t forget quotes')", $result);
    }
}
