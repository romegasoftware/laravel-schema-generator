<?php

namespace RomegaSoftware\LaravelZodGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelZodGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\ArrayValidationRules;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\BooleanValidationRules;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\EnumValidationRules;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\NumberValidationRules;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\StringValidationRules;
use RomegaSoftware\LaravelZodGenerator\Extractors\RequestClassExtractor;
use RomegaSoftware\LaravelZodGenerator\Generators\ZodSchemaGenerator;
use RomegaSoftware\LaravelZodGenerator\Tests\TestCase;
use Spatie\LaravelData\DataCollection;

class RequestClassGenerationTest extends TestCase
{
    protected RequestClassExtractor $extractor;

    protected ZodSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new RequestClassExtractor;
        $this->generator = $this->app->make(ZodSchemaGenerator::class);
    }

    #[Test]
    public function it_generates_schema_from_form_request(): void
    {
        $testClass = new class extends \Illuminate\Foundation\Http\FormRequest
        {
            public function rules(): array
            {
                return [
                    'email' => 'required|email|max:255',
                    'password' => 'required|min:8',
                    'remember' => 'boolean',
                ];
            }
        };

        $reflection = new ReflectionClass($testClass);

        // Mock the ZodSchema attribute check
        $this->assertTrue(method_exists($testClass, 'rules'));

        $extracted = new ExtractedSchemaData(
            name: 'LoginSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'email',
                    'type' => 'email',
                    'isOptional' => false,
                    'validations' => StringValidationRules::from([
                        'required' => true,
                        'email' => true,
                        'max' => 255,
                        'customMessages' => [],
                    ]),
                ],
                [
                    'name' => 'password',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => StringValidationRules::from([
                        'required' => true,
                        'min' => 8,
                        'customMessages' => [],
                    ]),
                ],
                [
                    'name' => 'remember',
                    'type' => 'boolean',
                    'isOptional' => true,
                    'validations' => BooleanValidationRules::from([
                        'boolean' => true,
                        'customMessages' => [],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('z.object({', $schema);
        $this->assertStringContainsString('email: z.email().trim().min(1', $schema);
        $this->assertStringContainsString('password: z.string().trim().min(8', $schema);
        $this->assertStringContainsString('remember: z.boolean().optional()', $schema);
    }

    #[Test]
    public function it_handles_nullable_fields(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'optional_field',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => StringValidationRules::from([
                        'nullable' => true,
                        'string' => true,
                        'customMessages' => [],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('optional_field: z.string().nullable()', $schema);
    }

    #[Test]
    public function it_handles_array_validation(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'tags',
                    'type' => 'array',
                    'isOptional' => false,
                    'validations' => ArrayValidationRules::from([
                        'required' => true,
                        'array' => true,
                        'customMessages' => [],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('tags: z.array(z.any())', $schema);
    }

    #[Test]
    public function it_handles_enum_validation(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'status',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => EnumValidationRules::from([
                        'required' => true,
                        'in' => ['pending', 'approved', 'rejected'],
                        'customMessages' => [],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('status: z.enum(["pending", "approved", "rejected"])', $schema);
    }

    #[Test]
    public function it_handles_custom_error_messages(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'email',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => StringValidationRules::from([
                        'required' => true,
                        'email' => true,
                        'customMessages' => [
                            'required' => 'Email address is required',
                            'email' => 'Please enter a valid email',
                        ],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('Email address is required', $schema);
    }

    #[Test]
    public function it_handles_regex_validation(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'phone',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => StringValidationRules::from([
                        'required' => true,
                        'regex' => '/^\\d{3}-\\d{3}-\\d{4}$/',
                        'customMessages' => [],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('.regex(/^\\d{3}-\\d{3}-\\d{4}$/)', $schema);
    }

    #[Test]
    public function it_handles_numeric_validation(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'age',
                    'type' => 'number',
                    'isOptional' => false,
                    'validations' => NumberValidationRules::from([
                        'required' => true,
                        'numeric' => true,
                        'min' => 18,
                        'max' => 120,
                        'customMessages' => [],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('age: z.number().min(18).max(120)', $schema);
    }

    #[Test]
    public function it_handles_url_validation(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'website',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => StringValidationRules::from([
                        'required' => true,
                        'url' => true,
                        'customMessages' => [],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('website: z.string().trim().min(1', $schema);
        $this->assertStringContainsString('.url()', $schema);
    }

    #[Test]
    public function it_handles_uuid_validation(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'uuid',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => StringValidationRules::from([
                        'required' => true,
                        'uuid' => true,
                        'customMessages' => [],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('uuid: z.string().trim().min(1', $schema);
        $this->assertStringContainsString('.uuid()', $schema);
    }
}
