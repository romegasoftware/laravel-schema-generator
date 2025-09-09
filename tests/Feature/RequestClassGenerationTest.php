<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use Spatie\LaravelData\DataCollection;

class RequestClassGenerationTest extends TestCase
{
    protected RequestClassExtractor $extractor;

    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new RequestClassExtractor;
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
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

        // Mock the ValidationSchema attribute check
        $this->assertTrue(method_exists($testClass, 'rules'));

        $extracted = new ExtractedSchemaData(
            name: 'LoginSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'email',
                    'type' => 'email',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('email', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('email', [], null, false, false),
                        new ResolvedValidation('max', [255], null, false, false),
                    ], 'email'),
                ],
                [
                    'name' => 'password',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('password', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('min', [8], null, false, false),
                    ], 'string'),
                ],
                [
                    'name' => 'remember',
                    'type' => 'boolean',
                    'isOptional' => true,
                    'validations' => ResolvedValidationSet::make('remember', [
                        new ResolvedValidation('boolean', [], null, false, false),
                    ], 'boolean'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('z.object({', $schema);
        $this->assertStringContainsString('email: z.email().max(255', $schema);
        $this->assertStringContainsString('password: z.string().min(8', $schema);
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
                    'validations' => ResolvedValidationSet::make('optional_field', [
                        new ResolvedValidation('nullable', [], null, false, true),
                        new ResolvedValidation('string', [], null, false, false),
                    ], 'string'),
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
                    'validations' => ResolvedValidationSet::make('tags', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('array', [], null, false, false),
                    ], 'array'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('tags: z.array(z.any())', $schema);
    }

    #[Test]
    public function it_handles_nested_array_with_object_items(): void
    {
        // Create nested validation set for array items with multiple properties
        $objectProperties = [
            'title' => ResolvedValidationSet::make(
                'categories.*.title',
                [
                    new ResolvedValidation('string', [], null, false, false),
                    new ResolvedValidation('max', [50], 'Title must be 50 characters or less', false, false),
                ],
                'string'
            ),
            'slug' => ResolvedValidationSet::make(
                'categories.*.slug',
                [
                    new ResolvedValidation('string', [], null, false, false),
                    new ResolvedValidation('max', [30], 'Slug must be 30 characters or less', false, false),
                ],
                'string'
            ),
        ];

        $nestedObjectValidations = ResolvedValidationSet::make(
            'categories.*[object]',
            [],
            'object',
            null,
            $objectProperties
        );

        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'categories',
                    'type' => 'array',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make(
                        'categories',
                        [
                            new ResolvedValidation('required', [], null, true, false),
                            new ResolvedValidation('array', [], null, false, false),
                        ],
                        'array',
                        $nestedObjectValidations
                    ),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Check that we get an array with nested object validation
        $this->assertStringContainsString('categories: z.array(z.object({', $schema);
        $this->assertStringContainsString('title: z.string().max(50', $schema);
        $this->assertStringContainsString('slug: z.string().max(30', $schema);
        $this->assertStringContainsString('Title must be 50 characters or less', $schema);
        $this->assertStringContainsString('Slug must be 30 characters or less', $schema);
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
                    'validations' => ResolvedValidationSet::make('status', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('in', [['pending', 'approved', 'rejected']], null, false, false),
                    ], 'enum:pending,approved,rejected'),
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
                    'validations' => ResolvedValidationSet::make('email', [
                        new ResolvedValidation('required', [], 'Email address is required', true, false),
                        new ResolvedValidation('email', [], 'Please enter a valid email', false, false),
                    ], 'email'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('email: z.email()', $schema);
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
                    'validations' => ResolvedValidationSet::make('phone', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('regex', ['/^\\d{3}-\\d{3}-\\d{4}$/'], null, false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('.regex(/^\\d{3}-\\d{3}-\\d{4}$/', $schema);
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
                    'validations' => ResolvedValidationSet::make('age', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('numeric', [], null, false, false),
                        new ResolvedValidation('min', [18], null, false, false),
                        new ResolvedValidation('max', [120], null, false, false),
                    ], 'number'),
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
                    'validations' => ResolvedValidationSet::make('website', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('url', [], null, false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('website: z.string().url(', $schema);
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
                    'validations' => ResolvedValidationSet::make('uuid', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('uuid', [], null, false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('uuid: z.string().uuid(', $schema);
    }

    #[Test]
    public function it_handles_nested_array_validation(): void
    {
        // Create nested validation set for array items
        $nestedValidations = ResolvedValidationSet::make(
            'tags.*[item]',
            [
                new ResolvedValidation('string', [], null, false, false),
                new ResolvedValidation('max', [50], 'Each tag must be 50 characters or less', false, false),
            ],
            'string'
        );

        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'tags',
                    'type' => 'array',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make(
                        'tags',
                        [
                            new ResolvedValidation('required', [], null, true, false),
                            new ResolvedValidation('array', [], null, false, false),
                        ],
                        'array',
                        $nestedValidations
                    ),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Check that we get an array with nested string validation
        $this->assertStringContainsString('tags: z.array(z.string().max(50', $schema);
        $this->assertStringContainsString('Each tag must be 50 characters or less', $schema);
    }

    #[Test]
    public function it_handles_nested_array_with_simple_items(): void
    {
        // Create nested validation set for simple array items
        $nestedValidations = ResolvedValidationSet::make(
            'tags.*[item]',
            [
                new ResolvedValidation('string', [], null, false, false),
                new ResolvedValidation('max', [50], 'Each tag must be 50 characters or less', false, false),
            ],
            'string'
        );

        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'tags',
                    'type' => 'array',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make(
                        'tags',
                        [
                            new ResolvedValidation('required', [], null, true, false),
                            new ResolvedValidation('array', [], null, false, false),
                        ],
                        'array',
                        $nestedValidations
                    ),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Check that we get an array with nested string validation
        $this->assertStringContainsString('tags: z.array(z.string().max(50', $schema);
        $this->assertStringContainsString('Each tag must be 50 characters or less', $schema);
    }
}
