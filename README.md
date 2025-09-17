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

## Documentation

For complete documentation, configuration options, advanced features, and examples, visit:

~~**📚 [Official Documentation](https://laravel-schema-generator.romegasoftware.com)**~~ Coming Soon

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for development setup and contribution guidelines.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Romega Software](https://romegasoftware.com/)
- [All Contributors](../../contributors)
