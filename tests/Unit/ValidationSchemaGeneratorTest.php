<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use Illuminate\Validation\Rule;
use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaFragment;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyCollection;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ValidationRuleFactory;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class ValidationSchemaGeneratorTest extends TestCase
{
    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_prefers_schema_override_when_present(): void
    {
        $override = SchemaFragment::literal('z.array(z.object({ qty: z.number() })).min(1)');

        $extracted = new ExtractedSchemaData(
            name: 'OverrideSchema',
            dependencies: [],
            properties: SchemaPropertyData::collect([
                [
                    'name' => 'items',
                    'type' => 'array',
                    'isOptional' => false,
                    'validations' => ResolvedValidationSet::make('items', [], 'array'),
                    'schemaOverride' => $override,
                ],
            ]),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('items: z.array(z.object({ qty: z.number() })).min(1)', $schema);
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
            ]),
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
            ]),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Test actual string validation format
        $this->assertStringContainsString('z.string()', $schema);
        $this->assertStringContainsString(".min(2, 'The name field must be at least 2 characters.')", $schema);
        $this->assertStringContainsString(".max(100, 'The name field may not be greater than 100 characters.')", $schema);
        $this->assertStringContainsString('.trim()', $schema);
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
            ]),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Test integer validation with proper messages
        // Numbers with integer validation use z.number({error:...})
        $this->assertStringContainsString('count: z.number({ error:', $schema);
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
            ]),
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
            ]),
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
            properties: SchemaPropertyCollection::make([]),
            type: '',
            className: ''
        );

        $this->generator->generate($extracted1);

        // Test Request class naming
        $extracted2 = new ExtractedSchemaData(
            name: 'CreateUserRequestSchema',
            dependencies: [],
            properties: SchemaPropertyCollection::make([]),
            type: '',
            className: ''
        );

        $this->generator->generate($extracted2);

        $schemas = $this->generator->getProcessedSchemas();

        $this->assertArrayHasKey('UserSchema', $schemas);
        $this->assertArrayHasKey('CreateUserRequestSchema', $schemas);
    }

    #[Test]
    public function it_sorts_schemas_correctly(): void
    {
        // Schema with dependency
        $schema1 = new ExtractedSchemaData(
            name: 'OrderDataSchema',
            dependencies: ['CustomerDataSchema'],
            properties: SchemaPropertyCollection::make([]),
            type: '',
            className: ''
        );

        $schema2 = new ExtractedSchemaData(
            name: 'CustomerDataSchema',
            dependencies: [],
            properties: SchemaPropertyCollection::make([]),
            type: '',
            className: ''
        );

        $this->generator->generate($schema1);
        $this->generator->generate($schema2);

        $sorted = $this->generator->sortSchemasByDependencies();

        // Customer should come before Order since Order depends on Customer
        $this->assertEquals('CustomerDataSchema', $sorted[0]->name);
        $this->assertEquals('OrderDataSchema', $sorted[1]->name);
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
            ]),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Should generate boolean validation with preprocessing for truthy/falsey values
        $this->assertStringContainsString('published: z.preprocess((val) =>', $schema);

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
            ]),
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
            ]),
            type: '',
            className: ''
        );

        $arraySchema = $this->generator->generate($arrayExtracted);

        // Should generate array validation
        $this->assertStringContainsString('tags: z.array(', $arraySchema);
        $this->assertStringContainsString('.optional()', $arraySchema);
    }

    #[Test]
    public function it_generates_super_refine_for_required_if_rules(): void
    {
        $passwordValidations = ResolvedValidationSet::make('password', [
            new ResolvedValidation('Nullable', [], null, false, true),
            new ResolvedValidation('RequiredIf', ['auth_type', 'password'], 'The password field is required.', false, false),
        ], 'string');

        $schemas = SchemaPropertyData::collect([
            [
                'name' => 'auth_type',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('auth_type', [], 'string'),
                'validator' => null,
            ],
            [
                'name' => 'password',
                'isOptional' => true,
                'validations' => $passwordValidations,
                'validator' => null,
            ],
        ]);

        $extracted = new ExtractedSchemaData(
            name: 'AuthSchema',
            dependencies: [],
            properties: $schemas,
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('.superRefine((data, ctx) => {', $schema);
        $this->assertStringContainsString("if (String(data.auth_type) === 'password' && (data.password === undefined || data.password === null || String(data.password).trim() === '')) {", $schema);
        $this->assertStringContainsString("code: 'custom'", $schema);
        $this->assertStringContainsString("message: 'The password field is required.'", $schema);
        $this->assertStringContainsString("path: ['password']", $schema);
    }

    #[Test]
    public function it_generates_super_refine_for_required_if_rule_objects(): void
    {
        $factory = $this->app->make(\Illuminate\Validation\Factory::class);
        $resolver = $this->app->make(LaravelValidationResolver::class);
        $ruleFactory = new ValidationRuleFactory;

        try {
            request()->replace(['status' => 'shipped']);

            $validator = $factory->make(
                [
                    'status' => 'shipped',
                    'tracking_number' => null,
                ],
                [
                    'status' => 'required|in:processing,shipped',
                    'tracking_number' => [
                        'nullable',
                        'string',
                        Rule::requiredIf(fn (): bool => request()->input('status') === 'shipped'),
                    ],
                ]
            );

            $statusValidations = $resolver->resolve(
                'status',
                'required|in:processing,shipped',
                $validator
            );

            $trackingValidations = $resolver->resolve(
                'tracking_number',
                $ruleFactory->normalizeRule([
                    'nullable',
                    'string',
                    Rule::requiredIf(fn (): bool => request()->input('status') === 'shipped'),
                ]),
                $validator
            );

            $properties = SchemaPropertyData::collect([
                [
                    'name' => 'status',
                    'validator' => $validator,
                    'isOptional' => ! $statusValidations->isFieldRequired(),
                    'validations' => $statusValidations,
                ],
                [
                    'name' => 'tracking_number',
                    'validator' => $validator,
                    'isOptional' => ! $trackingValidations->isFieldRequired(),
                    'validations' => $trackingValidations,
                ],
            ]);

            $extracted = new ExtractedSchemaData(
                name: 'OrderStatusTransitionSchema',
                dependencies: [],
                properties: $properties,
                type: '',
                className: ''
            );

            $schema = $this->generator->generate($extracted);

            $this->assertStringContainsString('.superRefine((data, ctx) => {', $schema);
            $this->assertStringContainsString("if (String(data.status) === 'shipped' && (data.tracking_number === undefined || data.tracking_number === null || String(data.tracking_number).trim() === '')) {", $schema);
            $this->assertStringContainsString("message: 'The tracking number field is required when status is shipped.'", $schema);
            $this->assertStringContainsString("path: ['tracking_number']", $schema);
        } finally {
            request()->replace([]);
        }
    }

    #[Test]
    public function it_generates_super_refine_for_prohibited_if_rule_objects(): void
    {
        $factory = $this->app->make(\Illuminate\Validation\Factory::class);
        $resolver = $this->app->make(LaravelValidationResolver::class);
        $ruleFactory = new ValidationRuleFactory;

        request()->replace(['status' => 'shipped']);

        try {
            $validator = $factory->make(
                [
                    'status' => 'shipped',
                    'internal_note' => null,
                ],
                [
                    'status' => 'required|in:processing,shipped',
                    'internal_note' => [
                        'nullable',
                        'string',
                        Rule::prohibitedIf(fn (): bool => request()->input('status') === 'shipped'),
                    ],
                ]
            );

            $statusValidations = $resolver->resolve(
                'status',
                'required|in:processing,shipped',
                $validator
            );

            $noteValidations = $resolver->resolve(
                'internal_note',
                $ruleFactory->normalizeRule([
                    'nullable',
                    'string',
                    Rule::prohibitedIf(fn (): bool => request()->input('status') === 'shipped'),
                ]),
                $validator
            );

            $properties = SchemaPropertyData::collect([
                [
                    'name' => 'status',
                    'validator' => $validator,
                    'isOptional' => ! $statusValidations->isFieldRequired(),
                    'validations' => $statusValidations,
                ],
                [
                    'name' => 'internal_note',
                    'validator' => $validator,
                    'isOptional' => ! $noteValidations->isFieldRequired(),
                    'validations' => $noteValidations,
                ],
            ]);

            $extracted = new ExtractedSchemaData(
                name: 'OrderStatusWithProhibitedNote',
                dependencies: [],
                properties: $properties,
                type: '',
                className: ''
            );

            $schema = $this->generator->generate($extracted);

            $this->assertStringContainsString('.superRefine((data, ctx) => {', $schema);
            $this->assertStringContainsString("if (String(data.status) === 'shipped' && !(data.internal_note === undefined || data.internal_note === null || String(data.internal_note).trim() === '')) {", $schema);
            $this->assertStringContainsString("message: 'The internal note field is prohibited when status is shipped.'", $schema);
            $this->assertStringContainsString("path: ['internal_note']", $schema);
        } finally {
            request()->replace([]);
        }
    }

    #[Test]
    public function it_matches_required_if_parameters_using_string_comparison(): void
    {
        $flagValidations = ResolvedValidationSet::make('feature_flag', [
            new ResolvedValidation('RequiredIf', ['plan', 'pro', 'enterprise'], 'Feature flag is required.', false, false),
        ], 'string');

        $properties = SchemaPropertyData::collect([
            [
                'name' => 'plan',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('plan', [], 'string'),
                'validator' => null,
            ],
            [
                'name' => 'feature_flag',
                'isOptional' => true,
                'validations' => $flagValidations,
                'validator' => null,
            ],
        ]);

        $extracted = new ExtractedSchemaData(
            name: 'PlanSchema',
            dependencies: [],
            properties: $properties,
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString("['pro', 'enterprise'].includes(String(data.plan))", $schema);
    }

    #[Test]
    public function it_normalizes_required_if_references_from_denormalized_data_rules(): void
    {
        $dependentValidation = ResolvedValidationSet::make('existing_account_id', [
            new ResolvedValidation(
                'RequiredIf',
                ['existing_account_id.connection_type', 'existing'],
                'Existing account id is required.',
                false,
                false
            ),
        ], 'string');

        $properties = SchemaPropertyData::collect([
            [
                'name' => 'connection_type',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('connection_type', [], 'string'),
                'validator' => null,
            ],
            [
                'name' => 'existing_account_id',
                'isOptional' => true,
                'validations' => $dependentValidation,
                'validator' => null,
            ],
        ]);

        $extracted = new ExtractedSchemaData(
            name: 'RequiredIfNormalizationSchema',
            dependencies: [],
            properties: $properties,
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString("String(data.connection_type) === 'existing'", $schema);
        $this->assertStringContainsString('Existing account id is required.', $schema);
    }

    #[Test]
    public function it_normalizes_required_if_references_for_nested_data_properties(): void
    {
        $nestedValidation = ResolvedValidationSet::make('profile.existing_account_id', [
            new ResolvedValidation(
                'RequiredIf',
                ['profile.existing_account_id.connection_type', 'existing'],
                'Nested account id is required.',
                false,
                false
            ),
        ], 'string');

        $properties = SchemaPropertyData::collect([
            [
                'name' => 'profile.connection_type',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('profile.connection_type', [], 'string'),
                'validator' => null,
            ],
            [
                'name' => 'profile.existing_account_id',
                'isOptional' => true,
                'validations' => $nestedValidation,
                'validator' => null,
            ],
        ]);

        $extracted = new ExtractedSchemaData(
            name: 'NestedRequiredIfNormalizationSchema',
            dependencies: [],
            properties: $properties,
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString("String(data.profile?.connection_type) === 'existing'", $schema);
        $this->assertStringContainsString('Nested account id is required.', $schema);
    }

    #[Test]
    public function it_generates_super_refine_for_confirmed_rules(): void
    {
        $properties = SchemaPropertyData::collect([
            [
                'name' => 'password',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('password', [
                    new ResolvedValidation('Confirmed', [], 'Passwords must match.'),
                ], 'string'),
                'validator' => null,
            ],
        ]);

        $extracted = new ExtractedSchemaData(
            name: 'ConfirmedSchema',
            dependencies: [],
            properties: $properties,
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('const confirmationValue = data.password_confirmation;', $schema);
        $this->assertStringContainsString("message: 'Passwords must match.'", $schema);
    }

    #[Test]
    public function it_generates_super_refine_for_same_and_different_rules(): void
    {
        $properties = SchemaPropertyData::collect([
            [
                'name' => 'email',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('email', [], 'string'),
                'validator' => null,
            ],
            [
                'name' => 'backup_email',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('backup_email', [
                    new ResolvedValidation('Same', ['email'], 'Emails must match.'),
                ], 'string'),
                'validator' => null,
            ],
            [
                'name' => 'username',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('username', [
                    new ResolvedValidation('Different', ['email'], 'Username must be unique from email.'),
                ], 'string'),
                'validator' => null,
            ],
        ]);

        $extracted = new ExtractedSchemaData(
            name: 'ComparisonSchema',
            dependencies: [],
            properties: $properties,
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('const otherValue = data.email;', $schema);
        $this->assertStringContainsString("message: 'Emails must match.'", $schema);
        $this->assertStringContainsString("message: 'Username must be unique from email.'", $schema);
    }

    #[Test]
    public function it_generates_super_refine_for_accepted_if_rules(): void
    {
        $properties = SchemaPropertyData::collect([
            [
                'name' => 'plan',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('plan', [], 'string'),
                'validator' => null,
            ],
            [
                'name' => 'terms',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('terms', [
                    new ResolvedValidation('AcceptedIf', ['plan', 'enterprise'], 'Terms must be accepted for enterprise.'),
                ], 'string'),
                'validator' => null,
            ],
        ]);

        $extracted = new ExtractedSchemaData(
            name: 'AcceptedIfSchema',
            dependencies: [],
            properties: $properties,
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('const normalized = val.toLowerCase();', $schema);
        $this->assertStringContainsString("message: 'Terms must be accepted for enterprise.'", $schema);
    }

    #[Test]
    public function it_generates_super_refine_for_date_comparisons(): void
    {
        $properties = SchemaPropertyData::collect([
            [
                'name' => 'start_date',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('start_date', [
                    new ResolvedValidation('Date', [], 'Start date must be valid.'),
                ], 'string'),
                'validator' => null,
            ],
            [
                'name' => 'end_date',
                'isOptional' => false,
                'validations' => ResolvedValidationSet::make('end_date', [
                    new ResolvedValidation('After', ['start_date'], 'End date must follow start date.'),
                ], 'string'),
                'validator' => null,
            ],
        ]);

        $extracted = new ExtractedSchemaData(
            name: 'DateSchema',
            dependencies: [],
            properties: $properties,
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('const referenceTimestamp = (() => { const raw = data.start_date;', $schema);
        $this->assertStringContainsString("message: 'End date must follow start date.'", $schema);
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
            ]),
            type: '',
            className: ''
        );

        $schema = $this->generator->generate($extracted);

        // Verify email field: should use proper email validation with messages
        $this->assertStringContainsString('email: z.email(', $schema);
        $this->assertStringContainsString('.max(255, ', $schema);
        $this->assertStringContainsString('The email field may not be greater than 255 characters.', $schema);

        // Verify integer field: number with error callback for integer validation
        $this->assertStringContainsString('age: z.number({ error:', $schema);
        $this->assertStringContainsString('The age field must be an integer.', $schema);
        $this->assertStringContainsString('.min(18, ', $schema);
        $this->assertStringContainsString('The age field must be at least 18.', $schema);
        $this->assertStringContainsString('.max(120, ', $schema);
        $this->assertStringContainsString('The age field may not be greater than 120.', $schema);

        // Verify URL field: should have url() validation with message and be optional
        $this->assertStringContainsString('website: z.', $schema);
        $this->assertStringContainsString("z.url({ error: 'The website field must be a valid URL.' })", $schema);
        $this->assertStringContainsString('.optional()', $schema);

        // Verify UUID field: should have uuid() validation with message
        $this->assertStringContainsString('uuid: z.string().uuid(\'The uuid field must be a valid UUID.\').trim()', $schema);

        // Verify overall structure is valid Zod v4
        $this->assertStringStartsWith('z.object({', $schema);
        $this->assertStringEndsWith('})', $schema);
    }
}
