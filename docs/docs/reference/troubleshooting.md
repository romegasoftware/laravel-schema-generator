---
sidebar_position: 2
---

# Troubleshooting

Common issues and solutions when using Laravel Zod Generator. This guide helps you diagnose and fix problems with schema generation, validation, and integration.

## Installation Issues

### Command Not Available

**Problem**: `php artisan schema:generate` command not found after installation.

**Solutions**:

1. Check if the package is properly installed:

   ```bash
   composer show romegasoftware/laravel-schema-generator
   ```

2. Clear Laravel caches:

   ```bash
   php artisan clear-compiled
   php artisan cache:clear
   php artisan config:clear
   ```

3. Re-run auto-discovery:
   ```bash
   composer dump-autoload
   ```

### Memory Issues

**Problem**: Out of memory errors during installation.

**Solution**: Increase PHP memory limit:

```bash
php -d memory_limit=512M /usr/local/bin/composer require romegasoftware/laravel-schema-generator
```

## Schema Generation Issues

### No Schemas Generated

**Problem**: Running `php artisan schema:generate` produces no output.

**Debug Steps**:

1. Check if any classes have the `#[ValidationSchema]` attribute:

   ```bash
   grep -r "ValidationSchema" app/
   ```

2. Verify scan paths in configuration:

   ```bash
   php artisan config:show laravel-schema-generator.scan_paths
   ```

3. Run with verbose output:

   ```bash
   php artisan schema:generate -v
   ```

4. Check for syntax errors in your validation classes:
   ```bash
   php -l app/Http/Requests/YourRequest.php
   ```

### Class Not Found Errors

**Problem**: Error messages like "Class 'App\Http\Requests\UserRequest' not found".

**Solutions**:

1. Verify namespace and class name:

   ```php
   <?php
   namespace App\Http\Requests; // Correct namespace

   class UserRequest // Correct class name
   ```

2. Check PSR-4 autoloading in `composer.json`:

   ```json
   {
     "autoload": {
       "psr-4": {
         "App\\": "app/"
       }
     }
   }
   ```

3. Regenerate autoloader:
   ```bash
   composer dump-autoload
   ```

### Invalid Attribute Usage

**Problem**: PHP errors about invalid attribute usage.

**Common Mistakes**:

```php
// Wrong: Missing use statement
#[ValidationSchema]
class UserRequest extends FormRequest { }

// Wrong: Incorrect namespace
use Laravel\ZodGenerator\Attributes\ValidationSchema;

// Correct:
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class UserRequest extends FormRequest { }
```

### Output File Issues

**Problem**: Generated file not found or not writable.

**Solutions**:

1. Check output directory exists:

   ```bash
   mkdir -p resources/js/types
   ```

2. Verify write permissions:

   ```bash
   chmod 755 resources/js/types
   ```

3. Check disk space:
   ```bash
   df -h
   ```

## Validation Rule Issues

### Unsupported Validation Rules

**Problem**: Some Laravel validation rules not converted to Zod.

**Explanation**: Not all Laravel rules have direct Zod equivalents:

**Unsupported Rules**:

- `unique` - Requires database access
- `exists` - Requires database access
- `required_if` - Complex conditional logic
- Custom rules - Need custom type handlers

**Solutions**:

1. Use client-side alternatives:

   ```typescript
   // Instead of 'unique:users,email'
   const result = await checkEmailUnique(email);
   ```

2. Create custom type handlers for complex rules:

   ```php
   // See Custom Type Handlers documentation
   ```

3. Add manual validation in TypeScript:
   ```typescript
   const schema = z
     .object({
       email: z.email(),
     })
     .refine(async (data) => {
       return await checkEmailUnique(data.email);
     }, "Email already exists");
   ```

### Incorrect Zod Output

**Problem**: Generated Zod schema doesn't match expected validation.

**Debug Steps**:

1. Check Laravel validation rules syntax:

   ```php
   // Wrong
   'email' => 'required,email,max:255'

   // Correct
   'email' => 'required|email|max:255'
   ```

2. Verify rule parsing with verbose output:

   ```bash
   php artisan schema:generate -vv
   ```

3. Test Laravel validation separately:
   ```bash
   php artisan tinker
   >>> validator(['email' => 'test'], ['email' => 'required|email'])->fails()
   ```

