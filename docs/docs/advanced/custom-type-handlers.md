---
sidebar_position: 2
---

# Custom Type Handlers

Custom type handlers give you fine-grained control over how different data types are converted to Zod schemas. They're perfect for adding support for new types, overriding default behavior, or implementing domain-specific validation logic.

## When to Use Custom Type Handlers

- **Override default behavior**: Change how built-in types (string, number, etc.) are handled
- **Add support for new types**: Handle custom PHP types not supported out of the box
- **Domain-specific validation**: Implement specialized validation for your business logic
- **Performance optimization**: Optimize validation for frequently-used types
- **Integration with validation libraries**: Connect with custom validation systems
- **Enhanced error messages**: Provide more specific error messages for your use case

## Understanding the TypeHandlerInterface

All type handlers must implement the `TypeHandlerInterface`:

```php
<?php

namespace RomegaSoftware\LaravelSchemaGenerator\TypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;

interface TypeHandlerInterface
{
    /**
     * Determine if this handler can handle the given type
     */
    public function canHandle(string $type): bool;

    /**
     * Determine if this handler can handle the entire property
     * This allows checking validation rules, not just the type
     */
    public function canHandleProperty(array $property): bool;

    /**
     * Handle the property and return a ZodBuilder
     */
    public function handle(array $property): ZodBuilder;

    /**
     * Get the priority of this handler (higher numbers = higher priority)
     */
    public function getPriority(): int;
}
```

## Creating a Basic Type Handler

### DateTime Type Handler

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;

class DateTimeTypeHandler implements TypeHandlerInterface
{
    /**
     * Determine if this handler can handle the given type
     */
    public function canHandle(string $type): bool
    {
        return in_array($type, ['datetime', 'timestamp', 'date']);
    }

    /**
     * Determine if this handler can handle the entire property
     */
    public function canHandleProperty(array $property): bool
    {
        // Handle based on type
        if ($this->canHandle($property['type'])) {
            return true;
        }

        // Also handle string fields with date-related validation
        $validations = $property['validations'] ?? [];
        return $property['type'] === 'string' && (
            isset($validations['date']) ||
            isset($validations['date_format']) ||
            isset($validations['before']) ||
            isset($validations['after'])
        );
    }

    /**
     * Handle the property and return a ZodBuilder
     */
    public function handle(array $property): ZodBuilder
    {
        $builder = new ZodStringBuilder();
        $validations = $property['validations'] ?? [];

        // Add datetime-specific validation
        if (isset($validations['date_format'])) {
            // Custom format validation
            $format = $validations['date_format'];
            $builder->regex($this->getRegexForDateFormat($format), "Must match format: $format");
        } else {
            // Default ISO date validation
            $builder->regex('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', 'Must be valid ISO datetime format');
        }

        // Handle nullable/optional
        if (isset($validations['nullable'])) {
            $builder->nullable();
        }

        $isOptional = $property['isOptional'] ?? false;
        if ($isOptional && !isset($validations['required'])) {
            $builder->optional();
        }

        // Add custom error messages
        if (isset($validations['customMessages'])) {
            foreach ($validations['customMessages'] as $rule => $message) {
                $builder->withMessage($rule, $message);
            }
        }

        return $builder;
    }

    /**
     * Get the priority of this handler (higher numbers = higher priority)
     */
    public function getPriority(): int
    {
        return 250; // Higher than default string handler (100)
    }

