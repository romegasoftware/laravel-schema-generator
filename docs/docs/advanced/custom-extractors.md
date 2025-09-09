---
sidebar_position: 1
---

# Custom Extractors

Custom extractors allow you to integrate Laravel Zod Generator with your own validation patterns, legacy codebases, or unique architectural approaches. They're perfect for handling validation classes that don't follow Laravel's standard FormRequest pattern.

## When to Use Custom Extractors

- **Legacy codebases** with non-standard validation approaches
- **Custom validation classes** that don't extend FormRequest
- **Third-party validation libraries** you want to integrate
- **Domain-specific validation patterns** (financial, healthcare, etc.)
- **API validation classes** with unique structures
- **Multi-tenant applications** with tenant-specific validation rules

## Understanding the ExtractorInterface

All extractors must implement the `ExtractorInterface`:

```php
<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use ReflectionClass;

interface ExtractorInterface
{
    /**
     * Check if this extractor can handle the given class
     */
    public function canHandle(ReflectionClass $class): bool;

    /**
     * Extract validation schema information from the class
     */
    public function extract(ReflectionClass $class): array;

    /**
     * Get the priority of this extractor (higher = checked first)
     */
    public function getPriority(): int;
}
```

## Creating a Basic Extractor

### Step 1: Create the Extractor Class

```php
<?php

namespace App\ZodExtractors;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorInterface;

class ApiValidatorExtractor implements ExtractorInterface
{
    /**
     * Check if this extractor can handle the given class
     */
    public function canHandle(ReflectionClass $class): bool
    {
        // Handle classes that implement your custom interface
        return $class->implementsInterface(ApiValidator::class) ||
               str_ends_with($class->getName(), 'ApiValidator');
    }

    /**
     * Extract validation schema information from the class
     */
    public function extract(ReflectionClass $class): array
    {
        $instance = $class->newInstance();
        $schemaName = $this->getSchemaName($class);

        // Extract from your custom validation method
        $rules = $instance->getValidationRules();
        $messages = $instance->getValidationMessages() ?? [];

        $properties = [];
        foreach ($rules as $field => $validationRules) {
            $properties[] = [
                'name' => $field,
                'type' => $this->inferType($validationRules),
                'isOptional' => $this->isOptional($validationRules),
                'validations' => $this->parseValidationRules(
                    $validationRules,
                    $messages[$field] ?? []
                ),
            ];
        }

        return [
            'name' => $schemaName,
            'properties' => $properties,
        ];
    }

    /**
     * Get the priority of this extractor (higher = checked first)
     */
    public function getPriority(): int
    {
        return 200; // Higher than default extractors (100-150)
    }

    private function getSchemaName(ReflectionClass $class): string
    {
        // Extract schema name from class name
        $className = $class->getShortName();
        return str_replace('ApiValidator', 'Schema', $className);
    }

    private function inferType(string|array $rules): string
    {
        $rulesString = is_array($rules) ? implode('|', $rules) : $rules;

        if (str_contains($rulesString, 'integer') || str_contains($rulesString, 'numeric')) {
            return 'number';
        }
        if (str_contains($rulesString, 'boolean')) {
            return 'boolean';
        }
        if (str_contains($rulesString, 'array')) {
            return 'array';
        }

        return 'string'; // Default to string
    }

    private function isOptional(string|array $rules): bool
    {
        $rulesString = is_array($rules) ? implode('|', $rules) : $rules;
        return !str_contains($rulesString, 'required');
    }

    private function parseValidationRules(string|array $rules, array $customMessages): array
    {
        $rulesString = is_array($rules) ? implode('|', $rules) : $rules;
        $validations = [];

        // Parse common validation rules
        if (str_contains($rulesString, 'required')) {
            $validations['required'] = true;
        }
        if (str_contains($rulesString, 'email')) {
            $validations['email'] = true;
        }
        if (preg_match('/min:(\d+)/', $rulesString, $matches)) {
            $validations['min'] = (int) $matches[1];
        }
        if (preg_match('/max:(\d+)/', $rulesString, $matches)) {
            $validations['max'] = (int) $matches[1];
        }
        if (preg_match('/regex:\/(.+)\//', $rulesString, $matches)) {
            $validations['regex'] = $matches[1];
        }

        // Add custom messages
        if (!empty($customMessages)) {
            $validations['customMessages'] = $customMessages;
        }

        return $validations;
    }
}
```

### Step 2: Register the Extractor

