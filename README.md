# Laravel Zod Generator

Generate TypeScript Zod validation schemas from your Laravel validation rules. This package supports multiple validation sources including Laravel FormRequest classes, Spatie Data classes, and any PHP class with validation rules.

## Features

- 🚀 **Zero Dependencies** - Works with vanilla Laravel, additional features unlock with optional packages
- 📦 **Smart Package Detection** - Automatically detects and uses installed packages
- 🎯 **Multiple Validation Sources**:
    - Laravel FormRequest classes
    - Spatie Laravel Data classes (when installed)
    - Any PHP class with a `rules()` method
    - Custom validation classes via extractors
- 🔧 **Flexible Configuration** - Customize output paths, formats, scan paths, and integration settings
- 🪝 **Auto-Integration** - Optionally hooks into Spatie's `typescript:transform` command
- 🧩 **Highly Extensible**:
    - **Custom Extractors** - Handle your own validation patterns and legacy systems
    - **Custom Type Handlers** - Override default behavior or add support for new types
    - **Priority System** - Control the order of processing with configurable priorities
    - **Laravel Container Integration** - Full dependency injection support
- 🎨 **Advanced Features**:
    - TypeScript namespace or module output formats
    - App types integration for existing TypeScript projects

## Installation

### Basic Installation (Laravel only)

```bash
composer require romegasoftware/laravel-zod-generator
```

### With Spatie Data Support

```bash
composer require spatie/laravel-data
composer require romegasoftware/laravel-zod-generator
```

### Full Integration

```bash
composer require spatie/laravel-data
composer require spatie/laravel-typescript-transformer
composer require romegasoftware/laravel-zod-generator
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-zod-generator-config
```

> **Note**: If you're using Spatie Data classes, the package automatically provides sensible defaults without requiring you to publish their configuration. Your existing Spatie Data configuration will be respected if already present.

