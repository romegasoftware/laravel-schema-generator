---
sidebar_position: 3
---

# Custom Validation Examples

Advanced examples using custom extractors, type handlers, and validation patterns with Laravel Zod Generator. These examples show how to extend the package for specialized use cases.

## Custom Extractors

### Legacy System Integration

Integrate with an existing legacy validation system:

```php
<?php

namespace App\ZodExtractors;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorInterface;

class LegacyValidatorExtractor implements ExtractorInterface
{
    public function canHandle(ReflectionClass $class): bool
    {
        // Handle classes from legacy validation system
        return str_starts_with($class->getName(), 'App\\Legacy\\Validators\\') ||
               $class->hasMethod('getLegacyRules');
    }

    public function extract(ReflectionClass $class): array
    {
        $instance = $class->newInstance();

        // Legacy system uses different method names
        if ($class->hasMethod('getLegacyRules')) {
            $legacyRules = $instance->getLegacyRules();
            $legacyMessages = $instance->getLegacyMessages() ?? [];
        } else {
            // Fallback to property-based rules
            $legacyRules = $instance->validationRules ?? [];
            $legacyMessages = $instance->validationMessages ?? [];
        }

        return [
            'name' => $this->convertLegacyName($class->getShortName()),
            'properties' => $this->convertLegacyProperties($legacyRules, $legacyMessages),
        ];
    }

    public function getPriority(): int
    {
        return 200; // Higher than default extractors
    }

    private function convertLegacyName(string $className): string
    {
        // Convert LegacyUserValidator -> UserValidationSchema
        $name = str_replace(['Legacy', 'Validator'], ['', ''], $className);
        return $name . 'ValidationSchema';
    }

    private function convertLegacyProperties(array $legacyRules, array $legacyMessages): array
    {
        $properties = [];

        foreach ($legacyRules as $field => $rule) {
            $properties[] = [
                'name' => $field,
                'type' => $this->mapLegacyType($rule),
                'isOptional' => !($rule['required'] ?? false),
                'validations' => $this->mapLegacyValidations($rule, $legacyMessages[$field] ?? []),
            ];
        }

        return $properties;
    }

    private function mapLegacyType(array $rule): string
    {
        return match($rule['type'] ?? 'string') {
            'int', 'integer', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            'array', 'list' => 'array',
            'email' => 'string', // Will be handled by email validation
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

        if ($rule['type'] === 'email') {
            $validations['email'] = true;
        }

        if (isset($rule['pattern'])) {
            $validations['regex'] = $rule['pattern'];
        }

        if (isset($rule['options'])) {
            $validations['enum'] = $rule['options'];
        }

        if (!empty($messages)) {
            $validations['customMessages'] = $messages;
        }

        return $validations;
    }
}
```

**Usage Example:**

```php
<?php

namespace App\Legacy\Validators;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class LegacyUserValidator
{
    public function getLegacyRules(): array
    {
        return [
            'username' => [
                'type' => 'string',
                'required' => true,
                'min_length' => 3,
                'max_length' => 50,
                'pattern' => '^[a-zA-Z0-9_-]+$',
            ],
            'email' => [
                'type' => 'email',
                'required' => true,
                'max_length' => 255,
            ],
            'age' => [
                'type' => 'integer',
                'required' => false,
                'min_value' => 18,
                'max_value' => 120,
            ],
            'role' => [
                'type' => 'string',
                'required' => true,
                'options' => ['admin', 'user', 'moderator'],
            ],
        ];
    }

    public function getLegacyMessages(): array
    {
        return [
            'username' => [
                'pattern' => 'Username can only contain letters, numbers, dashes, and underscores',
                'required' => 'Username is required',
            ],
            'email' => [
                'required' => 'Email address is required',
            ],
        ];
    }
}
```

### Database-Driven Validation

Extract validation rules from database configuration:

```php
<?php

namespace App\ZodExtractors;

use App\Models\ValidationRule;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorInterface;

class DatabaseValidationExtractor implements ExtractorInterface
{
    public function canHandle(ReflectionClass $class): bool
    {
        // Handle classes that use database-driven validation
        return $class->hasMethod('getValidationEntity') ||
               str_contains($class->getName(), 'DatabaseValidator');
    }

    public function extract(ReflectionClass $class): array
    {
        $instance = $class->newInstance();
        $entityName = $instance->getValidationEntity();

        // Fetch rules from database
        $dbRules = ValidationRule::where('entity', $entityName)
                                ->where('active', true)
                                ->orderBy('priority')
                                ->get();

        return [
            'name' => $this->getSchemaName($class),
            'properties' => $this->processDbRules($dbRules),
        ];
    }

    public function getPriority(): int
    {
        return 250; // High priority for database rules
    }

    private function getSchemaName(ReflectionClass $class): string
    {
        return str_replace('DatabaseValidator', 'Schema', $class->getShortName());
    }

    private function processDbRules($dbRules): array
    {
        $properties = [];
        $groupedRules = $dbRules->groupBy('field_name');

        foreach ($groupedRules as $fieldName => $rules) {
            $fieldType = $rules->first()->field_type;
            $validations = [];

            foreach ($rules as $rule) {
                switch ($rule->rule_type) {
                    case 'required':
                        $validations['required'] = true;
                        break;
                    case 'min_length':
                        $validations['min'] = (int) $rule->rule_value;
                        break;
                    case 'max_length':
                        $validations['max'] = (int) $rule->rule_value;
                        break;
                    case 'email':
                        $validations['email'] = true;
                        break;
                    case 'regex':
                        $validations['regex'] = $rule->rule_value;
                        break;
                    case 'enum':
                        $validations['enum'] = json_decode($rule->rule_value, true);
                        break;
                }

                if ($rule->error_message) {
                    $validations['customMessages'][$rule->rule_type] = $rule->error_message;
                }
            }

            $properties[] = [
                'name' => $fieldName,
                'type' => $this->mapDatabaseType($fieldType),
                'isOptional' => !isset($validations['required']),
                'validations' => $validations,
            ];
        }

        return $properties;
    }

    private function mapDatabaseType(string $dbType): string
    {
        return match($dbType) {
            'integer', 'bigint', 'decimal', 'float' => 'number',
            'boolean' => 'boolean',
            'json', 'array' => 'array',
            default => 'string',
        };
    }
}
```

## Custom Type Handlers

### Financial Data Handler

Handle monetary values with specific formatting:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodNumberBuilder;

class MonetaryTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return in_array($type, ['money', 'currency', 'price', 'amount']);
    }

    public function canHandleProperty(array $property): bool
    {
        if ($this->canHandle($property['type'])) {
            return true;
        }

        // Handle numeric fields with monetary names
        $name = $property['name'];
        return $property['type'] === 'number' && (
            str_contains($name, 'price') ||
            str_contains($name, 'amount') ||
            str_contains($name, 'cost') ||
            str_contains($name, 'fee') ||
            str_contains($name, 'balance') ||
            str_ends_with($name, '_cents')
        );
    }

    public function handle(array $property): ZodBuilder
    {
        $builder = new ZodNumberBuilder();
        $validations = $property['validations'] ?? [];
        $name = $property['name'];

        // Handle cent-based amounts
        if (str_ends_with($name, '_cents')) {
            $builder->int('Amount in cents must be a whole number');
            $builder->min(0, 'Amount cannot be negative');
        } else {
            // Handle dollar amounts
            $builder->min(0, 'Amount cannot be negative');

            // Precision for monetary values (2 decimal places)
            $builder->transform('(val) => Math.round(val * 100) / 100');

            // Maximum reasonable monetary value
            $builder->max(999999999.99, 'Amount exceeds maximum limit');
        }

        // Apply custom validations
        if (isset($validations['min'])) {
            $min = $validations['min'];
            $message = str_ends_with($name, '_cents')
                ? "Minimum amount is {$min} cents"
                : "Minimum amount is $" . number_format($min, 2);
            $builder->min($min, $message);
        }

        if (isset($validations['max'])) {
            $max = $validations['max'];
            $message = str_ends_with($name, '_cents')
                ? "Maximum amount is {$max} cents"
                : "Maximum amount is $" . number_format($max, 2);
            $builder->max($max, $message);
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
        return 300;
    }
}
```

### Geographic Coordinate Handler

Handle latitude/longitude coordinates:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodNumberBuilder;

class CoordinateTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return in_array($type, ['coordinate', 'latitude', 'longitude']);
    }

    public function canHandleProperty(array $property): bool
    {
        if ($this->canHandle($property['type'])) {
            return true;
        }

        $name = strtolower($property['name']);
        return $property['type'] === 'number' && (
            str_contains($name, 'lat') ||
            str_contains($name, 'lng') ||
            str_contains($name, 'lon') ||
            str_contains($name, 'longitude') ||
            str_contains($name, 'latitude') ||
            in_array($name, ['x', 'y']) // Common coordinate names
        );
    }

    public function handle(array $property): ZodBuilder
    {
        $builder = new ZodNumberBuilder();
        $name = strtolower($property['name']);
        $validations = $property['validations'] ?? [];

        // Determine coordinate type and set appropriate bounds
        if (str_contains($name, 'lat') || $property['type'] === 'latitude') {
            // Latitude: -90 to 90
            $builder->min(-90, 'Latitude must be between -90 and 90 degrees');
            $builder->max(90, 'Latitude must be between -90 and 90 degrees');
        } elseif (str_contains($name, 'lng') || str_contains($name, 'lon') || $property['type'] === 'longitude') {
            // Longitude: -180 to 180
            $builder->min(-180, 'Longitude must be between -180 and 180 degrees');
            $builder->max(180, 'Longitude must be between -180 and 180 degrees');
        } else {
            // Generic coordinate - use longitude bounds as default
            $builder->min(-180, 'Coordinate must be within valid range');
            $builder->max(180, 'Coordinate must be within valid range');
        }

        // Precision for coordinates (typically 6-8 decimal places)
        $builder->transform('(val) => Math.round(val * 1000000) / 1000000'); // 6 decimal places

        // Apply custom validations if they exist
        if (isset($validations['min'])) {
            $builder->min($validations['min']);
        }
        if (isset($validations['max'])) {
            $builder->max($validations['max']);
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
        return 350; // High priority to catch coordinates before generic number handler
    }
}
```

### Advanced String Validation Handler

Handle complex string patterns and formats:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;

class AdvancedStringTypeHandler implements TypeHandlerInterface
{
    private array $patterns = [
        'ssn' => [
            'regex' => '/^\d{3}-\d{2}-\d{4}$/',
            'message' => 'Social Security Number must be in format: 123-45-6789'
        ],
        'ein' => [
            'regex' => '/^\d{2}-\d{7}$/',
            'message' => 'Employer Identification Number must be in format: 12-3456789'
        ],
        'credit_card' => [
            'regex' => '/^\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}$/',
            'message' => 'Invalid credit card number format'
        ],
        'isbn' => [
            'regex' => '/^(?:ISBN(?:-1[03])?:? )?(?=[0-9X]{10}$|(?=(?:[0-9]+[- ]){3})[- 0-9X]{13}$|97[89][0-9]{10}$|(?=(?:[0-9]+[- ]){4})[- 0-9]{17}$)(?:97[89][- ]?)?[0-9]{1,5}[- ]?[0-9]+[- ]?[0-9]+[- ]?[0-9X]$/',
            'message' => 'Invalid ISBN format'
        ],
        'mac_address' => [
            'regex' => '/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
            'message' => 'MAC address must be in format: XX:XX:XX:XX:XX:XX or XX-XX-XX-XX-XX-XX'
        ],
        'hex_color' => [
            'regex' => '/^#[0-9A-Fa-f]{6}$/',
            'message' => 'Color must be a valid hex color code (e.g., #FF5733)'
        ],
        'semantic_version' => [
            'regex' => '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/',
            'message' => 'Version must follow semantic versioning (e.g., 1.2.3)'
        ],
        'jwt_token' => [
            'regex' => '/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]*$/',
            'message' => 'Invalid JWT token format'
        ],
        'base64' => [
            'regex' => '/^[A-Za-z0-9+\/]*={0,2}$/',
            'message' => 'Invalid Base64 encoding'
        ],
    ];