Add your extractor to the configuration:

```php
// config/laravel-schema-generator.php
'custom_extractors' => [
    \App\ZodExtractors\ApiValidatorExtractor::class,
],
```

### Step 3: Create Your Validation Interface

```php
<?php

namespace App\Contracts;

interface ApiValidator
{
    public function getValidationRules(): array;
    public function getValidationMessages(): array;
}
```

### Step 4: Use in Your Classes

```php
<?php

namespace App\Validators;

use App\Contracts\ApiValidator;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class UserApiValidator implements ApiValidator
{
    public function getValidationRules(): array
    {
        return [
            'username' => 'required|string|alpha_dash|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:12',
            'role' => 'required|in:admin,editor,viewer',
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'username.alpha_dash' => 'Username can only contain letters, numbers, dashes, and underscores',
            'password.min' => 'Password must be at least 12 characters long',
        ];
    }
}
```

## Real-World Examples

### Financial Validation Extractor

```php
<?php

namespace App\ZodExtractors;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorInterface;

class FinancialValidatorExtractor implements ExtractorInterface
{
    public function canHandle(ReflectionClass $class): bool
    {
        return $class->implementsInterface(FinancialValidator::class) ||
               str_contains($class->getName(), 'Financial');
    }

    public function extract(ReflectionClass $class): array
    {
        $instance = $class->newInstance();

        return [
            'name' => $this->getSchemaName($class),
            'properties' => [
                [
                    'name' => 'account_number',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => [
                        'required' => true,
                        'regex' => '^[0-9]{10,12}$',
                        'customMessages' => [
                            'regex' => 'Account number must be 10-12 digits'
                        ]
                    ],
                ],
                [
                    'name' => 'routing_number',
                    'type' => 'string',
                    'isOptional' => false,
                    'validations' => [
                        'required' => true,
                        'regex' => '^[0-9]{9}$',
                        'customMessages' => [
                            'regex' => 'Routing number must be exactly 9 digits'
                        ]
                    ],
                ],
                [
                    'name' => 'amount',
                    'type' => 'number',
                    'isOptional' => false,
                    'validations' => [
                        'required' => true,
                        'min' => 0.01,
                        'max' => 10000.00,
                        'customMessages' => [
                            'min' => 'Amount must be at least $0.01',
                            'max' => 'Amount cannot exceed $10,000.00'
                        ]
                    ],
                ],
            ],
        ];
    }

    public function getPriority(): int
    {
        return 300; // High priority for financial validation
    }

    private function getSchemaName(ReflectionClass $class): string
    {
        return str_replace('Validator', 'Schema', $class->getShortName());
    }
}
```

### Legacy System Extractor

```php
<?php

namespace App\ZodExtractors;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorInterface;

class LegacyValidatorExtractor implements ExtractorInterface
{
    public function canHandle(ReflectionClass $class): bool
    {
        // Handle old validation classes from legacy system
        return str_starts_with($class->getName(), 'App\\Legacy\\Validators\\') ||
               $class->hasMethod('getLegacyRules');
    }

    public function extract(ReflectionClass $class): array
    {
        $instance = $class->newInstance();

        // Handle different legacy validation patterns
        if ($class->hasMethod('getLegacyRules')) {
            $rules = $instance->getLegacyRules();
            $messages = $instance->getLegacyMessages() ?? [];
        } elseif ($class->hasProperty('validationRules')) {
            $property = $class->getProperty('validationRules');
            $property->setAccessible(true);
            $rules = $property->getValue($instance);
            $messages = [];
        } else {
            $rules = [];
            $messages = [];
        }

        return [
            'name' => $this->convertLegacyName($class->getShortName()),
            'properties' => $this->convertLegacyRules($rules, $messages),
        ];
    }

    public function getPriority(): int
    {
        return 150; // Medium priority
    }

    private function convertLegacyName(string $className): string
    {
        // Convert legacy naming to modern format
        $name = str_replace(['Legacy', 'Validator'], ['', 'Schema'], $className);
        return $name . 'Schema';
    }

    private function convertLegacyRules(array $rules, array $messages): array
    {
        $properties = [];

        foreach ($rules as $field => $rule) {
            // Legacy system might use different rule format
            $properties[] = [
                'name' => $field,
                'type' => $this->mapLegacyType($rule),
                'isOptional' => !($rule['required'] ?? false),
                'validations' => $this->mapLegacyValidations($rule, $messages[$field] ?? []),
            ];
        }

        return $properties;
    }

    private function mapLegacyType(array $rule): string
    {
        return match($rule['type'] ?? 'string') {
            'int', 'integer' => 'number',
            'bool', 'boolean' => 'boolean',
            'list', 'array' => 'array',
            default => 'string',
        };
    }

    private function mapLegacyValidations(array $rule, array $messages): array
    {
        $validations = [];

        // Map legacy validation rules to modern format
        if ($rule['required'] ?? false) {
            $validations['required'] = true;
        }

        if (isset($rule['min_length'])) {
            $validations['min'] = $rule['min_length'];
        }

        if (isset($rule['max_length'])) {
            $validations['max'] = $rule['max_length'];
        }

        if (isset($rule['pattern'])) {
            $validations['regex'] = $rule['pattern'];
        }

        if (!empty($messages)) {
            $validations['customMessages'] = $messages;
        }

        return $validations;
    }
}
```

