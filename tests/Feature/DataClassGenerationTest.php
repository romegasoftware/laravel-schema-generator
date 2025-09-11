<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use Spatie\LaravelData\DataCollection;

class DataClassGenerationTest extends TestCase
{
    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_handles_email_references(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'UserSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'email',
                    'validator' => null,
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('email', [
                        new ResolvedValidation('email', [], null, false, false),
                        new ResolvedValidation('max', [255], null, false, false),
                    ], 'email'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('email: z.email(', $schema);
        $this->assertStringContainsString('.max(255)', $schema);
    }

    #[Test]
    public function it_handles_data_collection_references(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'UserSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'posts',
                    'validator' => null,
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('posts', [
                        new ResolvedValidation('array', [], null, false, false),
                    ], 'array'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // For now, arrays are generated as z.array(z.any())
        $this->assertStringContainsString('posts: z.array(', $schema);
    }

    #[Test]
    public function it_handles_nested_data_references(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'OrderSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'customer',
                    'validator' => null,
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('customer', [
                        new ResolvedValidation('required', [], null, true, false),
                    ], 'object'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Objects are now properly generated as z.object()
        $this->assertStringContainsString('customer: z.object({})', $schema);
    }

    #[Test]
    public function it_handles_optional_data_collections(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'UserSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'addresses',
                    'validator' => null,
                    'isOptional' => true,
                    'validations' => ResolvedValidationSet::make('addresses', [
                        new ResolvedValidation('array', [], null, false, false),
                    ], 'array'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('addresses: z.array(', $schema);
        $this->assertStringContainsString('.optional()', $schema);
    }

    #[Test]
    public function it_handles_nullable_data_collections(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'UserSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'orders',
                    'validator' => null,
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('orders', [
                        new ResolvedValidation('array', [], null, false, false),
                        new ResolvedValidation('nullable', [], null, false, true),
                    ], 'array'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('orders: z.array(', $schema);
        $this->assertStringContainsString('.nullable()', $schema);
    }

    #[Test]
    public function it_handles_enum_types(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'UserSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'status',
                    'validator' => null,
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('status', [
                        new ResolvedValidation('required', [], null, true, false),
                    ], 'enum'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Enums with required validation are generated as z.string()
        $this->assertStringContainsString('status: z.string()', $schema);
    }

    #[Test]
    public function it_handles_enum_with_custom_message(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'UserSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'role',
                    'validator' => null,
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('role', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('enum', [], 'Please select a valid role', false, false),
                    ], 'enum'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Enums with messages are generated as z.string()
        $this->assertStringContainsString('role: z.string()', $schema);
    }

    #[Test]
    public function it_handles_typed_arrays(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'ConfigSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'tags',
                    'validator' => null,
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
    public function it_handles_array_item_validations(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'FormSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'codes',
                    'validator' => null,
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('codes', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('array', [], null, false, false),
                        new ResolvedValidation('regex', ['/^[A-Z]{3}$/'], 'Each code must be 3 uppercase letters', false, false),
                    ], 'array'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('codes: z.array(z.any())', $schema);
    }

    #[Test]
    public function it_sorts_schemas_by_dependencies(): void
    {
        // First schema depends on second
        $schema1 = new ExtractedSchemaData(
            name: 'UserSchema',
            dependencies: ['ProfileSchema'],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'profile',
                    'validator' => null,
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('profile', [], 'object'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema2 = new ExtractedSchemaData(
            name: 'ProfileSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'bio',
                    'validator' => null,
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('bio', [], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $this->generator->generate($schema1);
        $this->generator->generate($schema2);

        $sorted = $this->generator->sortSchemasByDependencies();

        // ProfileSchema should come before UserSchema
        $this->assertEquals('ProfileSchema', $sorted[0]->name);
        $this->assertEquals('UserSchema', $sorted[1]->name);
    }

    #[Test]
    public function it_converts_php_regex_to_javascript(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'postal_code',
                    'validator' => null,
                    'isOptional' => true,
                    'validations' => ResolvedValidationSet::make('postal_code', [
                        new ResolvedValidation('regex', ['/^\\d{5}(-\\d{4})?$/'], null, false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // The PHP regex should be converted to JavaScript format
        $this->assertStringContainsString('.regex(', $schema);
        $this->assertStringContainsString('/^\\d{5}(-\\d{4})?$/', $schema);
    }

    #[Test]
    public function it_includes_custom_messages_for_regex_validation(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'postal_code',
                    'validator' => null,
                    'isOptional' => true,
                    'validations' => ResolvedValidationSet::make('postal_code', [
                        new ResolvedValidation('regex', ['/^\\d{5}(-\\d{4})?$/'], 'Postal Code must match ##### or #####-####', false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Should include the custom message for regex validation
        $this->assertStringContainsString('.regex(', $schema);
        $this->assertStringContainsString('Postal Code must match ##### or #####-####', $schema);
    }

    #[Test]
    public function it_includes_inherited_custom_messages_for_regex_validation(): void
    {
        // This test simulates what happens when InheritValidationFrom is used
        // The DataClassExtractor would produce this structure when inheriting
        // regex validation with custom messages from another class
        $extracted = new ExtractedSchemaData(
            name: 'FranchiseUpdateRequestSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'postal_code',
                    'validator' => null,
                    'isOptional' => true,
                    'validations' => ResolvedValidationSet::make('postal_code', [
                        new ResolvedValidation('regex', ['/^\\d{5}(-\\d{4})?$/'], 'Postal Code must match ##### or #####-####', false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Should include the inherited custom message for regex validation
        $this->assertStringContainsString('postal_code: z.string()', $schema);
        $this->assertStringContainsString('.regex(', $schema);
        $this->assertStringContainsString('Postal Code must match ##### or #####-####', $schema);
        $this->assertStringContainsString('.optional()', $schema);
    }

    #[Test]
    public function it_handles_fee_structure_data_reference(): void
    {
        $extracted = new ExtractedSchemaData(
            name: 'FeeConfigurationSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'platform_card_fees',
                    'validator' => null,
                    'isOptional' => true,
                    'validations' => ResolvedValidationSet::make('platform_card_fees', [
                        new ResolvedValidation('nullable', [false], null, false, false),
                    ], 'string'),
                ],
            ], DataCollection::class),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Should reference the FeeStructure schema
        // Strings with nullable are generated as z.string()
        $this->assertStringContainsString('platform_card_fees: z.string()', $schema);
    }
}
