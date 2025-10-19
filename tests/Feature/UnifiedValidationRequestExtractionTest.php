<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests\UnifiedValidationRequest;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class UnifiedValidationRequestExtractionTest extends TestCase
{
    use InteractsWithExtractors;

    private ExtractedSchemaData $extracted;

    protected function setUp(): void
    {
        parent::setUp();
        $reflection = new ReflectionClass(UnifiedValidationRequest::class);
        $this->extracted = $this->getRequestExtractor()->extract($reflection);
    }

    #[Test]
    public function it_extracts_schema_metadata(): void
    {
        $this->assertEquals('UnifiedValidationRequestSchema', $this->extracted->name);
        $this->assertEquals(UnifiedValidationRequest::class, $this->extracted->className);
        $this->assertEquals('request', $this->extracted->type);

        $properties = $this->extracted->properties->toCollection();
        $this->assertCount(5, $properties);
        $this->assertNotNull($properties->firstWhere('name', 'auth_type'));
        $this->assertNotNull($properties->firstWhere('name', 'credentials'));
        $this->assertNotNull($properties->firstWhere('name', 'profile'));
        $this->assertNotNull($properties->firstWhere('name', 'metadata'));
        $this->assertNotNull($properties->firstWhere('name', 'attachments'));
    }

    #[Test]
    public function it_groups_dot_notation_fields_into_nested_objects(): void
    {
        $properties = $this->extracted->properties->toCollection();

        $credentials = $properties->firstWhere('name', 'credentials');
        $this->assertNotNull($credentials);
        $this->assertSame('object', $credentials->validations->inferredType);
        $this->assertArrayHasKey('email', $credentials->validations->getObjectProperties());
        $this->assertArrayHasKey('password', $credentials->validations->getObjectProperties());
        $this->assertArrayHasKey('otp', $credentials->validations->getObjectProperties());

        $profile = $properties->firstWhere('name', 'profile');
        $this->assertNotNull($profile);
        $objects = $profile->validations->getObjectProperties();
        $this->assertArrayHasKey('preferences', $objects);
        $this->assertArrayHasKey('contacts', $objects);
        $this->assertArrayHasKey('address', $objects);
    }

    #[Test]
    public function it_marks_optional_fields_on_extracted_properties(): void
    {
        $properties = $this->extracted->properties->toCollection();

        $authType = $properties->firstWhere('name', 'auth_type');
        $this->assertNotNull($authType);
        $this->assertFalse($authType->isOptional);

        $attachments = $properties->firstWhere('name', 'attachments');
        $this->assertNotNull($attachments);
        $this->assertTrue($attachments->isOptional);
    }

    #[Test]
    public function it_returns_expected_validation_sets_for_core_fields(): void
    {
        $properties = $this->extracted->properties->toCollection();

        $authType = $properties->firstWhere('name', 'auth_type');
        $this->assertTrue($authType->validations->hasValidation('Required'));
        $this->assertTrue($authType->validations->hasValidation('In'));

        $metadata = $properties->firstWhere('name', 'metadata');
        $this->assertNotNull($metadata);
        $loginCount = $this->findNestedValidationSet($metadata->validations, 'login_count');
        $this->assertNotNull($loginCount);
        $this->assertTrue($loginCount->hasValidation('Integer'));
        $this->assertTrue($loginCount->hasValidation('Min'));
        $this->assertTrue($loginCount->hasValidation('Max'));
    }

    #[Test]
    public function it_generates_super_refine_logic_for_required_if_rules(): void
    {
        $properties = $this->extracted->properties->toCollection();
        $credentials = $properties->firstWhere('name', 'credentials');
        $this->assertNotNull($credentials);

        $passwordValidations = $this->findNestedValidationSet($credentials->validations, 'password');
        $this->assertNotNull($passwordValidations);
        $passwordRequiredIf = $passwordValidations->getValidation('RequiredIf');
        $this->assertNotNull($passwordRequiredIf);
        $this->assertEquals('The credentials.password field is required when auth type is password.', $passwordRequiredIf->message);

        $otpValidations = $this->findNestedValidationSet($credentials->validations, 'otp');
        $this->assertNotNull($otpValidations);
        $otpRequiredIf = $otpValidations->getValidation('RequiredIf');
        $this->assertNotNull($otpRequiredIf);
        $this->assertEquals('The credentials.otp field is required when auth type is otp.', $otpRequiredIf->message);
    }

    #[DataProvider('nestedFieldExpectations')]
    #[Test]
    public function it_preserves_messages_and_parameters_for_nested_fields(string $path, string $expectedValidation, mixed $expectedParameters, ?string $expectedMessage): void
    {
        $profile = $this->extracted->properties->toCollection()->firstWhere('name', 'profile');
        $this->assertNotNull($profile);

        $nested = $this->findNestedValidationSet($profile->validations, $path);
        $this->assertNotNull($nested, sprintf('Expected nested path [%s] to resolve to a validation set', $path));

        $validation = $nested->getValidation($expectedValidation);
        $this->assertNotNull($validation);

        if ($expectedParameters !== null) {
            $this->assertEquals($expectedParameters, $validation->parameters);
        }

        if ($expectedMessage !== null) {
            $this->assertEquals($expectedMessage, $validation->message);
        }
    }

    /**
     * @return iterable<string, array{path: string, validation: string, parameters: mixed, message: ?string}>
     */
    public static function nestedFieldExpectations(): iterable
    {
        yield 'accepted terms refinement' => ['preferences.accepted_terms', 'Accepted', null, 'You must accept the terms.'];

        yield 'postal code regex' => ['address.postal_code', 'Regex', ['/^[0-9]{5}$/'], 'Postal code must be exactly 5 digits.'];

        yield 'tags wildcard rules' => ['preferences.tags.*', 'In', ['news', 'updates', 'offers'], null];
    }

    private function findNestedValidationSet(ResolvedValidationSet $set, string $path): ?ResolvedValidationSet
    {
        $segments = explode('.', $path);
        $current = $set;

        foreach ($segments as $segment) {
            if ($segment === '*') {
                $current = $current->getNestedValidations();

                continue;
            }

            $properties = $current->getObjectProperties();
            if (! array_key_exists($segment, $properties)) {
                return null;
            }

            $current = $properties[$segment];
        }

        return $current;
    }
}
