# Laravel Schema Generator

Generate TypeScript Schema validation schemas from your Laravel validation rules. This package supports Laravel FormRequest classes, Spatie Data classes, and custom validation classes through an extensible architecture.

It will generate Zod schema out of the box, but can be extended for different schema generators.

## Features

- 🚀 **Zero Dependencies** - Works with vanilla Laravel
- 📦 **Smart Package Detection** - Automatically detects and uses installed packages
- 🎯 **Multiple Validation Sources** - FormRequests, Spatie Data classes, custom extractors
- 🔧 **Flexible Configuration** - Customize output paths, formats, and integration settings
- 🧩 **Highly Extensible** - Custom extractors and type handlers with priority system

## Installation

```bash
composer require romegasoftware/laravel-schema-generator
```

Ensure Zod v4 is installed

```bash
npm install zod
```

### Optional Packages

For additional features, install these optional packages:

```bash
# For Spatie Data class support
composer require spatie/laravel-data

# For TypeScript transformer integration
composer require spatie/laravel-typescript-transformer
```

## Configuration

To publish the configuration file, run:

```bash
php artisan vendor:publish --provider="RomegaSoftware\LaravelSchemaGenerator\LaravelSchemaGeneratorServiceProvider"
```

This will create a `config/laravel-schema-generator.php` file where you can customize output paths, formats, and integration settings.

### Generating Separate Files

By default, all schemas are generated into a single TypeScript file. You can configure the package to generate separate files for each schema (e.g., `UserSchema.ts`, `PostSchema.ts`) by enabling `separate_files` in the configuration.

```php
// config/laravel-schema-generator.php

'zod' => [
    'output' => [
        'path' => resource_path('js/types/schemas.ts'), // fallback/default path
        'separate_files' => true,
        // Optional: specify a dedicated directory for separate files
        'directory' => resource_path('js/types/schemas'),
    ],
],
```

When enabled, the generator will:
1. Create individual `.ts` files for each schema.
2. Automatically resolve and inject imports for dependent schemas (e.g., if `UserSchema` references `AddressSchema`).

## Quick Start

1. **Add the attribute** to your Laravel validation classes:

```php
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'nullable|integer|min:18',
        ];
    }
}
```

2. **Generate the schemas**:

```bash
php artisan schema:generate
```

3. **Use in TypeScript**:

```typescript
import { CreateUserRequestSchema } from "@/types/schemas";

const result = CreateUserRequestSchema.safeParse(formData);
if (result.success) {
  // Data is valid
  await api.createUser(result.data);
}
```

## Relative date limits

Keep moving date limits as rule strings. Do not calculate a timestamp in `rules()`:

```php
'expires_at' => ['nullable', 'date', 'after:now', 'before:+1 week'],
```

The generated schema calculates the limit each time validation runs. Its output does not change with the generation date. Signed integer offsets support seconds, minutes, hours, days, and weeks, with an optional `now` prefix, such as `now -2 hours`. Days and weeks use elapsed durations of 24 hours and 7 days. Use these offsets with a UTC server timezone to keep PHP and browser limits equal across daylight saving changes. Calendar expressions such as `next Friday` and `+1 month` are not supported.

Fixed date strings and references to other fields remain supported. For a clear validation message, set a message such as `expires_at.before` to `The date must be within the next 7 days.`

## Documentation

For complete documentation, configuration options, advanced features, and examples, visit:

~~**📚 [Official Documentation](https://laravel-schema-generator.romegasoftware.com)**~~ Coming Soon

## Custom Schema Overrides

When you need to keep bespoke Laravel validation logic but still describe the TypeScript shape, provide a literal override using the fluent helper. Prefix the snippet with `.` when you want to append behaviour to the inferred Zod builder instead of replacing it entirely:

```php
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaRule;

'items' => [
    'required',
    'array',
    SchemaRule::make(
        static function ($attribute, $value, $fail, string $message): void {
            if (collect($value)->sum('qty') < 12) {
                $fail($message);
            }
        }
    )
        ->append(static function (string $encodedMessage): string {
            return <<<ZOD
                .superRefine((items, ctx) => {
                    const total = items.reduce((sum, item) => sum + item.qty, 0);
                    if (total < 12) {
                        ctx.addIssue({
                            code: 'custom',
                            message: {$encodedMessage},
                            path: ['items'],
                        });
                    }
                })
                ZOD;
        })
        ->failWith('You must order at least 12 total units.'),
],
```

Because the override begins with `.`, the generator keeps the inferred base (`z.array(...)`) and simply appends your refinement. The callable passed to `append()` receives the JSON-encoded message as its first argument (and the raw message as a second argument if you declare it). When you want to replace the builder outright, omit the leading dot and provide the complete Zod expression (for example `z.array(z.object({ ... }))`).

Prefer dedicated rule objects? Implement `SchemaAnnotatedRule` and reuse the same fluent API with the provided trait:

```php
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RomegaSoftware\LaravelSchemaGenerator\Concerns\InteractsWithSchemaFragment;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\SchemaAnnotatedRule;

final class TotalOrderItemsRule implements SchemaAnnotatedRule, ValidationRule
{
    use InteractsWithSchemaFragment;

    public function __construct()
    {
        $this->withFailureMessage('You must order at least 12 total cases.')
            ->append(function (?string $encodedMessage) {
                return <<<ZOD
                .superRefine((items, ctx) => {
                    const total = items.reduce((sum, item) => sum + item.quantity, 0);
                    if (total < 12) {
                        ctx.addIssue({
                            code: 'custom',
                            message: {$encodedMessage},
                            path: ['items']
                        });
                    }
                })
                ZOD;
            });
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (collect($value)->sum('quantity') < 12) {
            $fail($this->failureMessage() ?? 'You must order at least 12 total units.');
        }
    }
}
```

Both approaches surface the schema fragment directly alongside the validation logic and are picked up automatically by the generator for FormRequests and Spatie Data classes.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for development setup and contribution guidelines.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Romega Software](https://romegasoftware.com/)
- [All Contributors](../../contributors)
