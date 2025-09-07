<?php

namespace RomegaSoftware\LaravelZodGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelZodGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\NumberValidationRules;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\StringValidationRules;
use RomegaSoftware\LaravelZodGenerator\Generators\ZodSchemaGenerator;
use RomegaSoftware\LaravelZodGenerator\Tests\TestCase;
use Spatie\LaravelData\DataCollection;

class ZodSchemaGeneratorTest extends TestCase
{
    protected ZodSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ZodSchemaGenerator::class);
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
                    'validations' => StringValidationRules::from([]),
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
                    'validations' => StringValidationRules::from([
                        'required' => true,
                        'min' => 2,
                        'max' => 100,
                        'customMessages' => [
                            'required' => 'Name is required',
                        ],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString("name: z.string().trim().min(2, 'Name is required').max(100)", $schema);
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
                    'validations' => NumberValidationRules::from([
                        'numeric' => true,
                        'min' => 0,
                        'max' => 100,
                        'customMessages' => [],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('count: z.number().min(0).max(100).int()', $schema);
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
                    'validations' => StringValidationRules::from([
                        'required' => true,
                        'regex' => '/^[A-Z]{2,4}$/',
                        'customMessages' => [],
                    ]),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // PHP regex /^[A-Z]{2,4}$/ should convert to JavaScript /^[A-Z]{2,4}$/
        $this->assertStringContainsString('.regex(/^[A-Z]{2,4}$/)', $schema);
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
                    'validations' => StringValidationRules::from([
                        'regex' => '/^[a-zA-Z0-9\.\-_]+$/',
                        'customMessages' => [],
                    ]),
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
}