### Multi-Tenant Extractor

```php
<?php

namespace App\ZodExtractors;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorInterface;

class MultiTenantExtractor implements ExtractorInterface
{
    public function canHandle(ReflectionClass $class): bool
    {
        return $class->implementsInterface(TenantValidator::class) ||
               str_contains($class->getName(), 'Tenant');
    }

    public function extract(ReflectionClass $class): array
    {
        $instance = $class->newInstance();
        $tenantId = $this->getCurrentTenantId();

        // Get tenant-specific rules
        $rules = $instance->getRulesForTenant($tenantId);
        $messages = $instance->getMessagesForTenant($tenantId);

        return [
            'name' => $this->getTenantSchemaName($class, $tenantId),
            'properties' => $this->processTenantRules($rules, $messages),
        ];
    }

    public function getPriority(): int
    {
        return 250; // High priority for tenant-specific validation
    }

    private function getCurrentTenantId(): string
    {
        // Get current tenant from your multi-tenancy system
        return app('tenant')->id ?? 'default';
    }

    private function getTenantSchemaName(ReflectionClass $class, string $tenantId): string
    {
        $baseName = str_replace('Validator', 'Schema', $class->getShortName());
        return $baseName . ucfirst($tenantId);
    }

    private function processTenantRules(array $rules, array $messages): array
    {
        // Process tenant-specific validation rules
        $properties = [];

        foreach ($rules as $field => $validationRules) {
            $properties[] = [
                'name' => $field,
                'type' => $this->inferTypeFromRules($validationRules),
                'isOptional' => !str_contains($validationRules, 'required'),
                'validations' => $this->parseRules($validationRules, $messages[$field] ?? []),
            ];
        }

        return $properties;
    }
}
```

## Advanced Patterns

### Extractor with Dependency Injection

```php
<?php

namespace App\ZodExtractors;

use App\Services\ValidationRuleService;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorInterface;

class DatabaseValidatedExtractor implements ExtractorInterface
{
    public function __construct(
        private ValidationRuleService $validationService
    ) {}

    public function canHandle(ReflectionClass $class): bool
    {
        // Use injected service to determine if we can handle this class
        return $this->validationService->hasCustomRules($class->getName());
    }

    public function extract(ReflectionClass $class): array
    {
        // Use injected service to get validation rules
        $customRules = $this->validationService->getRulesForClass($class->getName());

        return [
            'name' => $this->getSchemaName($class),
            'properties' => $this->processCustomRules($customRules),
        ];
    }

    public function getPriority(): int
    {
        return 400; // Very high priority
    }

    // Implementation methods...
}
```

### Extractor with Caching

```php
<?php

namespace App\ZodExtractors;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorInterface;

class CachedExtractor implements ExtractorInterface
{
    private static array $cache = [];

    public function canHandle(ReflectionClass $class): bool
    {
        $cacheKey = 'can_handle:' . $class->getName();

        if (!isset(self::$cache[$cacheKey])) {
            self::$cache[$cacheKey] = $this->performCanHandleCheck($class);
        }

        return self::$cache[$cacheKey];
    }

    public function extract(ReflectionClass $class): array
    {
        $cacheKey = 'extract:' . $class->getName();

        if (!isset(self::$cache[$cacheKey])) {
            self::$cache[$cacheKey] = $this->performExtraction($class);
        }

        return self::$cache[$cacheKey];
    }

    public function getPriority(): int
    {
        return 200;
    }

    private function performCanHandleCheck(ReflectionClass $class): bool
    {
        // Expensive check logic here
        return str_ends_with($class->getName(), 'CachedValidator');
    }

    private function performExtraction(ReflectionClass $class): array
    {
        // Expensive extraction logic here
        return [
            'name' => $class->getShortName() . 'Schema',
            'properties' => [],
        ];
    }
}
```

