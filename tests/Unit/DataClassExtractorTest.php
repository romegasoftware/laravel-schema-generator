<?php

namespace RomegaSoftware\LaravelZodGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelZodGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelZodGenerator\Attributes\ZodSchema;
use RomegaSoftware\LaravelZodGenerator\Extractors\DataClassExtractor;
use RomegaSoftware\LaravelZodGenerator\Tests\TestCase;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

class DataClassExtractorTest extends TestCase
{
    protected DataClassExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new DataClassExtractor;
    }

    #[Test]
    public function it_extracts_custom_messages_from_data_class(): void
    {
        $reflection = new ReflectionClass(TestPostalCodeData::class);

        $result = $this->extractor->extract($reflection);

        $this->assertEquals('TestPostalCodeSchema', $result->name);
        $this->assertCount(1, $result->properties);

        $property = $result->properties->first();
        $this->assertEquals('code', $property->name);
        $this->assertEquals('string', $property->type);
        $this->assertFalse($property->isOptional);

        $validations = $property->validations;
        $this->assertTrue($validations->hasValidation('string'));
        $this->assertEquals('/^\d{5}(-\d{4})?$/', $validations->getValidation('regex'));
        $this->assertNotEmpty($validations->getCustomMessages());
        $this->assertEquals('Postal Code must match ##### or #####-####', $validations->getCustomMessage('regex'));
    }

    #[Test]
    public function it_inherits_custom_messages_from_validation_inheritance(): void
    {
        $reflection = new ReflectionClass(TestInheritingData::class);

        $result = $this->extractor->extract($reflection);

        $this->assertEquals('TestInheritingSchema', $result->name);
        $this->assertCount(1, $result->properties);

        $property = $result->properties->first();
        $this->assertEquals('postal_code', $property->name);
        $this->assertEquals('string', $property->type);
        $this->assertFalse($property->isOptional);

        $validations = $property->validations;
        $this->assertTrue($validations->hasValidation('string'));
        $this->assertEquals('/^\d{5}(-\d{4})?$/', $validations->getValidation('regex'));
        $this->assertNotEmpty($validations->getCustomMessages());
        $this->assertEquals('Postal Code must match ##### or #####-####', $validations->getCustomMessage('regex'));
    }

    #[Test]
    public function it_does_not_override_inherited_custom_messages_with_empty_messages(): void
    {
        // This test ensures that when a class inherits validation but has no messages() method,
        // the inherited custom messages are preserved and not overwritten with empty array
        $reflection = new ReflectionClass(TestInheritingDataWithoutMessages::class);

        $result = $this->extractor->extract($reflection);

        $property = $result->properties->first();
        $validations = $property->validations;

        // The inherited custom message should still be there
        $this->assertNotEmpty($validations->customMessages);
        $this->assertEquals('Postal Code must match ##### or #####-####', $validations->customMessages['regex']);
    }
}

// Test classes for the extractor

#[TypeScript]
#[ZodSchema]
class TestPostalCodeData extends Data
{
    public function __construct(
        #[StringType, Regex('/^\d{5}(-\d{4})?$/')]
        public string $code
    ) {}

    public static function messages(): array
    {
        return [
            'code.regex' => 'Postal Code must match ##### or #####-####',
        ];
    }
}

#[TypeScript]
#[ZodSchema]
class TestInheritingData extends Data
{
    public function __construct(
        #[InheritValidationFrom(TestPostalCodeData::class, 'code')]
        public string $postal_code
    ) {}

    // This class has no messages() method, so messages should be inherited
}

#[TypeScript]
#[ZodSchema]
class TestInheritingDataWithoutMessages extends Data
{
    public function __construct(
        #[InheritValidationFrom(TestPostalCodeData::class, 'code')]
        public string $postal_code
    ) {}

    // Explicitly testing the case where there's no messages() method
    // but we still want inherited messages to be preserved
}