    private function getRegexForDateFormat(string $format): string
    {
        // Convert PHP date format to regex
        $patterns = [
            'Y-m-d' => '/^\d{4}-\d{2}-\d{2}$/',
            'Y-m-d H:i:s' => '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            'm/d/Y' => '/^\d{2}\/\d{2}\/\d{4}$/',
            // Add more formats as needed
        ];

        return $patterns[$format] ?? '/^.+$/'; // Fallback to any string
    }
}
```

## Real-World Examples

### Currency Type Handler

Handle monetary values with proper validation:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodNumberBuilder;

class CurrencyTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return $type === 'currency' || $type === 'money';
    }

    public function canHandleProperty(array $property): bool
    {
        // Handle based on type
        if ($this->canHandle($property['type'])) {
            return true;
        }

        // Also handle number fields with currency-related names
        $propertyName = $property['name'];
        return $property['type'] === 'number' && (
            str_contains($propertyName, 'price') ||
            str_contains($propertyName, 'amount') ||
            str_contains($propertyName, 'cost') ||
            str_contains($propertyName, 'fee') ||
            str_ends_with($propertyName, '_cents')
        );
    }

    public function handle(array $property): ZodBuilder
    {
        $builder = new ZodNumberBuilder();
        $validations = $property['validations'] ?? [];
        $propertyName = $property['name'];

        // Currency-specific validations
        if (str_ends_with($propertyName, '_cents')) {
            // Handle cents - must be whole number
            $builder->int('Must be a whole number of cents');
            $builder->min(0, 'Amount cannot be negative');
        } else {
            // Handle dollar amounts - allow decimals
            $builder->min(0, 'Amount cannot be negative');

            // Limit to 2 decimal places for currency
            $builder->transform('(val) => Math.round(val * 100) / 100');
        }

        // Apply custom validations
        if (isset($validations['min'])) {
            $min = $validations['min'];
            $builder->min($min, "Minimum amount is $" . number_format($min, 2));
        }

        if (isset($validations['max'])) {
            $max = $validations['max'];
            $builder->max($max, "Maximum amount is $" . number_format($max, 2));
        }

        // Handle nullable/optional
        if (isset($validations['nullable'])) {
            $builder->nullable();
        }

        $isOptional = $property['isOptional'] ?? false;
        if ($isOptional && !isset($validations['required'])) {
            $builder->optional();
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 300; // Higher than default number handler
    }
}
```

### UUID Type Handler

Handle UUID and ULID validation:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;

class UuidTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return false; // Only handle based on validation rules
    }

    public function canHandleProperty(array $property): bool
    {
        $validations = $property['validations'] ?? [];

        // Handle any field with UUID validation
        return isset($validations['uuid']) ||
               isset($validations['ulid']) ||
               str_contains($property['name'], '_uuid') ||
               str_contains($property['name'], '_ulid');
    }

    public function handle(array $property): ZodBuilder
    {
        $builder = new ZodStringBuilder();
        $validations = $property['validations'] ?? [];

        if (isset($validations['uuid'])) {
            // UUID v4 validation
            $builder->regex(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                'Must be a valid UUID'
            );
        } elseif (isset($validations['ulid'])) {
            // ULID validation
            $builder->regex(
                '/^[0-9A-HJKMNP-TV-Z]{26}$/i',
                'Must be a valid ULID'
            );
        }

        // Handle nullable/optional
        if (isset($validations['nullable'])) {
            $builder->nullable();
        }

        $isOptional = $property['isOptional'] ?? false;
        if ($isOptional && !isset($validations['required'])) {
            $builder->optional();
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 400; // Very high priority to catch UUIDs before string handler
    }
}
```

### Enhanced Email Handler

Extend the built-in email handler with domain validation:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;

class EnhancedEmailTypeHandler implements TypeHandlerInterface
{
    private array $allowedDomains;
    private array $blockedDomains;

    public function __construct()
    {
        $this->allowedDomains = config('validation.allowed_email_domains', []);
        $this->blockedDomains = config('validation.blocked_email_domains', [
            'tempmail.org', 'guerrillamail.com', '10minutemail.com'
        ]);
    }

    public function canHandle(string $type): bool
    {
        return false; // Only handle based on validation rules
    }

    public function canHandleProperty(array $property): bool
    {
        $validations = $property['validations'] ?? [];
        return isset($validations['email']);
    }

    public function handle(array $property): ZodBuilder
    {
        $builder = new ZodStringBuilder();
        $validations = $property['validations'] ?? [];

        // Basic email validation
        $builder->email('Please enter a valid email address');

        // Domain restrictions
        if (!empty($this->allowedDomains)) {
            $pattern = '/^[^@]+@(' . implode('|', array_map('preg_quote', $this->allowedDomains)) . ')$/i';
            $allowedDomainsStr = implode(', ', $this->allowedDomains);
            $builder->regex($pattern, "Email must be from allowed domains: $allowedDomainsStr");
        }

        if (!empty($this->blockedDomains)) {
            $pattern = '/^[^@]+@(?!' . implode('|', array_map('preg_quote', $this->blockedDomains)) . ').*$/i';
            $builder->regex($pattern, 'This email domain is not allowed');
        }

        // Length restrictions
        if (isset($validations['max'])) {
            $builder->max($validations['max'], "Email must be no more than {$validations['max']} characters");
        }

        // Handle nullable/optional
        if (isset($validations['nullable'])) {
            $builder->nullable();
        }

        $isOptional = $property['isOptional'] ?? false;
        if ($isOptional && !isset($validations['required'])) {
            $builder->optional();
        }

        // Custom messages
        if (isset($validations['customMessages'])) {
            foreach ($validations['customMessages'] as $rule => $message) {
                $builder->withMessage($rule, $message);
            }
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 200; // Higher than default email handler (110)
    }
}
```