    public function canHandle(string $type): bool
    {
        return array_key_exists($type, $this->patterns);
    }

    public function canHandleProperty(array $property): bool
    {
        if ($this->canHandle($property['type'])) {
            return true;
        }

        // Check if field name matches known patterns
        $name = strtolower($property['name']);
        foreach ($this->patterns as $pattern => $config) {
            if (str_contains($name, $pattern) || str_ends_with($name, '_' . $pattern)) {
                return $property['type'] === 'string';
            }
        }

        // Check validation rules for pattern hints
        $validations = $property['validations'] ?? [];
        if (isset($validations['regex'])) {
            $regex = $validations['regex'];
            foreach ($this->patterns as $pattern => $config) {
                if ($regex === $config['regex']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function handle(array $property): ZodBuilder
    {
        $builder = new ZodStringBuilder();
        $validations = $property['validations'] ?? [];

        // Determine which pattern to use
        $patternType = $this->detectPatternType($property);

        if ($patternType && isset($this->patterns[$patternType])) {
            $pattern = $this->patterns[$patternType];
            $builder->regex($pattern['regex'], $pattern['message']);
        }

        // Apply additional validations
        if (isset($validations['min'])) {
            $builder->min($validations['min']);
        }
        if (isset($validations['max'])) {
            $builder->max($validations['max']);
        }

        // Handle case sensitivity for certain types
        if (in_array($patternType, ['hex_color', 'mac_address'])) {
            // These are case-insensitive, transform to uppercase
            $builder->transform('(val) => val.toUpperCase()');
        }

        // Handle nullable/optional
        if (isset($validations['nullable'])) {
            $builder->nullable();
        }

        $isOptional = $property['isOptional'] ?? false;
        if ($isOptional && !isset($validations['required'])) {
            $builder->optional();
        }

        // Add custom messages if provided
        if (isset($validations['customMessages'])) {
            foreach ($validations['customMessages'] as $rule => $message) {
                $builder->withMessage($rule, $message);
            }
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 250; // Higher than basic string handler
    }

    private function detectPatternType(array $property): ?string
    {
        // Check explicit type first
        if ($this->canHandle($property['type'])) {
            return $property['type'];
        }

        // Check field name patterns
        $name = strtolower($property['name']);
        foreach ($this->patterns as $pattern => $config) {
            if (str_contains($name, $pattern) || str_ends_with($name, '_' . $pattern)) {
                return $pattern;
            }
        }

        // Check regex validation
        $validations = $property['validations'] ?? [];
        if (isset($validations['regex'])) {
            $regex = $validations['regex'];
            foreach ($this->patterns as $pattern => $config) {
                if ($regex === $config['regex']) {
                    return $pattern;
                }
            }
        }

        return null;
    }
}
```

## Multi-Tenant Validation

Handle tenant-specific validation rules:

```php
<?php

namespace App\ZodExtractors;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorInterface;

class TenantAwareExtractor implements ExtractorInterface
{
    public function canHandle(ReflectionClass $class): bool
    {
        return $class->implementsInterface(TenantValidationInterface::class) ||
               str_contains($class->getName(), 'TenantValidator');
    }

    public function extract(ReflectionClass $class): array
    {
        $instance = $class->newInstance();
        $tenant = $this->getCurrentTenant();

        // Get tenant-specific rules
        $rules = $instance->getRulesForTenant($tenant);
        $messages = $instance->getMessagesForTenant($tenant);

        return [
            'name' => $this->getTenantSchemaName($class, $tenant),
            'properties' => $this->processTenantRules($rules, $messages, $tenant),
        ];
    }

    public function getPriority(): int
    {
        return 300; // High priority for tenant-specific validation
    }

    private function getCurrentTenant(): string
    {
        // Get current tenant from your multi-tenancy system
        return app('tenant')?->slug ?? config('app.default_tenant', 'default');
    }

    private function getTenantSchemaName(ReflectionClass $class, string $tenant): string
    {
        $baseName = str_replace('TenantValidator', 'Schema', $class->getShortName());
        return $baseName . ucfirst($tenant);
    }

    private function processTenantRules(array $rules, array $messages, string $tenant): array
    {
        $properties = [];

        foreach ($rules as $field => $validationRules) {
            $properties[] = [
                'name' => $field,
                'type' => $this->inferType($validationRules),
                'isOptional' => $this->isOptional($validationRules),
                'validations' => $this->parseTenantValidations($validationRules, $messages[$field] ?? [], $tenant),
            ];
        }

        return $properties;
    }

    private function parseTenantValidations(string $rules, array $messages, string $tenant): array
    {
        $validations = $this->parseBasicValidations($rules);

        // Add tenant-specific context
        $validations['tenant_context'] = $tenant;

        // Add tenant-specific messages
        if (!empty($messages)) {
            $validations['customMessages'] = $messages;
        }

        return $validations;
    }

    private function parseBasicValidations(string $rules): array
    {
        $validations = [];
        $rulesList = explode('|', $rules);

        foreach ($rulesList as $rule) {
            if ($rule === 'required') {
                $validations['required'] = true;
            } elseif (str_starts_with($rule, 'min:')) {
                $validations['min'] = (int) substr($rule, 4);
            } elseif (str_starts_with($rule, 'max:')) {
                $validations['max'] = (int) substr($rule, 4);
            } elseif ($rule === 'email') {
                $validations['email'] = true;
            } elseif (str_starts_with($rule, 'regex:')) {
                $validations['regex'] = substr($rule, 6);
            }
        }

        return $validations;
    }

    private function inferType(string $rules): string
    {
        if (str_contains($rules, 'integer') || str_contains($rules, 'numeric')) {
            return 'number';
        }
        if (str_contains($rules, 'boolean')) {
            return 'boolean';
        }
        if (str_contains($rules, 'array')) {
            return 'array';
        }
        return 'string';
    }

    private function isOptional(string $rules): bool
    {
        return !str_contains($rules, 'required');
    }
}
```

**Tenant Validation Interface:**

```php
<?php

namespace App\Contracts;

interface TenantValidationInterface
{
    public function getRulesForTenant(string $tenant): array;
    public function getMessagesForTenant(string $tenant): array;
}
```

**Tenant-Specific Validator:**

```php
<?php

namespace App\Validators;

use App\Contracts\TenantValidationInterface;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class TenantUserValidator implements TenantValidationInterface
{
    public function getRulesForTenant(string $tenant): array
    {
        $baseRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ];

        return match($tenant) {
            'enterprise' => array_merge($baseRules, [
                'employee_id' => 'required|string|max:20|unique:users,employee_id',
                'department' => 'required|in:engineering,sales,marketing,hr,finance',
                'manager_email' => 'required|email|exists:users,email',
            ]),

            'startup' => array_merge($baseRules, [
                'role' => 'required|in:founder,developer,designer,marketer',
                'equity_percentage' => 'nullable|numeric|min:0|max:100',
            ]),

            'freelancer' => array_merge($baseRules, [
                'hourly_rate' => 'required|numeric|min:1|max:500',
                'skills' => 'required|array|min:1|max:10',
                'skills.*' => 'string|max:50',
                'portfolio_url' => 'nullable|url',
            ]),

            default => $baseRules,
        };
    }

    public function getMessagesForTenant(string $tenant): array
    {
        return match($tenant) {
            'enterprise' => [
                'employee_id.unique' => 'This employee ID is already in use',
                'manager_email.exists' => 'Manager must be an existing user',
            ],

            'startup' => [
                'equity_percentage.max' => 'Equity percentage cannot exceed 100%',
            ],

            'freelancer' => [
                'hourly_rate.min' => 'Minimum hourly rate is $1',
                'skills.min' => 'Please select at least one skill',
            ],

            default => [],
        };
    }
}
```

## Advanced Conditional Validation

Handle complex business logic validation:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodObjectBuilder;

class BusinessLogicTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return $type === 'business_logic';
    }

    public function canHandleProperty(array $property): bool
    {
        $validations = $property['validations'] ?? [];
        return isset($validations['business_rule']) ||
               isset($validations['conditional_validation']);
    }

    public function handle(array $property): ZodBuilder
    {
        $validations = $property['validations'] ?? [];
        $businessRule = $validations['business_rule'] ?? null;

        if ($businessRule === 'subscription_billing') {
            return $this->handleSubscriptionBilling($property);
        }

        if ($businessRule === 'shipping_calculation') {
            return $this->handleShippingCalculation($property);
        }

        if ($businessRule === 'discount_validation') {
            return $this->handleDiscountValidation($property);
        }

        // Default handling
        return new ZodObjectBuilder();
    }

    public function getPriority(): int
    {
        return 500; // Very high priority for business logic
    }

    private function handleSubscriptionBilling(array $property): ZodBuilder
    {
        $builder = new ZodObjectBuilder();

        // Plan selection
        $builder->addProperty('plan_id',
            (new \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder())
                ->min(1, 'Plan selection is required')
        );

        // Billing cycle
        $builder->addProperty('billing_cycle',
            (new \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder())
                ->enum(['monthly', 'quarterly', 'yearly'])
        );

        // Add conditional validation via refine
        $builder->refine('
            (data) => {
                if (data.plan_id === "enterprise" && data.billing_cycle === "monthly") {
                    return false;
                }
                return true;
            }',
            'Enterprise plans require quarterly or yearly billing'
        );

        return $builder;
    }

    private function handleShippingCalculation(array $property): ZodBuilder
    {
        $builder = new ZodObjectBuilder();

        // Shipping method
        $builder->addProperty('method',
            (new \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder())
                ->enum(['standard', 'express', 'overnight'])
        );

        // Shipping address (required for calculation)
        $builder->addProperty('address',
            (new ZodObjectBuilder())
                ->addProperty('country',
                    (new \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder())
                        ->length(2)
                )
                ->addProperty('postal_code',
                    (new \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder())
                        ->regex('/^\d{5}(-\d{4})?$/')
                )
        );

        // Weight (affects shipping cost)
        $builder->addProperty('total_weight',
            (new \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodNumberBuilder())
                ->min(0.1, 'Package must have minimum weight')
                ->max(150, 'Package exceeds maximum weight limit')
        );

        // Business rule: Express shipping not available for certain countries
        $builder->refine('
            (data) => {
                const restrictedCountries = ["AF", "IQ", "LY", "SO", "SY"];
                if (data.method === "express" && restrictedCountries.includes(data.address.country)) {
                    return false;
                }
                return true;
            }',
            'Express shipping is not available for this destination'
        );

        return $builder;
    }

    private function handleDiscountValidation(array $property): ZodBuilder
    {
        $builder = new ZodObjectBuilder();

        // Discount type
        $builder->addProperty('type',
            (new \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder())
                ->enum(['percentage', 'fixed_amount', 'buy_x_get_y'])
        );

        // Discount value
        $builder->addProperty('value',
            (new \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodNumberBuilder())
                ->min(0, 'Discount value must be positive')
        );

        // Minimum order amount
        $builder->addProperty('minimum_order_amount',
            (new \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodNumberBuilder())
                ->min(0)
                ->optional()
        );

        // Maximum discount amount (for percentage discounts)
        $builder->addProperty('maximum_discount_amount',
            (new \RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodNumberBuilder())
                ->min(0)
                ->optional()
        );

        // Complex business rules
        $builder->refine('
            (data) => {
                // Percentage discounts cannot exceed 100%
                if (data.type === "percentage" && data.value > 100) {
                    return false;
                }

                // Fixed amount discounts must have a maximum
                if (data.type === "fixed_amount" && data.value > 1000) {
                    return false;
                }

                // Buy X Get Y discounts have different validation
                if (data.type === "buy_x_get_y") {
                    return data.value >= 1 && data.value <= 10;
                }

                return true;
            }',
            'Invalid discount configuration'
        );

        return $builder;
    }
}
```

## Generated Schema Usage

### Complex Form Validation

```typescript
import { z } from 'zod';
import {
  TenantUserEnterpriseSchema,
  BusinessLogicSchema,
  MonetarySchema
} from '@/types/zod-schemas';

// Enterprise user registration with complex validation
const EnterpriseRegistrationForm: React.FC = () => {
  const {
    register,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm<z.infer<typeof TenantUserEnterpriseSchema>>({
    resolver: zodResolver(TenantUserEnterpriseSchema),
  });

  const selectedDepartment = watch('department');

  const onSubmit = async (data: z.infer<typeof TenantUserEnterpriseSchema>) => {
    try {
      // Additional client-side business logic validation
      if (selectedDepartment === 'finance' && !data.manager_email?.endsWith('@finance.company.com')) {
        throw new Error('Finance department users must have a finance manager');
      }

      const response = await fetch('/api/enterprise/users', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        throw new Error('Registration failed');
      }

      // Handle success
      router.push('/dashboard');
    } catch (error) {
      console.error('Registration error:', error);
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      <div>
        <input
          {...register('name')}
          placeholder="Full Name"
          className="w-full px-3 py-2 border rounded"
        />
        {errors.name && <span className="text-red-500">{errors.name.message}</span>}
      </div>

      <div>
        <input
          {...register('employee_id')}
          placeholder="Employee ID"
          className="w-full px-3 py-2 border rounded"
        />
        {errors.employee_id && <span className="text-red-500">{errors.employee_id.message}</span>}
      </div>

      <div>
        <select {...register('department')} className="w-full px-3 py-2 border rounded">
          <option value="">Select Department</option>
          <option value="engineering">Engineering</option>
          <option value="sales">Sales</option>
          <option value="marketing">Marketing</option>
          <option value="hr">Human Resources</option>
          <option value="finance">Finance</option>
        </select>
        {errors.department && <span className="text-red-500">{errors.department.message}</span>}
      </div>

      <div>
        <input
          {...register('manager_email')}
          type="email"
          placeholder="Manager Email"
          className="w-full px-3 py-2 border rounded"
        />
        {errors.manager_email && <span className="text-red-500">{errors.manager_email.message}</span>}
      </div>

      <button
        type="submit"
        className="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700"
      >
        Register Employee
      </button>
    </form>
  );
};
```

### API Integration with Complex Schemas

```typescript
import { z } from "zod";
import { BusinessLogicSchema } from "@/types/zod-schemas";

class SubscriptionService {
  async createSubscription(data: unknown): Promise<void> {
    // Validate complex business logic
    const result = BusinessLogicSchema.safeParse(data);

    if (!result.success) {
      const errors = result.error.issues.map((issue) => ({
        field: issue.path.join("."),
        message: issue.message,
        code: issue.code,
      }));

      throw new ValidationError("Subscription validation failed", errors);
    }

    // Additional runtime business rule checks
    await this.validateBusinessRules(result.data);

    // Create subscription
    await fetch("/api/subscriptions", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(result.data),
    });
  }

  private async validateBusinessRules(data: any): Promise<void> {
    // Check if plan is available
    const plan = await this.getPlan(data.plan_id);
    if (!plan.available) {
      throw new Error("Selected plan is not currently available");
    }

    // Check enterprise plan billing cycle restriction
    if (plan.type === "enterprise" && data.billing_cycle === "monthly") {
      throw new Error("Enterprise plans require quarterly or yearly billing");
    }

    // Validate pricing tiers
    if (
      plan.minimum_seats &&
      (!data.seats || data.seats < plan.minimum_seats)
    ) {
      throw new Error(
        `Minimum ${plan.minimum_seats} seats required for this plan`
      );
    }
  }

  private async getPlan(planId: string): Promise<any> {
    const response = await fetch(`/api/plans/${planId}`);
    return response.json();
  }
}
```

## Best Practices

### Prioritize Extractors Appropriately

```php
class ExtractorPriorities
{
    const BUSINESS_CRITICAL = 1000;    // Critical business logic
    const TENANT_SPECIFIC = 500;       // Multi-tenant rules
    const DOMAIN_SPECIFIC = 300;       // Financial, geographic, etc.
    const CUSTOM_INTEGRATION = 200;    // Legacy systems, APIs
    const ENHANCED_DEFAULT = 150;      // Enhanced built-in behavior
    const DEFAULT = 100;               // Standard extractors
}
```

### Document Complex Validation Logic

```php
/**
 * Handles subscription billing validation with enterprise restrictions.
 *
 * Business Rules:
 * - Enterprise plans cannot use monthly billing
 * - Minimum seat requirements must be enforced
 * - Pricing tiers affect available features
 *
 * @priority 500 - High priority for business-critical validation
 */
class SubscriptionValidationHandler implements TypeHandlerInterface
{
    // Implementation...
}
```

### Test Custom Components Thoroughly

```php
<?php

namespace Tests\Unit\ZodExtractors;

use PHPUnit\Framework\TestCase;
use App\ZodExtractors\TenantAwareExtractor;

class TenantAwareExtractorTest extends TestCase
{
    /** @test */
    public function it_generates_different_schemas_per_tenant(): void
    {
        $extractor = new TenantAwareExtractor();

        // Mock different tenant contexts
        app()->instance('tenant', (object) ['slug' => 'enterprise']);
        $enterpriseSchema = $extractor->extract(new ReflectionClass(TenantUserValidator::class));

        app()->instance('tenant', (object) ['slug' => 'freelancer']);
        $freelancerSchema = $extractor->extract(new ReflectionClass(TenantUserValidator::class));

        $this->assertNotEquals($enterpriseSchema, $freelancerSchema);
        $this->assertStringContains('Enterprise', $enterpriseSchema['name']);
        $this->assertStringContains('Freelancer', $freelancerSchema['name']);
    }
}
```

### Handle Errors Gracefully

```php
public function extract(ReflectionClass $class): array
{
    try {
        $instance = $class->newInstance();
        $tenant = $this->getCurrentTenant();

        return [
            'name' => $this->getSchemaName($class, $tenant),
            'properties' => $this->processRules($instance, $tenant),
        ];
    } catch (TenantException $e) {
        // Fallback to default validation
        logger()->warning("Tenant validation failed, using defaults", [
            'class' => $class->getName(),
            'error' => $e->getMessage(),
        ]);

        return $this->getDefaultSchema($class);
    } catch (Exception $e) {
        // Re-throw with context
        throw new ExtractionException(
            "Failed to extract validation from {$class->getName()}: {$e->getMessage()}",
            0,
            $e
        );
    }
}
```

### Performance Considerations

```php
class CachedCustomExtractor implements ExtractorInterface
{
    private static array $cache = [];

    public function extract(ReflectionClass $class): array
    {
        $cacheKey = $class->getName() . ':' . $this->getCurrentTenant();

        if (!isset(self::$cache[$cacheKey])) {
            self::$cache[$cacheKey] = $this->performExtraction($class);
        }

        return self::$cache[$cacheKey];
    }
}
```

## Next Steps

- [Real-world Examples](./real-world.md) - Complete application examples
- [Custom Extractors](../advanced/custom-extractors.md) - Deep dive into extractors
- [Custom Type Handlers](../advanced/custom-type-handlers.md) - Advanced type handling
- [Integration](../advanced/integration.md) - CI/CD and build integration
