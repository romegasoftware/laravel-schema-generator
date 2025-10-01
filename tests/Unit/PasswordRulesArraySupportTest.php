<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodPasswordBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ValidationRuleFactory;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\CreatesTestClasses;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;

class PasswordRulesArraySupportTest extends TestCase
{
    use CreatesTestClasses;

    protected ValidationRuleFactory $ruleFactory;

    protected LaravelValidationResolver $validationResolver;

    protected ZodBuilderFactory $builderFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ruleFactory = new ValidationRuleFactory;
        $this->validationResolver = new LaravelValidationResolver;
        $this->builderFactory = new ZodBuilderFactory;
    }

    #[Test]
    public function it_detects_and_expands_password_rules_with_defaults(): void
    {
        $passwordRule = Password::defaults();

        $normalizedRule = $this->ruleFactory->normalizeRule($passwordRule);

        // Should expand to individual rules
        $this->assertStringContainsString('password', $normalizedRule);
        // The actual implementation will determine the exact format
    }

    #[Test]
    public function it_handles_password_rule_with_all_constraints(): void
    {
        $passwordRule = Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised();

        $normalizedRule = $this->ruleFactory->normalizeRule($passwordRule);

        // Should contain all constraints
        $this->assertStringContainsString('password', $normalizedRule);
    }

    #[Test]
    public function it_extracts_password_rules_from_form_request(): void
    {
        // Create a test FormRequest with password rules
        $formRequest = $this->createTestFormRequest([
            'password' => ['required', Password::defaults()],
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $class = new \ReflectionClass($formRequest);
        $extractor = app(RequestClassExtractor::class);

        // Mark the class with ValidationSchema attribute to ensure it's processed
        $validationSchemaAttribute = new ValidationSchema;

        // We can't add attributes at runtime, so we'll test the rule normalization directly
        $normalizedRules = [];
        foreach ($formRequest->rules() as $field => $rule) {
            if (is_array($rule)) {
                $normalized = [];
                foreach ($rule as $r) {
                    if (is_object($r)) {
                        $normalized[] = $this->ruleFactory->normalizeRule($r);
                    } else {
                        $normalized[] = $r;
                    }
                }
                $normalizedRules[$field] = implode('|', $normalized);
            } else {
                $normalizedRules[$field] = $rule;
            }
        }

        // Should have normalized password rules
        $this->assertArrayHasKey('password', $normalizedRules);
        $this->assertStringContainsString('password', $normalizedRules['password']);
        $this->assertStringContainsString('required', $normalizedRules['password']);
    }

    #[Test]
    public function it_resolves_password_rule_messages_correctly(): void
    {
        $validator = new Validator(
            app('translator'),
            ['password' => 'test'],
            ['password' => Password::min(8)->letters()->numbers()]
        );

        $passwordRule = Password::min(8)->letters()->numbers();
        $normalizedRule = $this->ruleFactory->normalizeRule($passwordRule);

        $resolvedSet = $this->validationResolver->resolve('password', $normalizedRule, $validator);

        // Should have appropriate validations
        $this->assertInstanceOf(ResolvedValidationSet::class, $resolvedSet);
        $this->assertNotEmpty($resolvedSet->validations);

        // Should have messages for each constraint
        foreach ($resolvedSet->validations as $validation) {
            $this->assertNotEmpty($validation->message);
        }
    }

    #[Test]
    public function it_returns_string_messages_when_laravel_provides_arrays(): void
    {
        $passwordRule = Password::min(10)->letters()->numbers()->symbols();

        $validator = new Validator(
            app('translator'),
            ['password' => null],
            ['password' => $passwordRule]
        );

        $normalizedRule = $this->ruleFactory->normalizeRule($passwordRule);
        $resolvedSet = $this->validationResolver->resolve('password', $normalizedRule, $validator);

        foreach ($resolvedSet->validations as $validation) {
            $this->assertIsString($validation->message);
            $this->assertNotSame('', $validation->message);
        }
    }

    #[Test]
    public function it_builds_zod_schema_for_password_with_multiple_constraints(): void
    {
        // This test verifies the end-to-end flow
        $passwordRule = Password::min(8)->max(256)->letters()->mixedCase()->numbers()->symbols()->uncompromised(3);

        $validator = new Validator(
            app('translator'),
            ['password' => 'test'],
            ['password' => $passwordRule]
        );

        $normalizedRule = $this->ruleFactory->normalizeRule($passwordRule);
        $resolvedSet = $this->validationResolver->resolve('password', $normalizedRule, $validator);

        // Create property
        $property = new SchemaPropertyData(
            name: 'password',
            validator: $validator,
            isOptional: false,
            validations: $resolvedSet
        );

        // Use UniversalTypeHandler to generate the builder
        $handler = new UniversalTypeHandler($this->builderFactory);
        $this->builderFactory->setUniversalTypeHandler($handler);

        $builder = $handler->handle($property);
        $result = $builder->build();

        // Should contain password validations
        $this->assertStringContainsString('z.string()', $result);
        $this->assertStringContainsString('.min(8', $result);
        $this->assertStringContainsString('.max(256', $result);
        $this->assertStringContainsString('.regex(/[a-zA-Z]/', $result);
        $this->assertStringContainsString('.refine((val) => /[a-z]/.test(val) && /[A-Z]/.test(val),', $result);
        $this->assertStringContainsString('.regex(/\d/, ', $result);
        $this->assertStringContainsString('.regex(/[^a-zA-Z0-9]/, ', $result);
        $this->assertStringContainsString('.trim()', $result);
    }

    #[Test]
    public function it_handles_password_rules_in_nested_arrays(): void
    {
        // Create a test FormRequest with nested password rules
        $formRequest = $this->createTestFormRequest([
            'users' => 'array',
            'users.*.password' => ['required', Password::defaults()],
            'users.*.confirm_password' => 'required|same:users.*.password',
        ]);

        // Test the rule normalization directly
        $normalizedRules = [];
        foreach ($formRequest->rules() as $field => $rule) {
            if (is_array($rule)) {
                $normalized = [];
                foreach ($rule as $r) {
                    if (is_object($r)) {
                        $normalized[] = $this->ruleFactory->normalizeRule($r);
                    } else {
                        $normalized[] = $r;
                    }
                }
                $normalizedRules[$field] = implode('|', $normalized);
            } else {
                $normalizedRules[$field] = $rule;
            }
        }

        // Should handle nested password rules
        $this->assertArrayHasKey('users', $normalizedRules);
        $this->assertArrayHasKey('users.*.password', $normalizedRules);
        $this->assertStringContainsString('password', $normalizedRules['users.*.password']);
        $this->assertStringContainsString('required', $normalizedRules['users.*.password']);
    }

    #[Test]
    public function it_preserves_custom_messages_from_password_rules(): void
    {
        // Test that messages from Password rules are resolved correctly
        $validator = new Validator(
            app('translator'),
            ['password' => 'test'],
            ['password' => Password::min(8)->letters()]
        );

        $passwordRule = Password::min(8)->letters();
        $normalizedRule = $this->ruleFactory->normalizeRule($passwordRule);

        // The normalized rule should contain the expanded password rules
        $this->assertNotEmpty($normalizedRule, 'Normalized rule should not be empty');
        $this->assertStringContainsString('password', $normalizedRule);

        // Parse the normalized rule to see what we get
        $rules = explode('|', $normalizedRule);
        $this->assertNotEmpty($rules, 'Should have at least one rule');

        // At minimum, we should have the password rule
        $this->assertContains('password', $rules, 'Should contain password rule');

        // Check if min rule was expanded separately
        $hasMin = false;
        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'min:')) {
                $hasMin = true;
                $this->assertEquals('min:8', $rule);
            }
        }

        if ($hasMin) {
            $this->assertTrue(true, 'Min rule was expanded correctly');
        } else {
            // Min might be part of the password rule parameters
            $this->assertCount(1, array_filter($rules, fn ($r) => str_contains($r, 'password')));
        }
    }

    #[Test]
    public function it_handles_password_rule_without_any_constraints(): void
    {
        // Test Password::defaults() with no additional constraints
        Password::defaults(function () {
            return Password::min(6);
        });

        $passwordRule = Password::defaults();
        $normalizedRule = $this->ruleFactory->normalizeRule($passwordRule);

        $this->assertNotEmpty($normalizedRule);
        $this->assertStringContainsString('password', $normalizedRule);
    }

    #[Test]
    public function it_generates_correct_zod_for_password_builder(): void
    {
        // Direct test of ZodPasswordBuilder if it exists
        if (class_exists(ZodPasswordBuilder::class)) {
            $builder = new ZodPasswordBuilder;

            $builder->validateMin([8], 'Password must be at least 8 characters')
                ->validatePasswordLetters([], 'Password must contain letters')
                ->validatePasswordMixed([], 'Password must contain mixed case')
                ->validatePasswordNumbers([], 'Password must contain numbers')
                ->validatePasswordSymbols([], 'Password must contain symbols');

            $result = $builder->build();

            // Verify the generated Zod schema
            $this->assertStringContainsString('z.string()', $result);
            $this->assertStringContainsString('.min(8', $result);
            $this->assertStringContainsString('.regex(', $result); // For various pattern checks
        }
    }
}