## Advanced Patterns

### Conditional Type Handler

Handle different validation based on context:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;

class ConditionalPasswordHandler implements TypeHandlerInterface
{
    public function canHandleProperty(array $property): bool
    {
        return str_contains($property['name'], 'password') &&
               $property['type'] === 'string';
    }

    public function handle(array $property): ZodBuilder
    {
        $builder = new ZodStringBuilder();
        $validations = $property['validations'] ?? [];

        // Different validation based on context
        if (str_contains($property['name'], 'admin')) {
            // Admin passwords need stronger validation
            $builder->min(16, 'Admin passwords must be at least 16 characters');
            $builder->regex(
                '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'Admin passwords must contain uppercase, lowercase, number, and special character'
            );
        } elseif (str_contains($property['name'], 'temp')) {
            // Temporary passwords can be simpler
            $builder->min(8, 'Temporary passwords must be at least 8 characters');
        } else {
            // Regular user passwords
            $builder->min(12, 'Passwords must be at least 12 characters');
            $builder->regex(
                '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                'Passwords must contain uppercase, lowercase, and number'
            );
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 300;
    }

    public function canHandle(string $type): bool
    {
        return false; // Only use canHandleProperty
    }
}
```

### Composite Type Handler

Handle complex composite validations:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodObjectBuilder;

class AddressTypeHandler implements TypeHandlerInterface
{
    public function canHandleProperty(array $property): bool
    {
        return $property['name'] === 'address' ||
               str_ends_with($property['name'], '_address');
    }

    public function handle(array $property): ZodBuilder
    {
        // Create a composite address object
        $builder = new ZodObjectBuilder();

        $builder->addProperty('street', (new ZodStringBuilder())
            ->min(1, 'Street address is required')
            ->max(255));

        $builder->addProperty('city', (new ZodStringBuilder())
            ->min(1, 'City is required')
            ->max(100));

        $builder->addProperty('state', (new ZodStringBuilder())
            ->length(2, 'State must be 2 characters')
            ->regex('/^[A-Z]{2}$/', 'State must be uppercase letters'));

        $builder->addProperty('postal_code', (new ZodStringBuilder())
            ->regex('/^\d{5}(-\d{4})?$/', 'Invalid postal code format'));

        $builder->addProperty('country', (new ZodStringBuilder())
            ->length(2, 'Country must be 2 characters')
            ->regex('/^[A-Z]{2}$/', 'Country must be uppercase letters'));

        return $builder;
    }

    public function getPriority(): int
    {
        return 350;
    }

    public function canHandle(string $type): bool
    {
        return $type === 'address';
    }
}
```

### Type Handler with External API Integration

```php
<?php

namespace App\ZodTypeHandlers;

use App\Services\ValidationApiService;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;

class ExternalValidatedHandler implements TypeHandlerInterface
{
    public function __construct(
        private ValidationApiService $validationService
    ) {}

    public function canHandleProperty(array $property): bool
    {
        // Check if external API has validation rules for this property
        return $this->validationService->hasValidationFor($property['name']);
    }

    public function handle(array $property): ZodBuilder
    {
        $builder = new ZodStringBuilder();

        // Get validation rules from external API
        $externalRules = $this->validationService->getValidationRules($property['name']);

        foreach ($externalRules as $rule) {
            match($rule['type']) {
                'min_length' => $builder->min($rule['value'], $rule['message'] ?? null),
                'max_length' => $builder->max($rule['value'], $rule['message'] ?? null),
                'regex' => $builder->regex($rule['pattern'], $rule['message'] ?? null),
                'email' => $builder->email($rule['message'] ?? null),
                default => null,
            };
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 500; // Very high priority for external validation
    }

    public function canHandle(string $type): bool
    {
        return false; // Only use canHandleProperty
    }
}
```

## Priority System

Type handlers are processed in priority order (higher numbers first):

- **1000+**: Critical overrides (complete replacement of default behavior)
- **500-999**: High-priority custom handlers (UUID, specialized validation)
- **300-499**: Domain-specific handlers (currency, datetime, etc.)
- **200-299**: Feature-specific handlers (email validation, data classes)
- **100-199**: Basic type handlers (string, number, boolean, array)
- **0-99**: Fallback handlers

### Priority Best Practices

```php
class TypeHandlerPriorities
{
    const CRITICAL_OVERRIDE = 1000;    // Override everything
    const EXTERNAL_API = 500;          // External validation
    const UUID_VALIDATION = 400;       // UUID/ULID handling
    const DOMAIN_SPECIFIC = 300;       // Currency, datetime, etc.
    const ENHANCED_BUILT_IN = 200;     // Enhanced email, etc.
    const BUILT_IN = 100;              // Default handlers
    const FALLBACK = 50;               // Last resort
}
```

## Registration and Configuration

### Register in Configuration

```php
// config/laravel-schema-generator.php
'custom_type_handlers' => [
    \App\ZodTypeHandlers\DateTimeTypeHandler::class,
    \App\ZodTypeHandlers\CurrencyTypeHandler::class,
    \App\ZodTypeHandlers\UuidTypeHandler::class,
    \App\ZodTypeHandlers\EnhancedEmailTypeHandler::class,
],
```

### Service Provider Registration

For handlers with dependencies:

```php
// app/Providers/ZodGeneratorServiceProvider.php
<?php

namespace App\Providers;

use App\Services\ValidationApiService;
use App\ZodTypeHandlers\ExternalValidatedHandler;
use Illuminate\Support\ServiceProvider;

class ZodGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExternalValidatedHandler::class, function ($app) {
            return new ExternalValidatedHandler(
                $app->make(ValidationApiService::class)
            );
        });
    }
}
```

## Testing Type Handlers

### Unit Tests

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\ZodTypeHandlers\CurrencyTypeHandler;

class CurrencyTypeHandlerTest extends TestCase
{
    private CurrencyTypeHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new CurrencyTypeHandler();
    }