This will create `config/laravel-zod-generator.php` with comprehensive configuration options:

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    |
    | The paths where the package will look for classes with the #[ZodSchema]
    | attribute. By default, it scans the app directory.
    |
    */
    'scan_paths' => [
        app_path(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where and how the generated TypeScript file will be written.
    | 
    | - path: Where to save the generated TypeScript file
    | - format: 'module' (ES6 exports) or 'namespace' (TypeScript namespace)
    |
    */
    'output' => [
        'path' => resource_path('js/types/zod-schemas.ts'),
        'format' => 'module', // 'module' or 'namespace'
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespace Configuration
    |--------------------------------------------------------------------------
    |
    | If using namespace format, specify the namespace name.
    |
    */
    'namespace' => 'Schemas',

    /*
    |--------------------------------------------------------------------------
    | App Types Integration
    |--------------------------------------------------------------------------
    |
    | Configure integration with existing TypeScript types in your project.
    | Useful when you have generated types from PHP classes.
    |
    */
    'app_types_import_path' => '.',
    'use_app_types' => false,

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Control which features are enabled. Set to 'auto' to auto-detect based
    | on installed packages, or explicitly set to true/false.
    |
    */
    'features' => [
        // Support for Spatie Laravel Data classes
        'data_classes' => 'auto',

        // Hook into typescript:transform command
        'typescript_transformer_hook' => 'auto',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Extractors
    |--------------------------------------------------------------------------
    |
    | Register custom extractors to handle additional validation sources.
    | Each extractor must implement ExtractorInterface.
    |
    */
    'custom_extractors' => [
        // \App\ZodExtractors\CustomExtractor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Type Handlers
    |--------------------------------------------------------------------------
    |
    | Register custom type handlers to override default behavior or add support
    | for additional types. Each handler must implement TypeHandlerInterface.
    | Handlers are processed in priority order (higher numbers = higher priority).
    |
    */
    'custom_type_handlers' => [
        // \App\ZodTypeHandlers\CustomStringHandler::class,
        // \App\ZodTypeHandlers\DateTimeHandler::class,
    ],
];
```

### Configuration Options Explained

- **scan_paths**: Directories to scan for classes with `#[ZodSchema]` attributes
- **output.path**: Where to save the generated TypeScript file
- **output.format**: Output format (`module` for ES6 exports, `namespace` for TypeScript namespaces)
- **namespace**: Namespace name when using `namespace` format
- **app_types_import_path**: Import path for existing TypeScript types
- **use_app_types**: Whether to reference App.* types in generated schemas
- **features**: Auto-detected feature flags that can be overridden
- **custom_extractors**: Classes that extract validation rules from custom sources
- **custom_type_handlers**: Classes that handle type-to-Zod conversion with custom logic

## Usage

### 1. Add the `#[ZodSchema]` Attribute

Add the `#[ZodSchema]` attribute to any class you want to generate a schema for:

#### Laravel FormRequest

```php
use RomegaSoftware\LaravelZodGenerator\Attributes\ZodSchema;

#[ZodSchema]
class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'age' => 'nullable|integer|min:18|max:120',
            'website' => 'nullable|url',
            'tags' => 'array',
            'tags.*' => 'string|regex:/^[a-z]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name',
            'email.email' => 'Please enter a valid email address',
            'tags.*.regex' => 'Tags must be lowercase letters only',
        ];
    }
}
```

#### Spatie Data Class (if installed)

```php
use RomegaSoftware\LaravelZodGenerator\Attributes\ZodSchema;
use RomegaSoftware\LaravelZodGenerator\Attributes\InheritValidationFrom;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\*;

#[ZodSchema]
class UserData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, Email]
        public string $email,

        #[Nullable, Min(18), Max(120)]
        public ?int $age,

        #[DataCollectionOf(TagData::class)]
        public DataCollection $tags,
    ) {}
}

#[ZodSchema]
class AddressData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $street,

        #[InheritValidationFrom(PostalCodeData::class, 'code')]
        public string $postal_code,
    ) {}
}
```

#### Custom Schema Name

```php
#[ZodSchema(name: 'CustomUserValidation')]
class UserRequest extends FormRequest
{
    // ...
}
```

### 2. Generate Schemas

Run the generation command:

```bash
php artisan zod:generate
```

This will scan your configured paths and generate a TypeScript file with Zod schemas.

### 3. Use in TypeScript

The generated TypeScript file will contain:

```typescript
import { z } from 'zod';

export const CreateUserSchema = z.object({
    name: z.string().trim().min(1, 'Please enter your name').max(255),
    email: z.email('Please enter a valid email address').max(255),
    password: z.string().min(8),
    password_confirmation: z.string(),
    age: z.number().min(18).max(120).nullable(),
    website: z.string().url().nullable(),
    tags: z.array(z.string().regex(/^[a-z]+$/, 'Tags must be lowercase letters only')),
});
export type CreateUserSchemaType = z.infer<typeof CreateUserSchema>;

export const UserSchema = z.object({
    name: z.string().max(255),
    email: z.email(),
    age: z.number().min(18).max(120).nullable(),
    tags: z.array(TagSchema),
});
export type UserSchemaType = z.infer<typeof UserSchema>;
```

Use in your React/Vue/Angular components:

```typescript
import { CreateUserSchema } from '@/types/zod-schemas';

// Validate form data
const result = CreateUserSchema.safeParse(formData);

if (result.success) {
    // Data is valid
    await api.createUser(result.data);
} else {
    // Handle validation errors
    console.error(result.error.errors);
}
```

## Advanced Features

### Inherit Validation From Other Classes

Reuse validation rules from other classes:

```php
class PostalCodeData extends Data
{
    public function __construct(
        #[StringType, Regex('/^\d{5}(-\d{4})?$/')]
        public string $code
    ) {}

    public static function messages(): array
    {
        return [
            'code.regex' => 'Invalid postal code format',
        ];
    }
}

class AddressData extends Data
{
    public function __construct(
        #[InheritValidationFrom(PostalCodeData::class, 'code')]
        public string $postal_code
    ) {}
}
```

### Automatic Integration with TypeScript Transformer

If you have `spatie/laravel-typescript-transformer` installed, this package can automatically run after the `typescript:transform` command:

```bash
php artisan typescript:transform
# Automatically runs zod:generate after completion
```

To disable this behavior, set in your config:

```php
'hooks' => [
    'typescript_transformer' => false,
],
```

## Advanced Customization

### Custom Extractors

Custom extractors allow you to integrate the package with your own validation patterns, legacy codebases, or unique architectural approaches. They're perfect for handling validation classes that don't follow Laravel's standard FormRequest pattern.

#### When to Use Custom Extractors

- **Legacy codebases** with non-standard validation approaches
- **Custom validation classes** that don't extend FormRequest
- **Third-party validation libraries** you want to integrate
- **Domain-specific validation patterns** (financial, healthcare, etc.)
- **API validation classes** with unique structures
- **Multi-tenant applications** with tenant-specific validation rules

#### Creating a Custom Extractor

First, create an extractor class that implements `ExtractorInterface`:

```php
<?php

namespace App\ZodExtractors;

use ReflectionClass;
use RomegaSoftware\LaravelZodGenerator\Extractors\ExtractorInterface;

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
                'validations' => $this->parseValidationRules($validationRules, $messages[$field] ?? []),
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

#### Real-World Example: Financial Validation

Here's a practical example for financial applications:

```php
<?php

namespace App\ZodExtractors;

use ReflectionClass;
use RomegaSoftware\LaravelZodGenerator\Extractors\ExtractorInterface;

class FinancialValidatorExtractor implements ExtractorInterface
{
    public function canHandle(ReflectionClass $class): bool
    {
        // Handle financial validation classes
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

#### Registering Custom Extractors

Register your extractors in the config file:

```php
// config/laravel-zod-generator.php
'custom_extractors' => [
    \App\ZodExtractors\ApiValidatorExtractor::class,
    \App\ZodExtractors\FinancialValidatorExtractor::class,
    \App\ZodExtractors\LegacyValidatorExtractor::class,
],
```

#### Priority System

Extractors are processed in priority order (higher numbers first):

- **500+**: Critical/override extractors
- **300-499**: Domain-specific extractors (financial, healthcare, etc.)
- **200-299**: Custom/third-party extractors
- **100-199**: Built-in extractors (FormRequest, Data classes)
- **<100**: Fallback extractors

#### Best Practices for Custom Extractors

1. **Use descriptive priorities**: Match your priority to your use case
2. **Handle errors gracefully**: Always check if methods exist before calling them
3. **Cache expensive operations**: Store reflection results if extracting many classes
4. **Follow naming conventions**: End schema names with "Schema"
5. **Validate extracted data**: Ensure properties have all required fields
6. **Document your extractors**: Include comments about what patterns they handle

### Custom Type Handlers

Custom type handlers give you fine-grained control over how different data types are converted to Zod schemas. They're perfect for adding support for new types, overriding default behavior, or implementing domain-specific validation logic.

#### When to Use Custom Type Handlers

- **Override default behavior**: Change how built-in types (string, number, etc.) are handled
- **Add support for new types**: Handle custom PHP types not supported out of the box
- **Domain-specific validation**: Implement specialized validation for your business logic
- **Performance optimization**: Optimize validation for frequently-used types
- **Integration with validation libraries**: Connect with custom validation systems
- **Enhanced error messages**: Provide more specific error messages for your use case

#### Creating a Custom Type Handler

Type handlers must implement the `TypeHandlerInterface`:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelZodGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodStringBuilder;

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
     * This allows checking validation rules, not just the type
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

#### Real-World Example: Currency Handler

Here's a practical example for handling monetary values:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelZodGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodNumberBuilder;

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

#### Validation-Rule Based Handler

Create handlers that trigger based on specific validation rules:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelZodGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodStringBuilder;

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

#### Registering Custom Type Handlers

Register your handlers in the config file:

```php
// config/laravel-zod-generator.php
'custom_type_handlers' => [
    \App\ZodTypeHandlers\DateTimeTypeHandler::class,
    \App\ZodTypeHandlers\CurrencyTypeHandler::class,
    \App\ZodTypeHandlers\UuidTypeHandler::class,
],
```

#### Priority System for Type Handlers

Type handlers are processed in priority order (higher numbers first):

- **1000+**: Critical overrides (complete replacement of default behavior)
- **500-999**: High-priority custom handlers (UUID, specialized validation)
- **300-499**: Domain-specific handlers (currency, datetime, etc.)
- **200-299**: Feature-specific handlers (email validation, data classes)
- **100-199**: Basic type handlers (string, number, boolean, array)
- **0-99**: Fallback handlers

#### Integration with Laravel's Container

Type handlers are resolved through Laravel's container, so you can inject dependencies:

```php
<?php

namespace App\ZodTypeHandlers;

use App\Services\ValidationRuleService;
use RomegaSoftware\LaravelZodGenerator\TypeHandlers\TypeHandlerInterface;

class DatabaseValidatedTypeHandler implements TypeHandlerInterface
{
    public function __construct(
        private ValidationRuleService $validationService
    ) {}

    public function canHandleProperty(array $property): bool
    {
        // Use injected service to determine if we can handle this property
        return $this->validationService->hasCustomRules($property['name']);
    }

    public function handle(array $property): ZodBuilder
    {
        // Use injected service to get validation rules
        $customRules = $this->validationService->getRules($property['name']);
        
        // Build Zod schema based on custom rules
        // ... implementation
    }

    // ... other methods
}
```

#### Best Practices for Custom Type Handlers

1. **Use appropriate priorities**: Set priorities that reflect when your handler should be checked
2. **Handle edge cases**: Always account for nullable and optional fields
3. **Provide clear error messages**: Use descriptive validation messages
4. **Leverage the builder pattern**: Use existing ZodBuilders when possible
5. **Test thoroughly**: Write tests for all validation scenarios your handler supports
6. **Document complex logic**: Comment any non-obvious validation rules
7. **Consider performance**: Cache expensive operations if handling many properties

### Troubleshooting and Error Handling

#### Common Issues and Solutions

##### Custom Extractor Not Being Used

**Problem**: Your custom extractor is registered but not being called.

**Solutions**:
1. Check the priority - higher priorities are checked first
2. Ensure `canHandle()` method returns true for your target classes
3. Verify the extractor is properly registered in config
4. Make sure the target class has the `#[ZodSchema]` attribute

```php
// Debug which extractor is being used
$manager = app(\RomegaSoftware\LaravelZodGenerator\Extractors\ExtractorManager::class);
$extractor = $manager->findExtractor(new ReflectionClass(YourClass::class));
dd(get_class($extractor)); // Shows which extractor was selected
```

##### Custom Type Handler Not Working

**Problem**: Your custom type handler isn't overriding the default behavior.

**Solutions**:
1. Ensure priority is higher than competing handlers
2. Check both `canHandle()` and `canHandleProperty()` methods
3. Verify handler registration in config
4. Test with a simple example first

```php
// Debug type handler selection
$generator = app(\RomegaSoftware\LaravelZodGenerator\Generators\ZodSchemaGenerator::class);
$registry = $generator->getTypeHandlerRegistry();

$property = ['name' => 'test', 'type' => 'string', 'validations' => []];
$handler = $registry->getHandlerForProperty($property);
dd(get_class($handler)); // Shows which handler was selected
```

##### Generated Schema Not Working

**Problem**: The generated Zod schema fails validation in TypeScript.

**Solutions**:
1. Check the generated TypeScript file for syntax errors
2. Verify Zod patterns match your data format
3. Test with simple data first, then add complexity
4. Check browser console for specific validation errors

```typescript
// Debug Zod validation
const result = YourSchema.safeParse(testData);
if (!result.success) {
    console.error('Validation errors:', result.error.issues);
}
```

##### Class Not Found Errors

**Problem**: Laravel can't find your custom extractor/handler classes.

**Solutions**:
1. Check namespace and class name spelling
2. Ensure proper PSR-4 autoloading
3. Run `composer dump-autoload` if needed
4. Verify file location matches namespace

##### Dependency Injection Issues

**Problem**: Custom handlers with constructor dependencies fail to resolve.

**Solutions**:
1. Ensure dependencies are bound in Laravel's container
2. Check service provider registration
3. Use manual registration if automatic resolution fails

```php
// Manual registration in service provider
$this->app->bind(CustomTypeHandler::class, function ($app) {
    return new CustomTypeHandler($app->make(SomeService::class));
});
```

#### Performance Considerations

##### For Large Codebases

1. **Limit scan paths**: Only scan directories that contain validation classes
2. **Use caching**: Implement caching in custom extractors for expensive operations
3. **Optimize reflection**: Cache reflection results when processing many classes
4. **Profile execution**: Use Laravel's profiling tools to identify bottlenecks

```php
// Example caching in custom extractor
private static $reflectionCache = [];

public function extract(ReflectionClass $class): array
{
    $className = $class->getName();
    
    if (!isset(self::$reflectionCache[$className])) {
        self::$reflectionCache[$className] = $this->performExpensiveExtraction($class);
    }
    
    return self::$reflectionCache[$className];
}
```

##### Memory Usage

For very large codebases, consider:
1. Processing classes in batches
2. Clearing processed data periodically
3. Using generators instead of loading all data at once

#### Testing Custom Components

##### Testing Custom Extractors

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use App\ZodExtractors\CustomExtractor;

class CustomExtractorTest extends TestCase
{
    public function test_can_handle_target_classes(): void
    {
        $extractor = new CustomExtractor();
        $class = new ReflectionClass(TargetClass::class);
        
        $this->assertTrue($extractor->canHandle($class));
    }

    public function test_extracts_expected_data(): void
    {
        $extractor = new CustomExtractor();
        $class = new ReflectionClass(TargetClass::class);
        
        $result = $extractor->extract($class);
        
        $this->assertEquals('ExpectedSchema', $result['name']);
        $this->assertCount(2, $result['properties']);
    }
}
```

##### Testing Custom Type Handlers

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\ZodTypeHandlers\CustomTypeHandler;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodStringBuilder;

class CustomTypeHandlerTest extends TestCase
{
    public function test_handles_expected_types(): void
    {
        $handler = new CustomTypeHandler();
        
        $this->assertTrue($handler->canHandle('custom_type'));
        $this->assertFalse($handler->canHandle('string'));
    }

    public function test_generates_expected_zod_schema(): void
    {
        $handler = new CustomTypeHandler();
        
        $property = [
            'name' => 'test_field',
            'type' => 'custom_type',
            'validations' => ['required' => true],
        ];
        
        $builder = $handler->handle($property);
        $zodSchema = $builder->build();
        
        $this->assertStringContainsString('z.string()', $zodSchema);
        $this->assertStringContainsString('required', $zodSchema);
    }
}
```

## Supported Validation Rules

The package supports the following Laravel validation rules:

| Laravel Rule        | Zod Schema                                 |
| ------------------- | ------------------------------------------ |
| `required`          | `.min(1)` for strings, required by default |
| `nullable`          | `.nullable()`                              |
| `string`            | `z.string()`                               |
| `email`             | `z.email()`                                |
| `numeric`/`integer` | `z.number()`                               |
| `boolean`           | `z.boolean()`                              |
| `array`             | `z.array()`                                |
| `min:X`             | `.min(X)`                                  |
| `max:X`             | `.max(X)`                                  |
| `regex:/pattern/`   | `.regex(/pattern/)`                        |
| `url`               | `.url()`                                   |
| `uuid`              | `.uuid()`                                  |
| `in:a,b,c`          | `z.enum(['a', 'b', 'c'])`                  |
| `confirmed`         | Handled in frontend                        |
| `unique`            | Not validated in frontend                  |

## Package Detection

The package automatically detects installed packages and enables features accordingly:

```bash
php artisan zod:generate

# Output:
📦 Available Features:
  ✓ Spatie Data class support enabled
  ✓ TypeScript Transformer integration available
  ✓ Automatic hook into typescript:transform command available
  ✓ Laravel FormRequest support enabled
```

## Testing

Run the test suite:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email support@romegasoftware.com instead of using the issue tracker.

## Credits

- [Romega Software](https://romegasoftware.com/)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
