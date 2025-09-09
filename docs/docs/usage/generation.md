---
sidebar_position: 3
---

# Generation Process

Understand how Laravel Zod Generator scans your codebase and creates TypeScript Zod schemas. Learn about the generation command options and workflow.

## The Generation Command

The main command for generating schemas:

```bash
php artisan schema:generate
```

### Command Options

| Option      | Description                                         | Example                                                      |
| ----------- | --------------------------------------------------- | ------------------------------------------------------------ |
| `--dry-run` | Show what would be generated without creating files | `php artisan schema:generate --dry-run`                      |
| `--force`   | Overwrite existing files without confirmation       | `php artisan schema:generate --force`                        |
| `--path`    | Override the output path from config                | `php artisan schema:generate --path=resources/js/schemas.ts` |
| `--format`  | Override the output format                          | `php artisan schema:generate --format=namespace`             |

### Detailed Output

Use the verbose flag to see detailed information about the generation process:

```bash
php artisan schema:generate -v

# Example output:
📦 Available Features:
  ✓ Laravel FormRequest support enabled
  ✓ Spatie Data class support enabled
  ✓ TypeScript Transformer integration available

🔍 Scanning paths:
  - /app/Http/Requests
  - /app/Data
  - /app/Validation

📋 Found classes:
  ✓ App\Http\Requests\CreateUserRequest (FormRequest)
  ✓ App\Http\Requests\UpdatePostRequest (FormRequest)
  ✓ App\Data\UserData (Spatie Data)
  ✓ App\Validation\CustomValidator (Custom)

🔧 Processing schemas:
  → CreateUserRequest: 6 properties, 2 arrays, 3 custom messages
  → UpdatePostRequest: 4 properties, 1 enum, 1 custom message
  → UserData: 3 properties, nested object support
  → CustomValidator: 5 properties, regex validation

✅ Generated schemas at: resources/js/types/zod-schemas.ts
📝 Generated 4 schemas successfully!
```

## Generation Workflow

### Feature Detection

The generator first detects which features are available:

```bash
php artisan schema:generate --dry-run

📦 Available Features:
  ✓ Laravel FormRequest support enabled
  ✓ Spatie Data class support enabled (spatie/laravel-data detected)
  ✗ TypeScript Transformer integration unavailable (package not found)
  ✓ Custom extractors: 2 registered
  ✓ Custom type handlers: 3 registered
```

### Class Scanning

The generator scans configured paths for classes with the `#[ValidationSchema]` attribute:

- **Recursive scanning**: Subdirectories are included
- **Namespace mapping**: Uses PSR-4 autoloading
- **Class instantiation**: Validates classes can be instantiated
- **Attribute detection**: Finds `#[ValidationSchema]` attributes

### Extraction Process

For each found class, extractors determine how to extract validation rules:

#### Priority System

1. **Custom extractors** (priority 200-1000+)
2. **Spatie Data extractor** (priority 150)
3. **FormRequest extractor** (priority 100)
4. **Generic rules() method extractor** (priority 50)

#### Extraction Details

```bash
php artisan schema:generate -vv

🔧 Extractor selection:
  → CreateUserRequest: FormRequestExtractor (priority: 100)
  → UserData: SpatieDataExtractor (priority: 150)
  → CustomValidator: CustomApiExtractor (priority: 300)
  → LegacyValidator: RulesMethodExtractor (priority: 50)
```

### Type Handler Processing

Each property is processed by type handlers to generate appropriate Zod validation:

#### Built-in Handlers

- **StringTypeHandler** (priority: 100)
- **NumberTypeHandler** (priority: 100)
- **BooleanTypeHandler** (priority: 100)
- **ArrayTypeHandler** (priority: 100)
- **EmailTypeHandler** (priority: 110)

#### Custom Handlers

Your custom handlers can override or extend built-in behavior:

```bash
🔧 Type handler selection:
  → name (string): StringTypeHandler
  → email (string): EmailTypeHandler (overrides StringTypeHandler)
  → price (number): CurrencyTypeHandler (custom, priority: 300)
  → tags (array): ArrayTypeHandler
```

### Schema Generation

The final step generates TypeScript code:

- **Validation rules** are converted to Zod methods
- **Custom messages** are preserved
- **Types** are inferred from Zod schemas
- **Imports** are automatically added

## Dry Run Mode

Use dry run to preview what will be generated:

```bash
php artisan schema:generate --dry-run
```

### Dry Run Output