## Priority System

Extractors are processed in priority order (higher numbers first):

- **500+**: Critical/override extractors
- **300-499**: Domain-specific extractors (financial, healthcare, etc.)
- **200-299**: Custom/third-party extractors
- **100-199**: Built-in extractors (FormRequest, Data classes)
- **&lt;100**: Fallback extractors

### Priority Best Practices

```php
class ExtractorPriorities
{
    const CRITICAL_OVERRIDE = 1000;    // Override all others
    const DOMAIN_SPECIFIC = 400;       // Financial, healthcare, etc.
    const TENANT_SPECIFIC = 300;       // Multi-tenant validation
    const CUSTOM_HIGH = 250;           // High-priority custom
    const CUSTOM_STANDARD = 200;       // Standard custom extractors
    const BUILT_IN_ENHANCED = 150;     // Enhanced built-in
    const BUILT_IN_STANDARD = 100;     // Standard built-in
    const FALLBACK = 50;               // Last resort
}
```

## Error Handling

### Robust Error Handling

```php
public function extract(ReflectionClass $class): array
{
    try {
        $instance = $class->newInstance();
    } catch (Throwable $e) {
        throw new ExtractionException(
            "Cannot instantiate class {$class->getName()}: {$e->getMessage()}",
            previous: $e
        );
    }

    if (!method_exists($instance, 'getRules')) {
        throw new ExtractionException(
            "Class {$class->getName()} must have a getRules() method"
        );
    }

    $rules = $instance->getRules();

    if (!is_array($rules)) {
        throw new ExtractionException(
            "getRules() must return an array in {$class->getName()}"
        );
    }

    return [
        'name' => $this->getSchemaName($class),
        'properties' => $this->processRules($rules),
    ];
}
```

## Testing Custom Extractors

### Unit Tests

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use App\ZodExtractors\ApiValidatorExtractor;

class ApiValidatorExtractorTest extends TestCase
{
    private ApiValidatorExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ApiValidatorExtractor();
    }

    public function test_can_handle_api_validators(): void
    {
        $class = new ReflectionClass(TestApiValidator::class);

        $this->assertTrue($this->extractor->canHandle($class));
    }

    public function test_cannot_handle_regular_classes(): void
    {
        $class = new ReflectionClass(stdClass::class);

        $this->assertFalse($this->extractor->canHandle($class));
    }

    public function test_extracts_expected_data(): void
    {
        $class = new ReflectionClass(TestApiValidator::class);
        $result = $this->extractor->extract($class);

        $this->assertEquals('TestApiSchema', $result['name']);
        $this->assertIsArray($result['properties']);
        $this->assertCount(2, $result['properties']);
    }

    public function test_has_correct_priority(): void
    {
        $this->assertEquals(200, $this->extractor->getPriority());
    }
}

class TestApiValidator implements ApiValidator
{
    public function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];
    }

    public function getValidationMessages(): array
    {
        return [];
    }
}
```

## Best Practices

### Use Descriptive Priorities

Match your priority to your use case and document the reasoning.

### Handle Errors Gracefully

Always check if methods exist before calling them and provide meaningful error messages.

### Cache Expensive Operations

Store reflection results and complex computations if extracting many classes.

### Follow Naming Conventions

End schema names with "Schema" for consistency.

### Validate Extracted Data

Ensure properties have all required fields before returning.

### Document Your Extractors

Include comments about what patterns they handle and when to use them.

```php
/**
 * Extracts validation rules from legacy financial validators.
 *
 * Handles classes that:
 * - Implement FinancialValidator interface
 * - Have 'Financial' in the class name
 * - Use legacy rule format with amount validation
 *
 * Priority: 300 (high, to override generic extractors)
 */
class FinancialValidatorExtractor implements ExtractorInterface
{
    // Implementation...
}
```

## Next Steps

- [Custom Type Handlers](./custom-type-handlers.md) - Override type conversion behavior
- [Validation Inheritance](./inheritance.md) - Reuse validation rules
- [Examples](../examples/custom-validation.md) - See more real-world examples
- [Troubleshooting](../reference/troubleshooting.md) - Debug extractor issues
