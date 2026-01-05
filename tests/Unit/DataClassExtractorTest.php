<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\RecursiveCategoryData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class DataClassExtractorTest extends TestCase
{
    use InteractsWithExtractors;

    #[Test]
    public function it_extracts_custom_messages_from_data_class(): void
    {
        $reflection = new ReflectionClass(TestPostalCodeData::class);

        $result = $this->getDataExtractor()->extract($reflection);

        $this->assertEquals('TestPostalCodeDataSchema', $result->name);
        $this->assertCount(1, $result->properties);

        $property = $result->properties->toCollection()->first();
        $this->assertEquals('code', $property->name);
        // Type is now inferred from validations
        $this->assertEquals('string', $property->validations->inferredType);
        $this->assertFalse($property->isOptional);

        $validations = $property->validations;
        $this->assertTrue($validations->hasValidation('String'));
        $this->assertEquals('/^\d{5}(-\d{4})?$/', $validations->getValidationParameter('Regex'));
        // Check that custom message exists
        $this->assertNotNull($validations->getMessage('Regex'));
        $this->assertEquals('Postal Code must match ##### or #####-####', $validations->getMessage('Regex'));
    }

    #[Test]
    public function it_inherits_custom_messages_from_validation_inheritance(): void
    {
        $reflection = new ReflectionClass(TestInheritingData::class);

        $result = $this->getDataExtractor()->extract($reflection);

        $this->assertEquals('TestInheritingDataSchema', $result->name);
        $this->assertCount(1, $result->properties);

        $property = $result->properties->toCollection()->first();
        $this->assertEquals('postal_code', $property->name);
        $this->assertEquals('string', $property->validations->inferredType);
        $this->assertFalse($property->isOptional);

        $validations = $property->validations;
        $this->assertTrue($validations->hasValidation('String'));
        $this->assertEquals('/^\d{5}(-\d{4})?$/', $validations->getValidationParameter('Regex'));
        // Check that custom message exists
        $this->assertNotNull($validations->getMessage('Regex'));
        $this->assertEquals('Postal Code must match ##### or #####-####', $validations->getMessage('Regex'));
    }

    #[Test]
    public function it_does_not_override_inherited_custom_messages_with_empty_messages(): void
    {
        // This test ensures that when a class inherits validation but has no messages() method,
        // the inherited custom messages are preserved and not overwritten with empty array
        $reflection = new ReflectionClass(TestInheritingDataWithoutMessages::class);

        $result = $this->getDataExtractor()->extract($reflection);

        $property = $result->properties->toCollection()->first();
        $validations = $property->validations;

        // The inherited custom message should still be there
        // Check that custom message exists
        $this->assertNotNull($validations->getMessage('Regex'));
        $this->assertEquals('Postal Code must match ##### or #####-####', $validations->getMessage('Regex'));
    }

    #[Test]
    public function it_handles_recursive_data_classes_without_infinite_recursion(): void
    {
        $reflection = new ReflectionClass(RecursiveCategoryData::class);

        $result = $this->getDataExtractor()->extract($reflection);

        $properties = $result->properties->toCollection()->keyBy('name');

        $this->assertTrue($properties->has('children'));

        $children = $properties->get('children');
        $this->assertSame('array', $children->validations->inferredType);
        $this->assertTrue($children->validations->hasNestedValidations());

        $nested = $children->validations->getNestedValidations();
        $this->assertNotNull($nested);
        $this->assertTrue($nested->hasObjectProperties());
        $this->assertArrayHasKey('name', $nested->getObjectProperties());
    }

}

// Test classes for the extractor

#[ValidationSchema]
class TestPostalCodeData extends Data
{
    public function __construct(
        #[StringType, Regex('/^\d{5}(-\d{4})?$/')]
        public string $code
    ) {}

    public static function messages(...$args): array
    {
        return [
            'code.regex' => 'Postal Code must match ##### or #####-####',
        ];
    }
}

#[ValidationSchema]
class TestInheritingData extends Data
{
    public function __construct(
        #[InheritValidationFrom(TestPostalCodeData::class, 'code')]
        public string $postal_code
    ) {}

    // This class has no messages() method, so messages should be inherited
}

#[ValidationSchema]
class TestInheritingDataWithoutMessages extends Data
{
    public function __construct(
        #[InheritValidationFrom(TestPostalCodeData::class, 'code')]
        public string $postal_code
    ) {}

    // Explicitly testing the case where there's no messages() method
    // but we still want inherited messages to be preserved
}