```typescript
// Preview of generated content:
import { z } from "zod";

export const CreateUserSchema = z.object({
  name: z.string().min(1, "Name is required").max(255),
  email: z.email("Invalid email format"),
  age: z.number().min(18).nullable(),
});

export type CreateUserSchemaType = z.infer<typeof CreateUserSchema>;

// File would be written to: resources/js/types/zod-schemas.ts
// Total schemas: 1
```

## Incremental Generation

The generator is smart about updating existing files:

### File Comparison

- **Content comparison**: Only updates if content has changed
- **Timestamp preservation**: Maintains file modification times when possible
- **Backup creation**: Can create backups before overwriting

### Incremental Updates

```bash
# First run
php artisan schema:generate
# Generated 3 schemas successfully!

# Second run (no changes)
php artisan schema:generate
# No changes detected, file not modified

# Third run (after adding new schema)
php artisan schema:generate
# Updated 1 existing schema, added 1 new schema
```

## Integration with TypeScript Transformer

If you have `spatie/laravel-typescript-transformer` installed:

### Automatic Integration

```bash
php artisan typescript:transform

# Output:
✓ Generated TypeScript types
✓ Generated Zod schemas (automatic hook)
```

### Manual Control

Disable automatic integration in config:

```php
'features' => [
    'typescript_transformer_hook' => false,
],
```

Then run commands separately:

```bash
php artisan typescript:transform
php artisan schema:generate
```

## Error Handling

### Common Errors

#### Class Not Found

```bash
❌ Error: Class 'App\Http\Requests\NonExistentRequest' not found
   Solution: Check class name and namespace
```

#### Invalid Rules

```bash
❌ Error: Invalid validation rules in App\Data\UserData
   Details: Property 'email' has unsupported validation attribute
   Solution: Use supported validation rules or create custom type handler
```

#### Output Directory Issues

```bash
❌ Error: Cannot write to output path: /invalid/path/schemas.ts
   Solution: Check directory exists and is writable
```

### Debugging

Enable debug mode for detailed error information:

```bash
php artisan schema:generate --debug

# Shows stack traces and detailed processing information
```

### Validation Issues

Check for validation rule compatibility:

```bash
# Supported Laravel rules
✓ required, nullable, string, email, numeric, boolean, array
✓ min, max, regex, url, uuid, in, date
✓ confirmed, unique (noted but not enforced client-side)

# Unsupported or complex rules
⚠️ required_if, required_unless, required_with
⚠️ custom validation rules without type handlers
```

## Performance Optimization

### Large Codebases

For projects with many files:

#### Targeted Scanning

```php
// config/laravel-schema-generator.php
'scan_paths' => [
    app_path('Http/Requests'),  // Only scan specific directories
    app_path('Data'),
    // Skip: app_path() - too broad for large projects
],
```

#### Custom Extractors with Caching

```php
class CachedExtractor implements ExtractorInterface
{
    private static array $cache = [];

    public function extract(ReflectionClass $class): array
    {
        $key = $class->getName();

        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = $this->performExtraction($class);
        }

        return self::$cache[$key];
    }
}
```

### Memory Usage

Monitor memory usage for very large projects:

```bash
php -d memory_limit=512M artisan schema:generate -v

# Shows memory usage:
Memory usage: 128MB / 512MB
Peak memory: 256MB
```

## Automated Generation

### CI/CD Integration

Add to your deployment pipeline:

```yaml
# .github/workflows/deploy.yml
- name: Generate Zod Schemas
  run: |
    php artisan schema:generate --force
    # Commit generated files if needed
```

### Git Hooks

Pre-commit hook to ensure schemas are up to date:

```bash
#!/bin/bash
# .git/hooks/pre-commit

php artisan schema:generate --dry-run --quiet
if [ $? -ne 0 ]; then
    echo "Zod schemas need to be regenerated"
    echo "Run: php artisan schema:generate"
    exit 1
fi
```

### Watch Mode

For development, you can create a simple watch script:

```bash
#!/bin/bash
# watch-schemas.sh

while inotifywait -e modify app/Http/Requests app/Data; do
    php artisan schema:generate
    echo "Schemas regenerated at $(date)"
done
```

## Next Steps

- [TypeScript Usage](./typescript-usage.md) - Use the generated schemas
- [Custom Extractors](../advanced/custom-extractors.md) - Handle complex extraction
- [Custom Type Handlers](../advanced/custom-type-handlers.md) - Customize type conversion
- [Troubleshooting](../reference/troubleshooting.md) - Solve common issues
