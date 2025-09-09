<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Orchestra\Testbench\Attributes\WithMigration;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class TestRequestWithRootEnum extends FormRequest
{
    public function rules(): array
    {
        return [
            'payment_method' => 'required|string|in:credit_card,paypal,bank_transfer',
            'items' => 'required|array',
            'items.*.pricing' => 'required|array',
            'items.*.pricing.*.component' => 'required|in:base,tax,discount',
            'items.*.pricing.*.amount' => 'required|numeric|min:0',
        ];
    }
}

#[WithMigration]
class EnumValidationIntegrationTest extends TestCase
{
    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(ValidationSchemaGenerator::class);
    }

    public function test_enum_validation_consistency_between_root_and_nested()
    {
        $extractor = app(\RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor::class);
        $extracted = $extractor->extract(new \ReflectionClass(TestRequestWithRootEnum::class));
        $schema = $this->generator->generate($extracted, 'TestRequestWithRootEnumSchema');

        // Root level enum should be properly formatted (using double quotes as per Zod standard)
        $this->assertStringContainsString('payment_method: z.enum(["credit_card", "paypal", "bank_transfer"])', $schema);

        // Nested enum should also be properly formatted
        $this->assertStringContainsString('component: z.enum(["base", "tax", "discount"])', $schema);

        // Should NOT contain malformed enum
        $this->assertStringNotContainsString('z.enum(App.', $schema);
        $this->assertStringNotContainsString('z.enum(credit_card', $schema);

        // The full schema should be a valid Zod object
        $this->assertStringContainsString('z.object({', $schema);
    }

    public function test_enum_validation_with_only_in_rule()
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'status' => 'in:active,inactive,pending',
                ];
            }
        };

        $extractor = app(\RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor::class);
        $extracted = $extractor->extract(new \ReflectionClass($request));
        $schema = $this->generator->generate($extracted, 'StatusEnumSchema');

        // Should generate enum correctly even without required|string
        $this->assertStringContainsString('status: z.enum(["active", "inactive", "pending"])', $schema);
    }

    public function test_enum_validation_with_required_in_rule()
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'priority' => 'required|in:low,medium,high',
                ];
            }
        };

        $extractor = app(\RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor::class);
        $extracted = $extractor->extract(new \ReflectionClass($request));
        $schema = $this->generator->generate($extracted, 'PriorityEnumSchema');

        // Should generate enum correctly with required
        $this->assertStringContainsString('priority: z.enum(["low", "medium", "high"])', $schema);
    }
}
