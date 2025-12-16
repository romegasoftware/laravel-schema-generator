<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit\Writers;

use Illuminate\Support\Facades\File;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Writers\ZodTypeScriptWriter;

class ZodTypeScriptWriterTest extends TestCase
{
    protected ZodTypeScriptWriter $writer;

    protected ValidationSchemaGenerator $mockGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGenerator = ValidationSchemaGenerator::mock();
        $this->writer = new ZodTypeScriptWriter($this->mockGenerator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_generates_namespace_content_with_multiple_schemas(): void
    {
        // Set config to use namespace format
        config(['laravel-schema-generator.zod.output.format' => 'namespace']);
        config(['laravel-schema-generator.zod.output.namespace' => 'Schemas']);

        $userSchema = new ExtractedSchemaData(
            name: 'UserSchema',
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'name',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('name', [], 'string'),
                ],
            ]),
            className: 'App\\Data\\UserData',
            type: 'data',
            dependencies: []
        );

        $profileSchema = new ExtractedSchemaData(
            name: 'ProfileSchema',
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'bio',
                    'type' => 'string',
                    'isOptional' => true,
                    'validations' => ResolvedValidationSet::make('bio', [], 'string'),
                ],
            ]),
            className: 'App\\Data\\ProfileData',
            type: 'data',
            dependencies: []
        );

        $schemas = [$userSchema, $profileSchema];

        // Mock generator methods
        $this->mockGenerator->shouldReceive('generate')
            ->with($userSchema)
            ->andReturn('z.object({ name: z.string() })');

        $this->mockGenerator->shouldReceive('generate')
            ->with($profileSchema)
            ->andReturn('z.object({ bio: z.string().optional() })');

        $this->mockGenerator->shouldReceive('sortSchemasByDependencies')
            ->andReturn($schemas);

        $content = $this->writer->generateContent($schemas);

        // Assert namespace structure
        $this->assertStringContainsString("import { z } from 'zod';", $content);
        $this->assertStringContainsString('export namespace Schemas {', $content);
        $this->assertStringContainsString('export const UserSchema =   z.object({ name: z.string() });', $content);
        $this->assertStringContainsString('export type UserSchemaType = z.infer<typeof UserSchema>;', $content);
        $this->assertStringContainsString('export const ProfileSchema =   z.object({ bio: z.string().optional() });', $content);
        $this->assertStringContainsString('export type ProfileSchemaType = z.infer<typeof ProfileSchema>;', $content);
        $this->assertStringContainsString('}', $content);
    }

    #[Test]
    public function it_generates_module_content_with_app_types_annotation(): void
    {
        // Set config to use module format with App types
        config(['laravel-schema-generator.zod.output.format' => 'module']);
        config(['laravel-schema-generator.use_app_types' => true]);
        config(['laravel-schema-generator.app_prefix' => 'App']);

        $dataSchema = new ExtractedSchemaData(
            name: 'UserDataSchema',
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'email',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('email', [], 'string'),
                ],
            ]),
            className: TestData::class,  // Use TestData which ends with 'Data'
            type: 'data',
            dependencies: []
        );

        $schemas = [$dataSchema];

        // Mock generator methods
        $this->mockGenerator->shouldReceive('generateHeader')
            ->with($schemas)
            ->andReturn("import { z } from 'zod';\nimport { App } from '.';\n\n");

        $this->mockGenerator->shouldReceive('generate')
            ->with($dataSchema)
            ->andReturn('z.object({ email: z.string().email() })');

        $this->mockGenerator->shouldReceive('sortSchemasByDependencies')
            ->andReturn($schemas);

        $this->mockGenerator->shouldReceive('needsAppTypesImport')
            ->with($schemas)
            ->andReturn(true);

        $content = $this->writer->generateContent($schemas);

        // This specifically tests lines 64-70: TypeScript type annotation with App types
        // The format should be: export const SchemaName: z.ZodType<App.ClassName> = schemaDefinition;
        $this->assertStringContainsString('export const UserDataSchema: z.ZodType<App.TestData> = z.object({ email: z.string().email() });', $content);
        $this->assertStringContainsString('export type UserDataSchemaType = z.infer<typeof UserDataSchema>;', $content);
    }

    #[Test]
    public function it_generates_module_content_without_app_types_for_non_data_classes(): void
    {
        config(['laravel-schema-generator.zod.output.format' => 'module']);
        config(['laravel-schema-generator.use_app_types' => true]);

        $requestSchema = new ExtractedSchemaData(
            name: 'CreateUserRequestSchema',
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'name',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('name', [], 'string'),
                ],
            ]),
            className: TestNonDataClass::class,
            type: 'request',
            dependencies: []
        );

        $schemas = [$requestSchema];

        // Mock generator methods
        $this->mockGenerator->shouldReceive('generateHeader')
            ->with($schemas)
            ->andReturn("import { z } from 'zod';\n\n");

        $this->mockGenerator->shouldReceive('generate')
            ->with($requestSchema)
            ->andReturn('z.object({ name: z.string() })');

        $this->mockGenerator->shouldReceive('sortSchemasByDependencies')
            ->andReturn($schemas);

        $this->mockGenerator->shouldReceive('needsAppTypesImport')
            ->with($schemas)
            ->andReturn(false);

        $content = $this->writer->generateContent($schemas);

        // Without App types annotation (not a Data class)
        $this->assertStringContainsString('export const CreateUserRequestSchema = z.object({ name: z.string() });', $content);
        $this->assertStringNotContainsString('z.ZodType<App.', $content);
    }

    #[Test]
    public function it_handles_dependency_sorting_in_namespace_format(): void
    {
        config(['laravel-schema-generator.zod.output.format' => 'namespace']);
        config(['laravel-schema-generator.zod.output.namespace' => 'Schemas']);

        $addressSchema = new ExtractedSchemaData(
            name: 'AddressSchema',
            properties: null,
            className: '',
            type: '',
            dependencies: []
        );

        $userSchema = new ExtractedSchemaData(
            name: 'UserSchema',
            properties: null,
            className: '',
            type: '',
            dependencies: ['AddressSchema']
        );

        // Order matters - user depends on address
        $schemas = [$userSchema, $addressSchema];
        $sortedSchemas = [$addressSchema, $userSchema]; // Expected sorted order

        $this->mockGenerator->shouldReceive('generate')
            ->with($userSchema)
            ->andReturn('z.object({ address: AddressSchema })');

        $this->mockGenerator->shouldReceive('generate')
            ->with($addressSchema)
            ->andReturn('z.object({ street: z.string() })');

        $this->mockGenerator->shouldReceive('sortSchemasByDependencies')
            ->andReturn($sortedSchemas);

        $content = $this->writer->generateContent($schemas);

        // Verify that AddressSchema appears before UserSchema in the output
        $addressPos = strpos($content, 'export const AddressSchema');
        $userPos = strpos($content, 'export const UserSchema');

        $this->assertNotFalse($addressPos);
        $this->assertNotFalse($userPos);
        $this->assertLessThan($userPos, $addressPos, 'AddressSchema should appear before UserSchema');
    }

    #[Test]
    public function it_properly_indents_content_in_namespace_format(): void
    {
        config(['laravel-schema-generator.zod.output.format' => 'namespace']);
        config(['laravel-schema-generator.zod.output.namespace' => 'MySchemas']);

        $schema = new ExtractedSchemaData(
            name: 'TestSchema',
            properties: null,
            className: TestNonDataClass::class,
            type: '',
            dependencies: []
        );

        $multilineSchema = "z.object({\n  field1: z.string(),\n  field2: z.number()\n})";

        $this->mockGenerator->shouldReceive('generate')
            ->with($schema)
            ->andReturn($multilineSchema);

        $this->mockGenerator->shouldReceive('sortSchemasByDependencies')
            ->andReturn([$schema]);

        $content = $this->writer->generateContent([$schema]);

        // Check that multiline content is properly indented within namespace
        $this->assertStringContainsString("  export const TestSchema =   z.object({\n    field1: z.string(),\n    field2: z.number()\n  });", $content);
    }

    #[Test]
    public function it_tests_indent_string_helper_method(): void
    {
        $input = "line1\nline2\nline3";
        $result = $this->writer->indentString($input, 4);

        $expected = "    line1\n    line2\n    line3";
        $this->assertEquals($expected, $result);

        // Test with empty lines
        $inputWithEmpty = "line1\n\nline3";
        $resultWithEmpty = $this->writer->indentString($inputWithEmpty, 2);

        $expectedWithEmpty = "  line1\n\n  line3";
        $this->assertEquals($expectedWithEmpty, $resultWithEmpty);
    }

    #[Test]
    public function it_gets_original_class_name_for_data_classes(): void
    {
        $dataSchema = new ExtractedSchemaData(
            name: 'UserDataSchema',
            properties: null,
            className: 'App\\Data\\UserData',
            type: 'data',
            dependencies: []
        );

        $result = $this->writer->getOriginalClassName($dataSchema);
        $this->assertEquals('UserData', $result);
    }

    #[Test]
    public function it_gets_original_class_name_for_request_classes_with_app_types(): void
    {
        config(['laravel-schema-generator.use_app_types' => true]);

        $requestSchema = new ExtractedSchemaData(
            name: 'CreateUserRequestSchema',
            properties: null,
            className: 'App\\Http\\Requests\\CreateUserRequest',
            type: 'request',
            dependencies: []
        );

        $result = $this->writer->getOriginalClassName($requestSchema);
        $this->assertEquals('CreateUserRequest', $result);
    }

    #[Test]
    public function it_returns_null_for_non_data_non_request_classes(): void
    {
        $otherSchema = new ExtractedSchemaData(
            name: 'SomeSchema',
            properties: null,
            className: 'App\\Services\\SomeService',
            type: 'other',
            dependencies: []
        );

        $result = $this->writer->getOriginalClassName($otherSchema);
        $this->assertNull($result);
    }

    #[Test]
    public function it_identifies_data_classes_correctly(): void
    {
        $dataSchema = new ExtractedSchemaData(
            name: 'UserDataSchema',
            properties: null,
            className: TestData::class,
            type: 'data',
            dependencies: []
        );

        $result = $this->writer->isDataClass($dataSchema);
        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_for_non_data_classes(): void
    {
        $requestSchema = new ExtractedSchemaData(
            name: 'RequestSchema',
            properties: null,
            className: TestNonDataClass::class,
            type: 'request',
            dependencies: []
        );

        $result = $this->writer->isDataClass($requestSchema);
        $this->assertFalse($result);
    }

    #[Test]
    public function it_returns_false_when_class_name_not_set(): void
    {
        // Test with a class that doesn't extend Data
        $schema = new ExtractedSchemaData(
            name: 'Schema',
            properties: null,
            className: '\\stdClass',
            type: '',
            dependencies: []
        );

        $result = $this->writer->isDataClass($schema);
        $this->assertFalse($result);
    }

    #[Test]
    public function it_writes_content_to_file_and_creates_directory(): void
    {
        $testPath = __DIR__.'/../../temp/test-output/schemas.ts';
        config(['laravel-schema-generator.zod.output.path' => $testPath]);

        File::shouldReceive('exists')
            ->once()
            ->with(dirname($testPath))
            ->andReturn(false);

        File::shouldReceive('makeDirectory')
            ->once()
            ->with(dirname($testPath), 0755, true)
            ->andReturn(true);

        File::shouldReceive('put')
            ->once()
            ->with($testPath, Mockery::any())
            ->andReturn(true);

        $schema = new ExtractedSchemaData(
            name: 'TestSchema',
            properties: null,
            className: TestNonDataClass::class,
            type: '',
            dependencies: []
        );

        $this->mockGenerator->shouldReceive('generateHeader')
            ->andReturn("import { z } from 'zod';\n\n");

        $this->mockGenerator->shouldReceive('generate')
            ->andReturn('z.object({})');

        $this->mockGenerator->shouldReceive('sortSchemasByDependencies')
            ->andReturn([$schema]);

        $this->mockGenerator->shouldReceive('needsAppTypesImport')
            ->andReturn(false);

        $this->writer->write([$schema]);

        // Assert that write was called successfully (File facade methods were called)
        $this->assertTrue(true);
    }

    #[Test]
    public function it_gets_output_path_from_config(): void
    {
        $expectedPath = resource_path('js/custom/schemas.ts');
        config(['laravel-schema-generator.zod.output.path' => $expectedPath]);

        $result = $this->writer->getOutputPath();
        $this->assertEquals($expectedPath, $result);
    }

    #[Test]
    public function it_handles_empty_schemas_array_in_namespace_format(): void
    {
        config(['laravel-schema-generator.zod.output.format' => 'namespace']);
        config(['laravel-schema-generator.zod.output.namespace' => 'EmptySchemas']);

        $this->mockGenerator->shouldReceive('sortSchemasByDependencies')
            ->andReturn([]);

        $content = $this->writer->generateContent([]);

        $this->assertStringContainsString("import { z } from 'zod';", $content);
        $this->assertStringContainsString('export namespace EmptySchemas {', $content);
        $this->assertStringContainsString('}', $content);
    }

    #[Test]
    public function it_handles_schemas_with_cached_generation_results(): void
    {
        config(['laravel-schema-generator.zod.output.format' => 'module']);

        $schema1 = new ExtractedSchemaData(
            name: 'Schema1',
            properties: null,
            className: TestNonDataClass::class,
            type: '',
            dependencies: []
        );

        $schema2 = new ExtractedSchemaData(
            name: 'Schema2',
            properties: null,
            className: TestNonDataClass::class,
            type: '',
            dependencies: []
        );

        $schemas = [$schema1, $schema2];

        // First call generates and caches
        $this->mockGenerator->shouldReceive('generateHeader')
            ->with($schemas)
            ->andReturn("import { z } from 'zod';\n\n");

        $this->mockGenerator->shouldReceive('generate')
            ->with($schema1)
            ->once()
            ->andReturn('z.object({ field1: z.string() })');

        $this->mockGenerator->shouldReceive('generate')
            ->with($schema2)
            ->once()
            ->andReturn('z.object({ field2: z.number() })');

        $this->mockGenerator->shouldReceive('sortSchemasByDependencies')
            ->andReturn($schemas);

        $this->mockGenerator->shouldReceive('needsAppTypesImport')
            ->with($schemas)
            ->andReturn(false);

        $content = $this->writer->generateContent($schemas);

        $this->assertStringContainsString('export const Schema1 = z.object({ field1: z.string() });', $content);
        $this->assertStringContainsString('export const Schema2 = z.object({ field2: z.number() });', $content);
    }

    #[Test]
    public function it_generates_separate_files_with_dependencies(): void
    {
        config(['laravel-schema-generator.zod.output.separate_files' => true]);
        $outputDir = '/tmp/schemas';
        config(['laravel-schema-generator.zod.output.directory' => $outputDir]);

        $depSchema = new ExtractedSchemaData(
            name: 'AddressSchema',
            properties: null,
            className: AddressData::class,
            type: 'data',
            dependencies: []
        );

        $mainSchema = new ExtractedSchemaData(
            name: 'UserSchema',
            properties: null,
            className: UserData::class,
            type: 'data',
            dependencies: [AddressData::class]
        );

        $schemas = [$depSchema, $mainSchema];

        // Mocks for File operations
        File::shouldReceive('exists')->with($outputDir)->andReturn(false);
        File::shouldReceive('makeDirectory')->with($outputDir, 0755, true)->once();

        // Mocks for Generator
        // 1. Initial generation loop
        $this->mockGenerator->shouldReceive('generate')->with($depSchema)->andReturn('z.object({ street: z.string() })');
        $this->mockGenerator->shouldReceive('generate')->with($mainSchema)->andReturn('z.object({ address: AddressSchema })');

        // 2. Dependencies
        $this->mockGenerator->shouldReceive('getSchemaDependencies')->andReturn([
            'UserSchema' => [AddressData::class],
        ]);

        // Mock generateSchemaName calls
        $this->mockGenerator->shouldReceive('generateSchemaName')->with(AddressData::class)->andReturn('AddressSchema');

        // 3. Header loop for each schema
        $this->mockGenerator->shouldReceive('generateHeader')->with([$depSchema])->andReturn("import { z } from 'zod';\n");
        $this->mockGenerator->shouldReceive('generateHeader')->with([$mainSchema])->andReturn("import { z } from 'zod';\n");

        // 4. App Types check
        $this->mockGenerator->shouldReceive('needsAppTypesImport')->with([$depSchema])->andReturn(false);
        $this->mockGenerator->shouldReceive('needsAppTypesImport')->with([$mainSchema])->andReturn(false);

        // 5. File::put calls
        // Expect write for AddressSchema
        File::shouldReceive('put')
            ->with($outputDir.DIRECTORY_SEPARATOR.'AddressSchema.ts', Mockery::on(function ($content) {
                return str_contains($content, 'export const AddressSchema = z.object({ street: z.string() });');
            }))
            ->once();

        // Expect write for UserSchema (should have imports)
        File::shouldReceive('put')
            ->with($outputDir.DIRECTORY_SEPARATOR.'UserSchema.ts', Mockery::on(function ($content) {
                return str_contains($content, "import { AddressSchema } from './AddressSchema';")
                    && str_contains($content, 'export const UserSchema = z.object({ address: AddressSchema });');
            }))
            ->once();

        $this->writer->write($schemas);

        $this->assertTrue(true);
    }
}

// Test helper classes
class TestData extends \Spatie\LaravelData\Data
{
    public function __construct(
        public string $email
    ) {}
}

class TestDataClass extends \Spatie\LaravelData\Data
{
    public function __construct(
        public string $email
    ) {}
}

class TestNonDataClass
{
    public function __construct(
        public string $name
    ) {}
}

class AddressData extends \Spatie\LaravelData\Data
{
    public function __construct(
        public string $street
    ) {}
}

class UserData extends \Spatie\LaravelData\Data
{
    public function __construct(
        public AddressData $address
    ) {}
}