## TypeScript Integration Issues

### Import Errors

**Problem**: Cannot import generated schemas in TypeScript.

**Solutions**:

1. Verify file exists at expected path:

   ```bash
   ls -la resources/js/types/zod-schemas.ts
   ```

2. Check TypeScript import path:

   ```typescript
   // If file is at resources/js/types/zod-schemas.ts
   import { UserSchema } from "@/types/zod-schemas";
   ```

3. Verify path mapping in `tsconfig.json`:
   ```json
   {
     "compilerOptions": {
       "baseUrl": ".",
       "paths": {
         "@/*": ["resources/js/*"]
       }
     }
   }
   ```

### Type Errors

**Problem**: TypeScript compilation errors with generated schemas.

**Solutions**:

1. Ensure Zod is installed:

   ```bash
   npm install zod
   ```

2. Check Zod version compatibility:

   ```bash
   npm ls zod
   ```

3. Verify generated schema syntax:
   ```bash
   npx tsc --noEmit resources/js/types/zod-schemas.ts
   ```

### Runtime Validation Errors

**Problem**: Zod validation fails with data that passes Laravel validation.

**Debug Steps**:

1. Compare validation rules:

   ```php
   // Laravel
   'age' => 'nullable|integer|min:18'
   ```

   ```typescript
   // Generated Zod
   age: z.number().int().min(18).nullable();
   ```

2. Check data types match:

   ```typescript
   // Wrong: String when expecting number
   {
     age: "25";
   }

   // Correct: Number
   {
     age: 25;
   }
   ```

3. Add debug logging:
   ```typescript
   const result = UserSchema.safeParse(data);
   if (!result.success) {
     console.log("Validation errors:", result.error.issues);
     console.log("Input data:", data);
   }
   ```

## Custom Extractor Issues

### Extractor Not Used

**Problem**: Custom extractor registered but not being called.

**Debug Steps**:

1. Check extractor registration:

   ```php
   // config/laravel-schema-generator.php
   'custom_extractors' => [
     \App\ZodExtractors\MyExtractor::class,
   ],
   ```

2. Verify `canHandle()` method returns `true`:

   ```php
   public function canHandle(ReflectionClass $class): bool
   {
     // Add debug logging
     logger()->debug('Checking class: ' . $class->getName());
     return str_ends_with($class->getName(), 'MyValidator');
   }
   ```

3. Check extractor priority:

   ```php
   public function getPriority(): int
   {
     return 200; // Higher than built-in extractors (100)
   }
   ```

4. Run generation with debug output:
   ```bash
   php artisan schema:generate --debug
   ```

### Extractor Errors

**Problem**: Errors when custom extractor runs.

**Common Issues**:

1. **Class instantiation errors**:

   ```php
   public function extract(ReflectionClass $class): array
   {
     try {
       $instance = $class->newInstance();
     } catch (Exception $e) {
       throw new ExtractionException("Cannot instantiate {$class->getName()}: {$e->getMessage()}");
     }
   }
   ```

2. **Missing method errors**:

   ```php
   if (!method_exists($instance, 'getRules')) {
     throw new ExtractionException("Class {$class->getName()} must have getRules() method");
   }
   ```

3. **Invalid return data**:
   ```php
   $rules = $instance->getRules();
   if (!is_array($rules)) {
     throw new ExtractionException("getRules() must return array");
   }
   ```

## Custom Type Handler Issues

### Handler Not Applied

**Problem**: Custom type handler registered but not being used.

**Debug Steps**:

1. Check priority against built-in handlers:

   ```php
   public function getPriority(): int
   {
     return 300; // Must be higher than competing handlers
   }
   ```

2. Verify `canHandleProperty()` logic:

   ```php
   public function canHandleProperty(array $property): bool
   {
     logger()->debug('Checking property', $property);
     return $property['type'] === 'custom_type';
   }
   ```

3. Debug handler selection:
   ```bash
   php artisan schema:generate -vv
   ```

### Handler Execution Errors

**Problem**: Type handler throws errors during execution.

**Common Fixes**:

1. **Handle missing validation data**:

   ```php
   public function handle(array $property): ZodBuilder
   {
     $validations = $property['validations'] ?? [];
     $builder = new ZodStringBuilder();

     // Always check if validation exists before using
     if (isset($validations['min'])) {
       $builder->min($validations['min']);
     }

     return $builder;
   }
   ```

2. **Validate property structure**:

   ```php
   public function handle(array $property): ZodBuilder
   {
     if (!isset($property['name'], $property['type'])) {
       throw new InvalidArgumentException('Property must have name and type');
     }

     // Continue processing...
   }
   ```

## Performance Issues

### Slow Generation

**Problem**: Schema generation takes too long.

**Solutions**:

1. Limit scan paths:

   ```php
   'scan_paths' => [
     app_path('Http/Requests'), // Instead of app_path()
   ],
   ```

2. Add caching to custom extractors:

   ```php
   private static array $cache = [];

   public function extract(ReflectionClass $class): array
   {
     $key = $class->getName();
     if (!isset(self::$cache[$key])) {
       self::$cache[$key] = $this->performExtraction($class);
     }
     return self::$cache[$key];
   }
   ```

3. Profile with timing:
   ```bash
   time php artisan schema:generate
   ```

### Memory Usage

**Problem**: High memory usage during generation.

**Solutions**:

1. Increase PHP memory limit:

   ```bash
   php -d memory_limit=512M artisan schema:generate
   ```

2. Process files in batches for large projects:
   ```php
   // Custom implementation for very large codebases
   ```

## Integration Issues

### Build Process Errors

**Problem**: Schema generation fails in CI/CD pipeline.

**Solutions**:

1. Ensure all dependencies available in CI:

   ```yaml
   # GitHub Actions example
   - name: Generate schemas
     run: |
       composer install --no-dev --optimize-autoloader
       php artisan schema:generate
   ```

2. Check file permissions:

   ```yaml
   - name: Set permissions
     run: chmod -R 755 resources/js/types
   ```

3. Verify output directory exists:
   ```yaml
   - name: Create directories
     run: mkdir -p resources/js/types
   ```

### Version Control Issues

**Problem**: Generated files causing merge conflicts.

**Solutions**:

1. Add generated files to `.gitignore`:

   ```
   /resources/js/types/zod-schemas.ts
   ```

2. Generate during build process instead of committing:

   ```json
   {
     "scripts": {
       "build": "php artisan schema:generate && npm run build"
     }
   }
   ```

3. Use consistent generation environment:
   ```bash
   # Lock to specific PHP version
   php8.1 artisan schema:generate
   ```

## Getting Help

### Enable Debug Mode

Add debug logging to help diagnose issues:

```php
// In your custom extractors/handlers
if (config('app.debug')) {
    logger()->info('Debug info', [
        'class' => $class->getName(),
        'data' => $someData,
    ]);
}
```

### Collect Diagnostic Information

When reporting issues, include:

1. **Environment info**:

   ```bash
   php --version
   composer show romegasoftware/laravel-schema-generator
   php artisan --version
   ```

2. **Configuration**:

   ```bash
   php artisan config:show laravel-schema-generator
   ```

3. **Sample validation class**:

   ```php
   // Minimal reproduction case
   ```

4. **Error output**:
   ```bash
   php artisan schema:generate -vv 2>&1 | tee debug.log
   ```

### Common Command Options for Debugging

```bash
# Verbose output
php artisan schema:generate -v

# Very verbose output
php artisan schema:generate -vv

# Dry run (show what would be generated)
php artisan schema:generate --dry-run

# Force overwrite existing files
php artisan schema:generate --force

# Debug mode with full error traces
php artisan schema:generate --debug
```

### Community Resources

- **GitHub Issues**: [Report bugs and feature requests](https://github.com/romegasoftware/laravel-schema-generator/issues)
- **Discussions**: [Community Q&A](https://github.com/romegasoftware/laravel-schema-generator/discussions)

## Next Steps

- [Validation Rules Reference](./validation-rules.md) - Complete rule mapping
- [Custom Extractors](../advanced/custom-extractors.md) - Debug extraction issues
- [Custom Type Handlers](../advanced/custom-type-handlers.md) - Fix type conversion problems
- [Integration Guide](../advanced/integration.md) - Resolve build process issues
