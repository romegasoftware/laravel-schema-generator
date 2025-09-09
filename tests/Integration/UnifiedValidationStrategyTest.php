<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Integration;

use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;

class UnifiedValidationStrategyTest extends TestCase
{
    private LaravelValidationResolver $resolver;

    private UniversalTypeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new LaravelValidationResolver;
        $this->handler = new UniversalTypeHandler;
    }

    /** @test */
    public function it_demonstrates_complete_unified_validation_workflow()
    {
        // Step 1: Resolve Laravel validation rules
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['email' => 'test@example.com'],
            ['email' => 'required|email|max:255'],
            ['required' => 'Email is required', 'email' => 'Please provide a valid email']
        );

        $validationSet = $this->resolver->resolve('email', 'required|email|max:255', $validator);

        // Step 2: Create schema property
        $property = new SchemaPropertyData(
            name: 'email',
            validator: $validator,
            isOptional: ! $validationSet->isFieldRequired(),
            validations: $validationSet
        );

        // Step 3: Handle with universal type handler
        $builder = $this->handler->handle($property);

        // Step 4: Generate Zod schema
        $ValidationSchema = $builder->build();

        // Verify the complete workflow
        $this->assertEquals('email', $validationSet->inferredType);
        $this->assertTrue($validationSet->isFieldRequired());
        $this->assertFalse($property->isOptional);

        // Verify Zod output contains expected validations
        $this->assertStringContainsString('z.email(', $ValidationSchema);
        $this->assertStringContainsString('.max(255', $ValidationSchema);
    }

    /** @test */
    public function it_handles_complex_string_validation()
    {
        $rules = 'required|string|min:8|max:100|regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).+$/';
        $customMessages = [
            'required' => 'Password is required',
            'min' => 'Password must be at least 8 characters',
            'regex' => 'Password must contain uppercase, lowercase, and number',
        ];

        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['password' => 'TestPass123'],
            ['password' => $rules],
            $customMessages
        );

        $validationSet = $this->resolver->resolve('password', $rules, $validator);
        $property = new SchemaPropertyData('password', $validator, false, $validationSet);
        $builder = $this->handler->handle($property);
        $ValidationSchema = $builder->build();

        $this->assertEquals('string', $validationSet->inferredType);
        $this->assertTrue($validationSet->hasValidation('Required'));
        $this->assertTrue($validationSet->hasValidation('Min'));
        $this->assertTrue($validationSet->hasValidation('Max'));
        $this->assertTrue($validationSet->hasValidation('Regex'));

        $this->assertStringContainsString('z.string()', $ValidationSchema);
        $this->assertStringContainsString('.min(8', $ValidationSchema);
        $this->assertStringContainsString('.max(100', $ValidationSchema);
        $this->assertStringContainsString('.regex(', $ValidationSchema);
    }

    /** @test */
    public function it_handles_numeric_validation()
    {
        $rules = 'required|integer|min:0|max:120';
        $customMessages = ['required' => 'Age is required', 'min' => 'Age must be positive'];

        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['age' => 25],
            ['age' => $rules],
            $customMessages
        );

        $validationSet = $this->resolver->resolve('age', $rules, $validator);

        $property = new SchemaPropertyData('age', $validator, false, $validationSet);
        $builder = $this->handler->handle($property);
        $ValidationSchema = $builder->build();

        $this->assertEquals('number', $validationSet->inferredType);
        $this->assertStringContainsString('z.number()', $ValidationSchema);
    }

    /** @test */
    public function it_handles_enum_validation()
    {
        $rules = 'required|in:pending,approved,rejected';
        $customMessages = ['in' => 'Status must be pending, approved, or rejected'];

        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['status' => 'pending'],
            ['status' => $rules],
            $customMessages
        );

        $validationSet = $this->resolver->resolve('status', $rules, $validator);

        $property = new SchemaPropertyData('status', $validator, false, $validationSet);
        $builder = $this->handler->handle($property);
        $ValidationSchema = $builder->build();

        $this->assertEquals('enum:pending,approved,rejected', $validationSet->inferredType);
        $this->assertStringContainsString('z.enum([', $ValidationSchema);
        $this->assertStringContainsString('pending', $ValidationSchema);
        $this->assertStringContainsString('approved', $ValidationSchema);
        $this->assertStringContainsString('rejected', $ValidationSchema);
    }

    /** @test */
    public function it_handles_boolean_validation()
    {
        $rules = 'boolean';

        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['is_active' => true],
            ['is_active' => $rules],
            []
        );

        $validationSet = $this->resolver->resolve('is_active', $rules, $validator);
        $property = new SchemaPropertyData('is_active', $validator, true, $validationSet);
        $builder = $this->handler->handle($property);
        $ValidationSchema = $builder->build();

        $this->assertEquals('boolean', $validationSet->inferredType);
        $this->assertStringContainsString('z.boolean()', $ValidationSchema);
        $this->assertStringContainsString('.optional()', $ValidationSchema);
    }

    /** @test */
    public function it_handles_array_validation()
    {
        $rules = 'required|array|min:1|max:10';
        $customMessages = ['required' => 'At least one tag is required'];

        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['tags' => ['tag1', 'tag2']],
            ['tags' => $rules],
            $customMessages
        );

        $validationSet = $this->resolver->resolve('tags', $rules, $validator);

        $property = new SchemaPropertyData('tags', $validator, false, $validationSet);
        $builder = $this->handler->handle($property);
        $ValidationSchema = $builder->build();

        $this->assertEquals('array', $validationSet->inferredType);
        $this->assertStringContainsString('z.array(', $ValidationSchema);
    }

    /** @test */
    public function it_handles_nullable_validation()
    {
        $rules = 'nullable|string|max:500';

        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['description' => 'Sample description'],
            ['description' => $rules],
            []
        );

        $validationSet = $this->resolver->resolve('description', $rules, $validator);

        $property = new SchemaPropertyData('description', $validator, true, $validationSet);
        $builder = $this->handler->handle($property);
        $ValidationSchema = $builder->build();

        $this->assertTrue($validationSet->isFieldNullable());
        $this->assertStringContainsString('z.string()', $ValidationSchema);
        $this->assertStringContainsString('.nullable()', $ValidationSchema);
        $this->assertStringContainsString('.optional()', $ValidationSchema);
    }

    /** @test */
    public function it_handles_url_validation()
    {
        $rules = 'url';
        $customMessages = ['url' => 'Must be a valid website URL'];

        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['website' => 'https://example.com'],
            ['website' => $rules],
            $customMessages
        );

        $validationSet = $this->resolver->resolve('website', $rules, $validator);
        $property = new SchemaPropertyData('website', $validator, true, $validationSet);
        $builder = $this->handler->handle($property);
        $ValidationSchema = $builder->build();

        $this->assertEquals('url', $validationSet->inferredType);
        $this->assertStringContainsString('.url(', $ValidationSchema);
        $this->assertStringContainsString('.optional()', $ValidationSchema);
    }

    /** @test */
    public function it_handles_uuid_validation()
    {
        $rules = 'required|uuid';

        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['id' => '550e8400-e29b-41d4-a716-446655440000'],
            ['id' => $rules],
            []
        );

        $validationSet = $this->resolver->resolve('id', $rules, $validator);
        $property = new SchemaPropertyData('id', $validator, false, $validationSet);
        $builder = $this->handler->handle($property);
        $ValidationSchema = $builder->build();

        $this->assertEquals('uuid', $validationSet->inferredType);
        $this->assertStringContainsString('.uuid(', $ValidationSchema);
    }

    /** @test */
    public function it_demonstrates_backward_compatibility()
    {
        $rules = 'required|string|min:2|max:50';
        $customMessages = ['required' => 'Name is required'];

        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $validator = new \Illuminate\Validation\Validator(
            $translator,
            ['name' => 'John Doe'],
            ['name' => $rules],
            $customMessages
        );

        $validationSet = $this->resolver->resolve('name', $rules, $validator);

        $property = new SchemaPropertyData('name', $validator, false, $validationSet);
        $builder = $this->handler->handle($property);
        $ValidationSchema = $builder->build();

        // Verify backward compatibility by checking the validation set structure
        $this->assertEquals('string', $validationSet->inferredType);
        $this->assertTrue($validationSet->isFieldRequired());
        $this->assertTrue($validationSet->hasValidation('Required'));
        $this->assertTrue($validationSet->hasValidation('String'));
        $this->assertTrue($validationSet->hasValidation('Min'));
        $this->assertTrue($validationSet->hasValidation('Max'));
    }
}