    public function test_handles_currency_types(): void
    {
        $this->assertTrue($this->handler->canHandle('currency'));
        $this->assertTrue($this->handler->canHandle('money'));
        $this->assertFalse($this->handler->canHandle('string'));
    }

    public function test_handles_price_properties(): void
    {
        $property = [
            'name' => 'product_price',
            'type' => 'number',
            'validations' => [],
        ];

        $this->assertTrue($this->handler->canHandleProperty($property));
    }

    public function test_generates_currency_validation(): void
    {
        $property = [
            'name' => 'price',
            'type' => 'currency',
            'validations' => [
                'min' => 0.01,
                'max' => 999.99,
            ],
        ];

        $builder = $this->handler->handle($property);
        $ValidationSchema = $builder->build();

        $this->assertStringContainsString('z.number()', $ValidationSchema);
        $this->assertStringContainsString('.min(0.01', $ValidationSchema);
        $this->assertStringContainsString('.max(999.99', $ValidationSchema);
    }

    public function test_has_correct_priority(): void
    {
        $this->assertEquals(300, $this->handler->getPriority());
    }
}
```

### Integration Tests

```php
<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\ZodTypeHandlers\DateTimeTypeHandler;

class TypeHandlerIntegrationTest extends TestCase
{
    public function test_type_handler_integrates_with_generator(): void
    {
        // Create a test validation class
        $this->createTestClass();

        // Run the generator
        $this->artisan('schema:generate --dry-run')
             ->expectsOutput('Generated 1 schemas successfully!');

        // Check the generated schema includes our custom type handling
        $generatedContent = $this->getGeneratedContent();
        $this->assertStringContainsString('datetime', $generatedContent);
        $this->assertStringContainsString('ISO datetime format', $generatedContent);
    }

