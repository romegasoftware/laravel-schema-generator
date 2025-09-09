<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use Spatie\LaravelData\DataCollection;

class ValidationSchemaGeneratorTest extends TestCase
{
    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_generates_empty_object_for_no_properties(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'EmptySchema',
            dependencies: [],
            properties: null,
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertEquals('z.object({})', $schema);
    }

    #[Test]
    public function it_stores_processed_schemas(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'test',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('test', [], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $this->generator->generate($extracted);
        $processed = $this->generator->getProcessedSchemas();

        $this->assertArrayHasKey('TestSchema', $processed);
        $this->assertEquals($extracted, $processed['TestSchema']);
    }

    #[Test]
    public function it_tracks_schema_dependencies(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'UserSchema',
            dependencies: ['ProfileData', 'AddressData'],
            properties: null,
            type: '',
            className: ''
        );

        $this->generator->generate($extracted);
        $dependencies = $this->generator->getSchemaDependencies();

        $this->assertArrayHasKey('UserSchema', $dependencies);
        $this->assertEquals(['ProfileData', 'AddressData'], $dependencies['UserSchema']);
    }

    #[Test]
    public function it_handles_string_with_multiple_validations(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'name',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('name', [
                        new ResolvedValidation('required', [], 'The name field is required.', true, false),
                        new ResolvedValidation('min', [2], 'The name field must be at least 2 characters.', false, false),
                        new ResolvedValidation('max', [100], 'The name field may not be greater than 100 characters.', false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Test actual string validation format
        $this->assertStringContainsString('z.string().trim()', $schema);
        $this->assertStringContainsString(".min(2, 'The name field must be at least 2 characters.')", $schema);
        $this->assertStringContainsString(".max(100, 'The name field may not be greater than 100 characters.')", $schema);
    }

    #[Test]
    public function it_handles_integer_validation(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'count',
                    'type' => 'integer',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('count', [
                        new ResolvedValidation('integer', [], 'The count field must be an integer.', false, false),
                        new ResolvedValidation('min', [0], 'The count field must be at least 0.', false, false),
                        new ResolvedValidation('max', [100], 'The count field may not be greater than 100.', false, false),
                    ], 'number'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Test integer validation with proper messages
        // Numbers with integer validation use z.number({error:...})
        $this->assertStringContainsString('count: z.number({error:', $schema);
        $this->assertStringContainsString(".min(0, 'The count field must be at least 0.')", $schema);
        $this->assertStringContainsString(".max(100, 'The count field may not be greater than 100.')", $schema);
        // Integer validation is handled in the base definition, not as .int()
    }

    #[Test]
    public function it_converts_php_regex_correctly(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'pattern',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('pattern', [
                        new ResolvedValidation('required', [], 'The pattern field is required.', true, false),
                        new ResolvedValidation('regex', ['/^[A-Z]{2,4}$/'], 'The pattern field format is invalid.', false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // PHP regex /^[A-Z]{2,4}$/ should convert to JavaScript /^[A-Z]{2,4}$/
        $this->assertStringContainsString('.regex(/^[A-Z]{2,4}$/', $schema);
        $this->assertStringContainsString("'The pattern field format is invalid.')", $schema);
    }

    #[Test]
    public function it_handles_complex_character_class_regex(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'complex_field',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('complex_field', [
                        new ResolvedValidation('regex', ['/^[a-zA-Z0-9\.\-_]+$/'], null, false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // The regex should be converted and applied
        $this->assertStringContainsString('.regex(/^[a-zA-Z0-9', $schema);
    }

    #[Test]
    public function it_generates_schema_names_correctly(): void
    {
        $extracted1 = new ExtractedSchemaData(
            name: 'UserSchema',
            dependencies: [],
            properties: new DataCollection(SchemaPropertyData::class, []),
            type: '',
            className: ''
        );

        $this->generator->generate($extracted1);

        // Test Request class naming
        $extracted2 = new ExtractedSchemaData(
            name: 'CreateUserSchema',
            dependencies: [],
            properties: new DataCollection(SchemaPropertyData::class, []),
            type: '',
            className: ''
        );

        $this->generator->generate($extracted2);

        $schemas = $this->generator->getProcessedSchemas();

        $this->assertArrayHasKey('UserSchema', $schemas);
        $this->assertArrayHasKey('CreateUserSchema', $schemas);
    }

    #[Test]
    public function it_sorts_schemas_correctly(): void
    {
        // Schema with dependency
        $schema1 = new ExtractedSchemaData(
            name: 'OrderSchema',
            dependencies: ['CustomerData'],
            properties: new DataCollection(SchemaPropertyData::class, []),
            type: '',
            className: ''
        );

        $schema2 = new ExtractedSchemaData(
            name: 'CustomerSchema',
            dependencies: [],
            properties: new DataCollection(SchemaPropertyData::class, []),
            type: '',
            className: ''
        );

        $this->generator->generate($schema1);
        $this->generator->generate($schema2);

        $sorted = $this->generator->sortSchemasByDependencies();

        // Customer should come before Order since Order depends on Customer
        $this->assertEquals('CustomerSchema', $sorted[0]->name);
        $this->assertEquals('OrderSchema', $sorted[1]->name);
    }

    #[Test]
    public function it_generates_schemas_with_real_laravel_validation_messages(): void
    {
        // Use the actual LaravelValidationResolver to get realistic messages
        $resolver = $this->app->make(\RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver::class);

        // Create a validator instance
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['published' => true],
            ['published' => 'boolean']
        );

        // Test boolean validation
        $booleanValidationSet = $resolver->resolve('published', 'boolean', $validator);

        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'published',
                    'type' => 'boolean',
                    'isOptional' => false,
                    'validations' => $booleanValidationSet,
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Should generate boolean validation with a message
        $this->assertStringContainsString('published: z.boolean(', $schema);

        // Test email validation
        $emailValidator = new \Illuminate\Validation\Validator(
            $translator,
            ['author_email' => 'test@example.com'],
            ['author_email' => 'required|email']
        );
        $emailValidationSet = $resolver->resolve('author_email', 'required|email', $emailValidator);

        $emailExtracted = new ExtractedSchemaData(
            name: 'EmailSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'author_email',
                    'validator' => null,
                    'isOptional' => false,
                    'validations' => $emailValidationSet,
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $emailSchema = $this->generator->generate($emailExtracted);

        // Should generate email validation with proper messages
        $this->assertStringContainsString('author_email: z.', $emailSchema);
        $this->assertStringContainsString('.email(', $emailSchema);

        // Test array validation
        $arrayValidator = new \Illuminate\Validation\Validator(
            $translator,
            ['tags' => []],
            ['tags' => 'array']
        );
        $arrayValidationSet = $resolver->resolve('tags', 'array', $arrayValidator);

        $arrayExtracted = new ExtractedSchemaData(
            name: 'ArraySchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'tags',
                    'validator' => null,
                    'isOptional' => true,
                    'validations' => $arrayValidationSet,
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $arraySchema = $this->generator->generate($arrayExtracted);

        // Should generate array validation
        $this->assertStringContainsString('tags: z.array(', $arraySchema);
        $this->assertStringContainsString('.optional()', $arraySchema);
    }

    #[Test]
    public function it_properly_formats_laravel_validation_messages_in_zod_v4(): void
    {
        // This test demonstrates that Laravel validation messages are properly
        // passed through to generated Zod schemas in the correct v4 format

        $extracted = new ExtractedSchemaData(
            name: 'ValidationMessageSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'email',
                    'type' => 'email',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('email', [
                        new ResolvedValidation('required', [], 'The email field is required.', true, false),
                        new ResolvedValidation('email', [], 'The email field must be a valid email address.', false, false),
                        new ResolvedValidation('max', [255], 'The email field may not be greater than 255 characters.', false, false),
                    ], 'email'),
                ],
                [
                    'name' => 'age',
                    'type' => 'integer',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('age', [
                        new ResolvedValidation('required', [], 'The age field is required.', true, false),
                        new ResolvedValidation('integer', [], 'The age field must be an integer.', false, false),
                        new ResolvedValidation('min', [18], 'The age field must be at least 18.', false, false),
                        new ResolvedValidation('max', [120], 'The age field may not be greater than 120.', false, false),
                    ], 'number'),
                ],
                [
                    'name' => 'website',
                    'type' => 'url',
                    'isOptional' => true,
                    'validations' => ResolvedValidationSet::make('website', [
                        new ResolvedValidation('url', [], 'The website field must be a valid URL.', false, false),
                    ], 'url'),
                ],
                [
                    'name' => 'uuid',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('uuid', [
                        new ResolvedValidation('required', [], 'The uuid field is required.', true, false),
                        new ResolvedValidation('uuid', [], 'The uuid field must be a valid UUID.', false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Verify email field: should use proper email validation with messages
        $this->assertStringContainsString('email: z.email(', $schema);
        $this->assertStringContainsString('.max(255, ', $schema);
        $this->assertStringContainsString('The email field may not be greater than 255 characters.', $schema);

        // Verify integer field: number with error callback for integer validation
        $this->assertStringContainsString('age: z.number({error:', $schema);
        $this->assertStringContainsString('The age field must be an integer.', $schema);
        $this->assertStringContainsString('.min(18, ', $schema);
        $this->assertStringContainsString('The age field must be at least 18.', $schema);
        $this->assertStringContainsString('.max(120, ', $schema);
        $this->assertStringContainsString('The age field may not be greater than 120.', $schema);

        // Verify URL field: should have url() validation with message and be optional
        $this->assertStringContainsString('website: z.', $schema);
        $this->assertStringContainsString('.url(\'The website field must be a valid URL.\')', $schema);
        $this->assertStringContainsString('.optional()', $schema);

        // Verify UUID field: should have uuid() validation with message
        $this->assertStringContainsString('uuid: z.string().trim()', $schema);
        $this->assertStringContainsString('.uuid(\'The uuid field must be a valid UUID.\')', $schema);

        // Verify overall structure is valid Zod v4
        $this->assertStringStartsWith('z.object({', $schema);
        $this->assertStringEndsWith('})', $schema);
    }
}
