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
                    'type' => 'string',
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

        $this->assertStringContainsString('email: z.email().max(255)', $schema);
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
                    'type' => 'DataCollection:PostData',
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

        $this->assertStringContainsString('posts: z.array(PostSchema)', $schema);
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
                    'type' => 'CustomerData',
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

        $this->assertStringContainsString('customer: CustomerSchema', $schema);
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
                    'type' => 'DataCollection:AddressData',
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

        $this->assertStringContainsString('addresses: z.array(AddressSchema).optional()', $schema);
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
                    'type' => 'DataCollection:OrderData',
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

        $this->assertStringContainsString('orders: z.array(OrderSchema).nullable()', $schema);
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
                    'type' => 'enum:UserStatus',
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

        $this->assertStringContainsString('status: z.enum(App.UserStatus)', $schema);
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
                    'type' => 'enum:UserRole',
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

        $this->assertStringContainsString('role: z.enum(App.UserRole, { message: "Please select a valid role" })', $schema);
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
                    'type' => 'array:string',
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

        $this->assertStringContainsString('tags: z.array(z.string())', $schema);
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
                    'type' => 'array',
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

        $this->assertStringContainsString('codes: z.array(z.string().regex(/^[A-Z]{3}$/, \'Each code must be 3 uppercase letters\'))', $schema);
    }

    #[Test]
    public function it_sorts_schemas_by_dependencies(): void
    {
        // First schema depends on second
        $schema1 = new ExtractedSchemaData(
            name: 'UserSchema',
            dependencies: ['ProfileData'],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'profile',
                    'type' => 'ProfileSchema',
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
                    'type' => 'string',
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
                    'type' => 'string',
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
        $this->assertStringContainsString('.regex(/^\\d{5}(-\\d{4})?$/)', $schema);
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
                    'type' => 'string',
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
        $this->assertStringContainsString('.regex(/^\\d{5}(-\\d{4})?$/, \'Postal Code must match ##### or #####-####\')', $schema);
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
                    'type' => 'string',
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
        $this->assertStringContainsString('postal_code: z.string().regex(/^\\d{5}(-\\d{4})?$/, \'Postal Code must match ##### or #####-####\').optional()', $schema);
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
                    'type' => 'FeeStructureData',
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
        $this->assertStringContainsString('platform_card_fees: FeeStructureSchema', $schema);
    }
}