    private function createTestClass(): void
    {
        // Create test validation class with datetime field
        // Implementation depends on your test setup
    }
}
```

## Debugging Type Handlers

### Debug Information

Add debug logging to your handlers:

```php
public function handle(array $property): ZodBuilder
{
    if (config('app.debug')) {
        logger()->info('Custom type handler processing', [
            'handler' => static::class,
            'property' => $property['name'],
            'type' => $property['type'],
            'validations' => $property['validations'] ?? [],
        ]);
    }

    // Handler implementation...
}
```

### Troubleshooting Common Issues

#### Handler Not Being Used

```php
// Debug which handler is being selected
$generator = app(\RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator::class);
$registry = $generator->getTypeHandlerRegistry();

$property = ['name' => 'test', 'type' => 'string', 'validations' => []];
$handler = $registry->getHandlerForProperty($property);
dd(get_class($handler)); // Shows which handler was selected
```

#### Priority Issues

```php
// Check handler priorities
$registry = app(\RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerRegistry::class);
$handlers = $registry->getAllHandlers();

foreach ($handlers as $handler) {
    echo get_class($handler) . ': ' . $handler->getPriority() . "\n";
}
```

## Best Practices

### Use Appropriate Priorities

Set priorities that reflect when your handler should be checked relative to others.

### Handle Edge Cases

Always account for nullable and optional fields:

```php
public function handle(array $property): ZodBuilder
{
    $builder = new ZodStringBuilder();

    // Your custom logic here

    // Always handle these standard cases
    $validations = $property['validations'] ?? [];

    if (isset($validations['nullable'])) {
        $builder->nullable();
    }

    $isOptional = $property['isOptional'] ?? false;
    if ($isOptional && !isset($validations['required'])) {
        $builder->optional();
    }

    return $builder;
}
```

### Provide Clear Error Messages

Use descriptive validation messages:

```php
$builder->min(8, 'Password must be at least 8 characters long');
$builder->regex('/^[A-Z]/', 'Must start with an uppercase letter');
```

### Leverage the Builder Pattern

Use existing ZodBuilders when possible rather than building strings manually:

```php
// Good: Use builders
$builder = new ZodStringBuilder();
$builder->email('Invalid email format');

// Avoid: Manual string building
$zodString = 'z.string().email("Invalid email format")';
```

### Test Thoroughly

Write tests for all validation scenarios your handler supports:

```php
/**
 * @dataProvider validationDataProvider
 */
public function test_validation_scenarios($input, $expectedValid, $expectedErrors): void
{
    $property = ['name' => 'test', 'type' => 'custom', 'validations' => $input];
    $builder = $this->handler->handle($property);

    // Test the generated validation
    // Implementation depends on your testing approach
}
```

## Next Steps

- [Validation Inheritance](./inheritance.md) - Reuse validation rules between classes
- [Integration](./integration.md) - Integrate with existing TypeScript workflows
- [Examples](../examples/custom-validation.md) - See more custom validation examples
- [Troubleshooting](../reference/troubleshooting.md) - Debug type handler issues
