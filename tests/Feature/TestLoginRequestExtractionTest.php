<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests\AuthTypeRequest;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests\DeploymentOptionsRequest;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests\TestLoginRequest;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class TestLoginRequestExtractionTest extends TestCase
{
    use InteractsWithExtractors;

    #[Test]
    public function it_extracts_validation_rules_from_test_login_request(): void
    {
        $reflection = new \ReflectionClass(TestLoginRequest::class);

        $result = $this->getRequestExtractor()->extract($reflection);

        // Verify schema name from attribute
        $this->assertEquals('LoginRequestSchema', $result->name);
        $this->assertEquals(TestLoginRequest::class, $result->className);
        $this->assertEquals('request', $result->type);

        // Verify all four properties are extracted (email, password, remember, login_as_user_type)
        $properties = $result->properties->toCollection();
        $this->assertCount(4, $properties, 'Should have email, password, remember, and login_as_user_type properties');

        // Check email property
        $emailProperty = $properties->firstWhere('name', 'email');
        $this->assertNotNull($emailProperty, 'Email property should exist');
        $this->assertFalse($emailProperty->isOptional, 'Email should be required');
        $this->assertEquals('email', $emailProperty->validations->inferredType);

        // Verify email validations
        $this->assertTrue($emailProperty->validations->hasValidation('Required'));
        $this->assertTrue($emailProperty->validations->hasValidation('Email'));
        $this->assertTrue($emailProperty->validations->hasValidation('Max'));

        // Check for custom messages on email
        $emailRequired = $emailProperty->validations->getValidation('Required');
        $this->assertNotNull($emailRequired);
        $this->assertEquals('Email is required', $emailRequired->message);

        $emailValidation = $emailProperty->validations->getValidation('Email');
        $this->assertNotNull($emailValidation);
        $this->assertEquals('Please provide a valid email', $emailValidation->message);

        // Check password property
        $passwordProperty = $properties->firstWhere('name', 'password');
        $this->assertNotNull($passwordProperty, 'Password property should exist');
        $this->assertFalse($passwordProperty->isOptional, 'Password should be required');
        $this->assertEquals('string', $passwordProperty->validations->inferredType);

        // Verify password validations
        $this->assertTrue($passwordProperty->validations->hasValidation('Required'));
        $this->assertTrue($passwordProperty->validations->hasValidation('Min'));
        $this->assertTrue($passwordProperty->validations->hasValidation('Max'));

        // Check for custom messages on password
        $passwordRequired = $passwordProperty->validations->getValidation('Required');
        $this->assertNotNull($passwordRequired);
        $this->assertEquals('Password is required', $passwordRequired->message);

        $passwordMin = $passwordProperty->validations->getValidation('Min');
        $this->assertNotNull($passwordMin);
        $this->assertEquals('Password must be at least 8 characters', $passwordMin->message);
        $this->assertEquals([8], $passwordMin->parameters);

        $passwordMax = $passwordProperty->validations->getValidation('Max');
        $this->assertNotNull($passwordMax);
        $this->assertEquals([128], $passwordMax->parameters);

        // Check remember property
        $rememberProperty = $properties->firstWhere('name', 'remember');
        $this->assertNotNull($rememberProperty, 'Remember property should exist');
        $this->assertTrue($rememberProperty->isOptional, 'Remember should be optional');
        $this->assertEquals('boolean', $rememberProperty->validations->inferredType);

        // Verify remember validations
        $this->assertTrue($rememberProperty->validations->hasValidation('Boolean'));

        // Check login_as_user_type property (Enum rule converted to In)
        $loginAsProperty = $properties->firstWhere('name', 'login_as_user_type');
        $this->assertNotNull($loginAsProperty, 'login_as_user_type property should exist');
        $this->assertTrue($loginAsProperty->isOptional, 'login_as_user_type should be optional');
        // Enum rule is converted to 'In' rule with the enum values
        $this->assertTrue($loginAsProperty->validations->hasValidation('In'), 'Should have In validation from Enum rule');

        // Check that it has the correct enum value
        $inValidation = $loginAsProperty->validations->getValidation('In');
        $this->assertNotNull($inValidation);
        // UserType::super_admin has the value 'Super Admin'
        $this->assertContains('Super Admin', $inValidation->parameters, 'Should contain Super Admin value from UserType enum');
    }

    #[Test]
    public function it_correctly_identifies_optional_fields(): void
    {
        $reflection = new \ReflectionClass(TestLoginRequest::class);

        $result = $this->getRequestExtractor()->extract($reflection);

        $properties = $result->properties->toCollection();

        // Email and password are required
        $emailProperty = $properties->firstWhere('name', 'email');
        $this->assertFalse($emailProperty->isOptional);

        $passwordProperty = $properties->firstWhere('name', 'password');
        $this->assertFalse($passwordProperty->isOptional);

        // Remember is optional (no required rule)
        $rememberProperty = $properties->firstWhere('name', 'remember');
        $this->assertTrue($rememberProperty->isOptional);
    }

    #[Test]
    public function it_preserves_validation_parameters(): void
    {
        $reflection = new \ReflectionClass(TestLoginRequest::class);

        $result = $this->getRequestExtractor()->extract($reflection);

        $properties = $result->properties->toCollection();

        // Check email max length
        $emailProperty = $properties->firstWhere('name', 'email');
        $maxValidation = $emailProperty->validations->getValidation('Max');
        $this->assertNotNull($maxValidation, 'Email Max validation should exist');
        $this->assertEquals([255], $maxValidation->parameters);

        // Check password min and max lengths
        $passwordProperty = $properties->firstWhere('name', 'password');

        $minValidation = $passwordProperty->validations->getValidation('Min');
        $this->assertNotNull($minValidation, 'Password Min validation should exist');
        $this->assertEquals([8], $minValidation->parameters);

        $maxValidation = $passwordProperty->validations->getValidation('Max');
        $this->assertNotNull($maxValidation, 'Password Max validation should exist');
        $this->assertEquals([128], $maxValidation->parameters);
    }

    #[Test]
    public function it_handles_nested_objects_defined_with_dot_notation(): void
    {
        $reflection = new \ReflectionClass(DeploymentOptionsRequest::class);

        $result = $this->getRequestExtractor()->extract($reflection);
        $properties = $result->properties->toCollection();

        $optionsProperty = $properties->firstWhere('name', 'options');
        $this->assertNotNull($optionsProperty, 'Options property should exist');
        $this->assertSame('object', $optionsProperty->validations->inferredType, 'Options should resolve to an object schema');

        $generator = $this->app->make(ValidationSchemaGenerator::class);
        $schema = $generator->generate($result);

        $this->assertStringContainsString('options: z.object({', $schema);
        $this->assertStringNotContainsString('options: z.array(', $schema);
        $this->assertStringContainsString('gitignore: z.string()', $schema);
        $this->assertStringContainsString("max(255, 'The options.gitignore field must not be greater than 255 characters.')", $schema);
        $this->assertStringContainsString('workflow: z.string()', $schema);
        $this->assertStringContainsString('plugin_url: z.preprocess((val) => (val === \'\' ? undefined : val), z.url(', $schema);
        $this->assertStringContainsString('repository: z.object({', $schema);
        $this->assertStringContainsString('sftp_path: z.string()', $schema);
    }

    #[Test]
    public function it_generates_super_refine_for_required_if_rules_in_form_requests(): void
    {
        $reflection = new \ReflectionClass(AuthTypeRequest::class);

        $result = $this->getRequestExtractor()->extract($reflection);
        $schema = $this->app->make(ValidationSchemaGenerator::class)->generate($result);

        $this->assertStringContainsString('.superRefine((data, ctx) => {', $schema);
        $this->assertStringContainsString("String(data.auth_type) === 'password'", $schema);
        $this->assertStringContainsString("ctx.addIssue({", $schema);
        $this->assertStringContainsString("'The password field is required when auth type is password.'", $schema);
        $this->assertStringContainsString("'The base path field must not be greater than 255 characters.'", $schema);
        $this->assertStringNotContainsString("base_path field", $schema);
    }
}
