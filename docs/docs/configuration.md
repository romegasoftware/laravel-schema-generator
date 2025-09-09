---
sidebar_position: 3
---

# Configuration

Laravel Zod Generator works out of the box with sensible defaults, but you can customize its behavior to fit your project's needs.

## Publishing Configuration

To customize the configuration, publish the config file:

```bash
php artisan vendor:publish --tag=laravel-schema-generator-config
```

This creates `config/laravel-schema-generator.php` with all available options.

:::note
If you're using Spatie Data classes, the package automatically provides sensible defaults without requiring you to publish their configuration. Your existing Spatie Data configuration will be respected if already present.
:::

## Configuration Options

### Scan Paths

Specify which directories to scan for classes with the `#[ValidationSchema]` attribute:

```php
'scan_paths' => [
    app_path(),
    app_path('Http/Requests'),
    app_path('Data'),
],
```

**Default**: `[app_path()]`

**Tips**:

- Include specific directories for better performance
- Use absolute paths or Laravel helper functions
- Multiple paths are supported

### Output Configuration

Configure where and how the generated TypeScript file is created:

```php
'output' => [
    'path' => resource_path('js/types/zod-schemas.ts'),
    'format' => 'module', // 'module' or 'namespace'
],
```

#### Output Formats

**Module Format** (`'module'`):

```typescript
import { z } from "zod";

export const UserSchema = z.object({
  name: z.string(),
  email: z.email(),
});

export type UserSchemaType = z.infer<typeof UserSchema>;
```

**Namespace Format** (`'namespace'`):

```typescript
import { z } from "zod";

declare namespace Schemas {
  const UserSchema: z.ValidationSchema<{
    name: string;
    email: string;
  }>;

  type UserSchemaType = z.infer<typeof UserSchema>;
}
```

### Namespace Configuration

When using namespace format, specify the namespace name:

```php
'namespace' => 'Schemas',
```

**Default**: `'Schemas'`

### App Types Integration

Configure integration with existing TypeScript types in your project:

```php
'app_types_import_path' => '.',
'use_app_types' => false,
```

When enabled, this will reference your existing App types:

```typescript
import { z } from "zod";
import type { User } from "./types"; // Your existing types

export const UserSchema: z.ValidationSchema<User> = z.object({
  name: z.string(),
  email: z.email(),
});
```

### Feature Flags

Control which features are enabled:

```php
'features' => [
    // Support for Spatie Laravel Data classes
    'data_classes' => 'auto',

    // Hook into typescript:transform command
    'typescript_transformer_hook' => 'auto',
],
```

**Options**:

- `'auto'` - Auto-detect based on installed packages (recommended)
- `true` - Force enable (may cause errors if dependencies not installed)
- `false` - Disable completely

### Custom Extractors

Register custom extractors to handle additional validation sources:

```php
'custom_extractors' => [
    \App\ZodExtractors\ApiValidatorExtractor::class,
    \App\ZodExtractors\LegacyValidatorExtractor::class,
],
```

Each extractor must implement `ExtractorInterface`. See [Custom Extractors](./advanced/custom-extractors.md) for details.

### Custom Type Handlers

Register custom type handlers to override default behavior:

```php
'custom_type_handlers' => [
    \App\ZodTypeHandlers\DateTimeHandler::class,
    \App\ZodTypeHandlers\CurrencyHandler::class,
],
```

Each handler must implement `TypeHandlerInterface`. Handlers are processed in priority order (higher numbers = higher priority). See [Custom Type Handlers](./advanced/custom-type-handlers.md) for details.

## Complete Configuration Example

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    */
    'scan_paths' => [
        app_path('Http/Requests'),
        app_path('Data'),
        app_path('Validation'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    */
    'output' => [
        'path' => resource_path('js/types/validation-schemas.ts'),
        'format' => 'module',
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespace Configuration
    |--------------------------------------------------------------------------
    */
    'namespace' => 'ValidationSchemas',

    /*
    |--------------------------------------------------------------------------
    | App Types Integration
    |--------------------------------------------------------------------------
    */
    'app_types_import_path' => '../types',
    'use_app_types' => true,

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'data_classes' => 'auto',
        'typescript_transformer_hook' => 'auto',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Extractors
    |--------------------------------------------------------------------------
    */
    'custom_extractors' => [
        \App\ZodExtractors\ApiValidatorExtractor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Type Handlers
    |--------------------------------------------------------------------------
    */
    'custom_type_handlers' => [
        \App\ZodTypeHandlers\DateTimeHandler::class,
        \App\ZodTypeHandlers\CurrencyHandler::class,
    ],
];
```

## Environment-Specific Configuration

You can override configuration per environment using Laravel's standard config system:

**.env**:

```bash
# Override output path for local development
LARAVEL_ZOD_GENERATOR_OUTPUT_PATH="resources/js/schemas.ts"
```

**config/laravel-schema-generator.php**:

```php
'output' => [
    'path' => env('LARAVEL_ZOD_GENERATOR_OUTPUT_PATH', resource_path('js/types/zod-schemas.ts')),
    'format' => 'module',
],
```

## Validation

The package validates your configuration on startup. If there are issues, you'll see helpful error messages:

```bash
php artisan schema:generate

# Example validation errors:
❌ Output path directory does not exist: /invalid/path
❌ Custom extractor class not found: App\NonExistentExtractor
❌ Invalid output format 'invalid'. Must be 'module' or 'namespace'
```

## Performance Tuning

For large projects, consider these optimizations:

### Limit Scan Paths

```php
'scan_paths' => [
    app_path('Http/Requests'),  // Only scan specific directories
    app_path('Data'),           // instead of entire app directory
],
```

### Use Specific Namespaces

Instead of scanning `app_path()`, target specific namespaces where your validation classes live.

### Custom Extractors Priority

Set appropriate priorities for custom extractors to avoid unnecessary processing:

```php
class HighPerformanceExtractor implements ExtractorInterface
{
    public function getPriority(): int
    {
        return 1000; // Process first
    }

    public function canHandle(ReflectionClass $class): bool
    {
        // Quick checks first
        return str_ends_with($class->getName(), 'ApiRequest');
    }
}
```

## Next Steps

- [Quick Start Guide](./quick-start.mdx) - Start using the configured package
- [Basic Usage](./usage/basic-usage.md) - Learn how to use the package
- [Custom Extractors](./advanced/custom-extractors.md) - Create custom extractors
- [Custom Type Handlers](./advanced/custom-type-handlers.md) - Create custom type handlers
