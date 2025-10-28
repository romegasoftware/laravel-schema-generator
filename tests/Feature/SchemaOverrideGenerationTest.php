<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use Illuminate\Foundation\Http\FormRequest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaRule;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class SchemaOverrideGenerationTest extends TestCase
{
    #[Test]
    public function it_extracts_schema_overrides_from_inline_helpers(): void
    {
        $request = new #[ValidationSchema] class extends FormRequest
        {
            public const OVERRIDE_SNIPPET = 'z.array(z.object({ qty: z.number() })).refine(items => items.length > 0)';

            public function authorize(): bool
            {
                return true;
            }

            public function validationRules(): array
            {
                return [
                    'items' => [
                        'required',
                        'array',
                        SchemaRule::literal(
                            function ($attribute, $value, $fail): void {
                                if (collect($value)->sum('qty') < 12) {
                                    $fail('You must order at least 12 total units.');
                                }
                            },
                            self::OVERRIDE_SNIPPET
                        ),
                    ],
                    'items.*.item_id' => ['required', 'integer'],
                    'items.*.quantity' => ['required', 'integer', 'min:1', 'multiple_of:3'],
                ];
            }
        };

        $overrideSnippet = $request::OVERRIDE_SNIPPET;

        /** @var RequestClassExtractor $extractor */
        $extractor = $this->app->make(RequestClassExtractor::class);

        $schemaData = $extractor->extract(new ReflectionClass($request));

        $itemsProperty = $schemaData->properties?->firstWhere('name', 'items');

        $this->assertNotNull($itemsProperty);
        $this->assertNotNull($itemsProperty->schemaOverride);
        $this->assertSame($overrideSnippet, $itemsProperty->schemaOverride->code());

        /** @var ValidationSchemaGenerator $generator */
        $generator = $this->app->make(ValidationSchemaGenerator::class);

        $schema = $generator->generate($schemaData);

        $this->assertStringContainsString($overrideSnippet, $schema);
    }
}
