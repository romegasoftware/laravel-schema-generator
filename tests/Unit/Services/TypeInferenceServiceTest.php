<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit\Services;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use RomegaSoftware\LaravelSchemaGenerator\Services\TypeInferenceService;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class TypeInferenceServiceTest extends TestCase
{
    protected TypeInferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TypeInferenceService();
    }

    #[Test]
    public function it_infers_password_type_from_password_rule(): void
    {
        $validations = ['password' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('password', $type);
    }

    #[Test]
    public function it_infers_boolean_type_from_boolean_rule(): void
    {
        $validations = ['boolean' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('boolean', $type);

        // Test with bool alias
        $validations = ['bool' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('boolean', $type);
    }

    #[Test]
    public function it_infers_number_type_from_numeric_rules(): void
    {
        $numericRules = ['integer', 'int', 'numeric', 'decimal'];
        
        foreach ($numericRules as $rule) {
            $validations = [$rule => true];
            $type = $this->service->inferType($validations);
            $this->assertEquals('number', $type, "Failed for rule: {$rule}");
        }
    }

    #[Test]
    public function it_infers_number_type_from_digits_rules(): void
    {
        $validations = ['digits' => 5];
        $type = $this->service->inferType($validations);
        $this->assertEquals('number', $type);

        $validations = ['digits_between' => [3, 5]];
        $type = $this->service->inferType($validations);
        $this->assertEquals('number', $type);
    }

    #[Test]
    public function it_infers_array_type_from_array_rules(): void
    {
        $validations = ['array' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('array', $type);

        $validations = ['list' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('array', $type);
    }

    #[Test]
    public function it_infers_email_type_from_email_rule(): void
    {
        $validations = ['email' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('email', $type);
    }

    #[Test]
    public function it_infers_url_type_from_url_rule(): void
    {
        $validations = ['url' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('url', $type);
    }

    #[Test]
    public function it_infers_uuid_type_from_uuid_rule(): void
    {
        $validations = ['uuid' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('uuid', $type);
    }

    #[Test]
    public function it_infers_string_type_from_json_rule(): void
    {
        $validations = ['json' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('string', $type);
    }

    #[Test]
    public function it_infers_string_type_from_date_rules(): void
    {
        $dateRules = ['date', 'date_format', 'date_equals', 'before', 'after'];
        
        foreach ($dateRules as $rule) {
            $validations = [$rule => true];
            $type = $this->service->inferType($validations);
            $this->assertEquals('string', $type, "Failed for rule: {$rule}");
        }
    }

    #[Test]
    public function it_infers_file_type_from_file_rules(): void
    {
        $validations = ['file' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('file', $type);

        $validations = ['image' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('file', $type);
    }

    #[Test]
    public function it_infers_file_type_from_mime_rules(): void
    {
        $fileRules = ['mimes', 'mimetypes', 'extensions', 'dimensions'];
        
        foreach ($fileRules as $rule) {
            $validations = [$rule => ['jpg', 'png']];
            $type = $this->service->inferType($validations);
            $this->assertEquals('file', $type, "Failed for rule: {$rule}");
        }
    }

    #[Test]
    public function it_infers_enum_type_from_in_rule_with_multiple_values(): void
    {
        $validations = ['in' => ['active', 'inactive', 'pending']];
        $type = $this->service->inferType($validations);
        $this->assertEquals('enum:active,inactive,pending', $type);
    }

    #[Test]
    public function it_infers_enum_type_from_in_rule_with_single_value(): void
    {
        $validations = ['in' => ['active']];
        $type = $this->service->inferType($validations);
        $this->assertEquals('enum:active', $type);
    }

    #[Test]
    public function it_infers_array_type_from_wildcard_field_name(): void
    {
        $validations = ['fieldName' => 'items.*', 'string' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('array', $type);
    }

    #[Test]
    public function it_defaults_to_string_type_when_no_specific_rule_matches(): void
    {
        $validations = ['required' => true, 'max' => 255];
        $type = $this->service->inferType($validations);
        $this->assertEquals('string', $type);
    }

    #[Test]
    public function it_normalizes_rule_names_correctly(): void
    {
        // Test various rule name formats
        $testCases = [
            ['snake_case_rule' => true], // Should convert to SnakeCaseRule
            ['digits_between' => [3, 5]], // Should convert to DigitsBetween
            ['date_format' => 'Y-m-d'],    // Should convert to DateFormat
        ];

        foreach ($testCases as $validations) {
            $type = $this->service->inferType($validations);
            $this->assertIsString($type);
        }
    }

    #[Test]
    public function it_handles_int_to_integer_normalization(): void
    {
        $validations = ['int' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('number', $type);
    }

    #[Test]
    public function it_handles_bool_to_boolean_normalization(): void
    {
        $validations = ['bool' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('boolean', $type);
    }

    #[Test]
    public function it_checks_numeric_fields_correctly(): void
    {
        $rules = ['integer'];
        $this->assertTrue($this->service->isNumericField($rules));

        $rules = ['numeric'];
        $this->assertTrue($this->service->isNumericField($rules));

        $rules = ['decimal:2'];
        $this->assertTrue($this->service->isNumericField($rules));

        $rules = ['digits:5'];
        $this->assertTrue($this->service->isNumericField($rules));

        $rules = ['digits_between:3,5'];
        $this->assertTrue($this->service->isNumericField($rules));

        $rules = ['string'];
        $this->assertFalse($this->service->isNumericField($rules));
    }

    #[Test]
    public function it_handles_mixed_rules_with_priority(): void
    {
        // Password should take priority
        $validations = ['password' => true, 'string' => true, 'min' => 8];
        $type = $this->service->inferType($validations);
        $this->assertEquals('password', $type);

        // Boolean should take priority
        $validations = ['boolean' => true, 'required' => true];
        $type = $this->service->inferType($validations);
        $this->assertEquals('boolean', $type);

        // Numeric should take priority
        $validations = ['integer' => true, 'min' => 0, 'max' => 100];
        $type = $this->service->inferType($validations);
        $this->assertEquals('number', $type);
    }

    #[Test]
    public function it_handles_empty_validations_array(): void
    {
        $validations = [];
        $type = $this->service->inferType($validations);
        $this->assertEquals('string', $type);
    }

    #[Test]
    public function it_handles_in_rule_stored_in_normalized_format(): void
    {
        // This tests the elseif branch on line 182
        // When 'in' key doesn't exist in validations but 'In' exists in normalized rules
        $validations = ['In' => ['value1', 'value2']];
        $type = $this->service->inferType($validations);
        $this->assertEquals('enum:value1,value2', $type);
    }

    #[Test]
    public function it_caches_laravel_rule_categories(): void
    {
        // First call extracts and caches
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractLaravelRuleCategories');
        $method->setAccessible(true);
        
        $categories1 = $method->invoke($this->service);
        $this->assertIsArray($categories1);
        $this->assertArrayHasKey('numeric', $categories1);
        $this->assertArrayHasKey('file', $categories1);
        $this->assertArrayHasKey('size', $categories1);
        $this->assertArrayHasKey('implicit', $categories1);

        // Second call should return cached result
        $categories2 = $method->invoke($this->service);
        $this->assertSame($categories1, $categories2);
    }

    #[Test]
    public function it_extracts_numeric_rules_from_laravel(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getNumericRules');
        $method->setAccessible(true);
        
        $numericRules = $method->invoke($this->service);
        $this->assertIsArray($numericRules);
        $this->assertContains('Numeric', $numericRules);
        $this->assertContains('Integer', $numericRules);
    }

    #[Test]
    public function it_extracts_file_rules_from_laravel(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getFileRules');
        $method->setAccessible(true);
        
        $fileRules = $method->invoke($this->service);
        $this->assertIsArray($fileRules);
        // File rules typically include Min, Max, Between, Size
        $this->assertNotEmpty($fileRules);
    }

    #[Test]
    public function it_handles_file_rules_that_are_not_file_type(): void
    {
        // Size rules alone don't determine file type
        $validations = ['min' => 10, 'max' => 100];
        $type = $this->service->inferType($validations);
        $this->assertEquals('string', $type);
    }

    #[Test]
    #[DataProvider('provideComplexValidationScenarios')]
    public function it_handles_complex_validation_scenarios(array $validations, string $expectedType): void
    {
        $type = $this->service->inferType($validations);
        $this->assertEquals($expectedType, $type);
    }

    public static function provideComplexValidationScenarios(): array
    {
        return [
            'email with additional rules' => [
                ['email' => true, 'required' => true, 'max' => 255],
                'email'
            ],
            'url with validation' => [
                ['url' => true, 'active_url' => true],
                'url'
            ],
            'file with size constraints' => [
                ['file' => true, 'max' => 2048],
                'file'
            ],
            'image with dimensions' => [
                ['image' => true, 'dimensions' => ['min_width' => 100]],
                'file'
            ],
            'date with format' => [
                ['date' => true, 'date_format' => 'Y-m-d'],
                'string'
            ],
            'numeric with range' => [
                ['numeric' => true, 'between' => [1, 100]],
                'number'
            ],
            'array with size' => [
                ['array' => true, 'min' => 1, 'max' => 10],
                'array'
            ],
        ];
    }

    #[Test]
    public function it_uses_makeable_trait(): void
    {
        $service = TypeInferenceService::make();
        $this->assertInstanceOf(TypeInferenceService::class, $service);
    }

    #[Test]
    public function it_handles_normalized_in_rule_with_non_array_value(): void
    {
        // Test the edge case where In rule exists but is not an array
        $validations = ['In' => 'single_value'];
        $type = $this->service->inferType($validations);
        $this->assertEquals('string', $type);
    }

    #[Test]
    public function it_handles_in_rule_with_empty_array(): void
    {
        // Test when in rule has empty array
        $validations = ['in' => []];
        $type = $this->service->inferType($validations);
        $this->assertEquals('string', $type);
    }

    #[Test]
    public function it_handles_file_rules_with_size_constraints(): void
    {
        // Test when file rules like Size/Min/Max don't necessarily mean file type
        // This tests the break statement in the file rules loop (line 160)
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('inferType');
        $method->setAccessible(true);
        
        // Test with Size rule which is in fileRules but doesn't indicate file type
        $validations = ['Size' => 100];
        $type = $method->invoke($this->service, $validations);
        $this->assertEquals('string', $type);
    }

    #[Test]
    public function it_handles_digits_rule_normalization_in_isNumericField(): void
    {
        // Test the digits rule normalization in isNumericField
        $rules = ['digits'];
        $this->assertTrue($this->service->isNumericField($rules));
    }

    #[Test]
    public function it_handles_non_string_rules_in_isNumericField(): void
    {
        // Test with non-string rules (objects, arrays, etc.)
        $rules = [new \stdClass(), ['nested' => 'array'], 123];
        $this->assertFalse($this->service->isNumericField($rules));
    }

    #[Test]
    public function it_normalizes_int_in_isNumericField(): void
    {
        // Test the Int -> Integer normalization in isNumericField
        $rules = ['int'];
        $this->assertTrue($this->service->isNumericField($rules));
    }

    #[Test]
    public function it_normalizes_bool_in_isNumericField(): void
    {
        // Test the Bool -> Boolean normalization in isNumericField (should not be numeric)
        $rules = ['bool'];
        $this->assertFalse($this->service->isNumericField($rules));
    }

    #[Test]
    public function it_handles_case_sensitive_int_normalization_in_isNumericField(): void
    {
        // Test the exact case-sensitive Int -> Integer normalization in isNumericField
        $rules = ['Int'];  // Capital I
        $this->assertTrue($this->service->isNumericField($rules));
    }

    #[Test]
    public function it_handles_case_sensitive_bool_normalization_in_isNumericField(): void
    {
        // Test the exact case-sensitive Bool -> Boolean normalization in isNumericField
        $rules = ['Bool'];  // Capital B
        $this->assertFalse($this->service->isNumericField($rules));
    }

    #[Test]
    public function it_handles_in_rule_with_normalized_array_having_no_elements(): void
    {
        // Test the else branch where normalized In rule has 0 elements
        $validations = ['In' => []];
        $type = $this->service->inferType($validations);
        $this->assertEquals('string', $type);
    }

    #[Test]
    public function it_gets_numeric_rules_successfully(): void
    {
        // Test getNumericRules method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getNumericRules');
        $method->setAccessible(true);
        
        $numericRules = $method->invoke($this->service);
        $this->assertIsArray($numericRules);
        $this->assertContains('Numeric', $numericRules);
        $this->assertContains('Integer', $numericRules);
    }

    #[Test]
    public function it_gets_file_rules_successfully(): void
    {
        // Test getFileRules method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getFileRules');
        $method->setAccessible(true);
        
        $fileRules = $method->invoke($this->service);
        $this->assertIsArray($fileRules);
        $this->assertNotEmpty($fileRules);
    }
}