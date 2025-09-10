<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\DataClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use Spatie\LaravelData\Data;

class DataClassWildcardTest extends TestCase
{
    private DataClassExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = $this->app->make(DataClassExtractor::class);
    }

    #[Test]
    public function it_handles_wildcard_array_validation_in_data_class(): void
    {
        $reflection = new \ReflectionClass(DataWithWildcardRules::class);
        
        $result = $this->extractor->extract($reflection);
        
        $this->assertEquals('DataWithWildcardRulesSchema', $result->name);
        
        // Debug: see what properties we get
        $propertyNames = $result->properties->toCollection()->pluck('name')->toArray();
        $this->assertNotEmpty($propertyNames, 'Should have properties. Got: ' . json_encode($propertyNames));
        
        // Find the tags property
        $tagsProperty = $result->properties->toCollection()->firstWhere('name', 'tags');
        $this->assertNotNull($tagsProperty, 'Tags property not found. Available properties: ' . json_encode($propertyNames));
        $this->assertEquals('array', $tagsProperty->validations->inferredType);
        
        // Check for nested validations
        $nestedValidations = $tagsProperty->validations->nestedValidations;
        $this->assertNotNull($nestedValidations, 'Tags should have nested validations for array items');
    }

    #[Test]
    public function it_handles_nested_object_array_validation(): void
    {
        $reflection = new \ReflectionClass(DataWithNestedObjectArray::class);
        
        $result = $this->extractor->extract($reflection);
        
        // Find the items property
        $itemsProperty = $result->properties->toCollection()->firstWhere('name', 'items');
        $this->assertNotNull($itemsProperty);
        $this->assertEquals('array', $itemsProperty->validations->inferredType);
        
        // Check for nested object properties
        $nestedValidations = $itemsProperty->validations->nestedValidations;
        $this->assertNotNull($nestedValidations, 'Items should have nested validations');
        
        // Check that nested object has properties
        $objectProperties = $nestedValidations->objectProperties ?? null;
        $this->assertNotNull($objectProperties, 'Nested items should have object properties');
        $this->assertArrayHasKey('name', $objectProperties);
        $this->assertArrayHasKey('price', $objectProperties);
    }
}

// Test Data classes

#[ValidationSchema]
class DataWithWildcardRules extends Data
{
    public function __construct(
        public array $tags = []
    ) {}
    
    public static function rules(): array
    {
        return [
            'tags' => 'array',
            'tags.*' => 'required|string|min:2|max:50'
        ];
    }
}

#[ValidationSchema]
class DataWithNestedObjectArray extends Data
{
    public function __construct(
        public array $items = []
    ) {}
    
    public static function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.name' => 'required|string|max:100',
            'items.*.price' => 'required|numeric|min:0'
        ];
    }
}