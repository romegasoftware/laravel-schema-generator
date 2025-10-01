<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodBooleanBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodEmailBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodEnumBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodNumberBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodStringBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodUrlBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;

class ZodBuilderBehaviorTest extends TestCase
{
    #[Test]
    #[DataProvider('stringBuilderExpectations')]
    public function it_generates_expected_string_builder_output(callable $configure, string $expected): void
    {
        $builder = new ZodStringBuilder();
        $result = $configure($builder)->build();

        $this->assertEquals($expected, $result);
    }

    /**
     * @return iterable<string, array{callable(ZodStringBuilder): ZodStringBuilder, string}>
     */
    public static function stringBuilderExpectations(): iterable
    {
        yield 'min max regex with trim' => [
            fn (ZodStringBuilder $builder) => $builder
                ->validateTrim()
                ->validateMin([5], 'Too short')
                ->validateMax([100], 'Too long')
                ->validateRegex(['/^[A-Z]+$/'], 'Must be uppercase'),
            "z.string().min(5, 'Too short').max(100, 'Too long').regex(/^[A-Z]+$/, 'Must be uppercase').trim()",
        ];

        yield 'nullable optional order' => [
            fn (ZodStringBuilder $builder) => $builder
                ->validateMin([1], 'Required')
                ->nullable()
                ->optional(),
            "z.string().min(1, 'Required').trim().nullable().optional()",
        ];

        yield 'duplicate size validations replace previous ones' => [
            fn (ZodStringBuilder $builder) => $builder
                ->validateMin([1])
                ->validateMin([5], 'Updated message')
                ->validateMax([10])
                ->validateMax([20]),
            "z.string().min(5, 'Updated message').max(20).trim()",
        ];

        yield 'escapes quotes in messages' => [
            fn (ZodStringBuilder $builder) => $builder->validateMin([1], "Don't forget quotes"),
            "z.string().min(1, 'Don\\'t forget quotes').trim()",
        ];
    }

    #[Test]
    public function it_generates_required_email_with_v4_error_callback(): void
    {
        $builder = new ZodEmailBuilder();
        $result = $builder->validateRequired([], 'Email is required')->build();

        $this->assertEquals("z.email({ error: 'Email is required' }).trim().min(1, 'Email is required')", $result);
    }

    #[Test]
    public function it_supports_common_string_refinements(): void
    {
        $builder = new ZodStringBuilder();
        $result = $builder
            ->validateAccepted([], 'Must be accepted')
            ->validateDeclined([], 'Must be declined')
            ->validateStartsWith(['abc'])
            ->validateEndsWith(['xyz'], 'Custom message')
            ->build();

        $this->assertStringContainsString('message: \'Must be accepted\'', $result);
        $this->assertStringContainsString('message: \'Must be declined\'', $result);
        $this->assertStringContainsString(".startsWith('abc')", $result);
        $this->assertStringContainsString(".endsWith('xyz', 'Custom message')", $result);
    }

    #[Test]
    public function it_handles_ip_and_ulid_shortcuts(): void
    {
        $builder = new ZodStringBuilder();
        $result = $builder->validateIp()->build();
        $this->assertStringContainsString('z.union([z.ipv4(), z.ipv6()])', $result);

        $builderIpv4 = new ZodStringBuilder();
        $this->assertStringContainsString("z.ipv4({ message: 'IPv4 required' })", $builderIpv4->validateIpv4([], 'IPv4 required')->build());

        $builderUlid = new ZodStringBuilder();
        $this->assertStringContainsString('z.ulid().optional()', $builderUlid->validateUlid()->optional()->build());
    }

    #[Test]
    public function it_builds_number_constraints(): void
    {
        $builder = new ZodNumberBuilder();
        $result = $builder
            ->validateInteger([], 'Must be an integer')
            ->validateMin([0])
            ->validateMax([100])
            ->build();

        $this->assertEquals("z.number({ error: 'Must be an integer' }).min(0).max(100)", $result);
        $this->assertStringContainsString('.min(0)', $result);
        $this->assertStringContainsString('.max(100)', $result);
    }

    #[Test]
    public function it_builds_decimal_and_digit_refinements(): void
    {
        $builder = new ZodNumberBuilder();
        $decimalExact = $builder->validateDecimal([2])->build();
        $this->assertStringContainsString('parts[1].length === 2', $decimalExact);

        $builder = new ZodNumberBuilder();
        $digitsBetween = $builder->validateDigitsBetween([3, 6])->build();
        $this->assertStringContainsString('str.length >= 3', $digitsBetween);
        $this->assertStringContainsString('str.length <= 6', $digitsBetween);
    }

    #[Test]
    public function it_configures_url_protocol_rules(): void
    {
        $builder = new ZodUrlBuilder();
        $singleProtocol = $builder->validateUrl(['https'], 'Must use HTTPS')->optional()->build();
        $this->assertEquals(
            "z.preprocess((val) => (val === '' ? undefined : val), z.url({ error: 'Must use HTTPS', protocol: /^https$/ }).optional())",
            $singleProtocol,
        );

        $builder = new ZodUrlBuilder();
        $multiple = $builder->validateUrl(['http', 'https'])->build();
        $this->assertEquals('z.url({ protocol: /^(?:http|https)$/ })', $multiple);
    }

    #[Test]
    public function it_builds_array_distinct_validation(): void
    {
        $factory = $this->app->make(ZodBuilderFactory::class);
        $factory->setUniversalTypeHandler($this->app->make(UniversalTypeHandler::class));

        $builder = $factory->createArrayBuilder('z.string()');
        $result = $builder->validateDistinct(['ignore_case'])->build();

        $this->assertStringContainsString('const ignoreCase = true;', $result);
        $this->assertStringContainsString('Duplicate values are not allowed.', $result);
    }

    #[Test]
    public function it_normalizes_boolean_inputs(): void
    {
        $builder = new ZodBooleanBuilder();
        $result = $builder->optional()->build();

        $this->assertStringContainsString('z.preprocess', $result);
        $this->assertStringContainsString('normalized === "true"', $result);
        $this->assertStringContainsString('.optional())', $result);
    }

    #[Test]
    public function it_generates_enum_schemas(): void
    {
        $builder = new ZodEnumBuilder(['active', 'inactive']);
        $result = $builder->validateRequired([], 'Invalid status')->nullable()->build();
        $this->assertEquals('z.enum(["active", "inactive"], { message: "Invalid status" }).nullable()', $result);

        $referenceBuilder = new ZodEnumBuilder([], 'App.StatusEnum');
        $this->assertEquals('z.enum(App.StatusEnum, { message: "Invalid status" })', $referenceBuilder->validateRequired([], 'Invalid status')->build());
    }
}
