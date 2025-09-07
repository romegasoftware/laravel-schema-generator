<?php

namespace RomegaSoftware\LaravelZodGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelZodGenerator\Tests\TestCase;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodArrayBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodEmailBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodEnumBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodNumberBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodStringBuilder;

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
    public function it_builds_array_validations(): void
    {
        $builder = new ZodArrayBuilder('z.string()');

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
