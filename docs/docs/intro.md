---
sidebar_position: 1
---

# Laravel Zod Generator

Generate TypeScript Zod validation schemas from your Laravel validation rules. This package supports multiple validation sources including Laravel FormRequest classes, Spatie Data classes, and any PHP class with validation rules.

## ✨ Key Features

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

## How It Works

1. **Add the `#[ValidationSchema]` attribute** to your Laravel validation classes
2. **Run the generation command** to scan your codebase
3. **Use the generated Zod schemas** in your TypeScript frontend

## Quick Example

### PHP (Laravel FormRequest)

```php
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'age' => 'nullable|integer|min:18|max:120',
        ];
    }
}
```

### Generated TypeScript

```typescript
import { z } from "zod";

export const CreateUserRequestSchema = z.object({
  name: z.string().min(1).max(255),
  email: z.email().max(255),
  password: z.string().min(8),
  password_confirmation: z.string(),
  age: z.number().min(18).max(120).nullable(),
});

export type CreateUserRequestSchemaType = z.infer<
  typeof CreateUserRequestSchema
>;
```

### Usage in TypeScript

```typescript
import { CreateUserRequestSchema } from "@/types/zod-schemas";

// Validate form data
const result = CreateUserRequestSchema.safeParse(formData);

if (result.success) {
  // Data is valid
  await api.createUser(result.data);
} else {
  // Handle validation errors
  console.error(result.error.errors);
}
```

## Getting Started

Ready to get started? Check out our [Installation Guide](./installation.mdx) or jump straight to the [Quick Start Guide](./quick-start.mdx).

## Why Laravel Zod Generator?

### The Problem

Frontend and backend validation often get out of sync. You define validation rules in Laravel, then manually recreate similar rules in your TypeScript frontend. This leads to:

- **Duplicated effort** - Writing validation logic twice
- **Inconsistencies** - Frontend and backend validation can drift apart
- **Maintenance overhead** - Updating validation rules in multiple places
- **Runtime errors** - Invalid data reaching your API

### The Solution

Laravel Zod Generator automatically generates TypeScript Zod schemas from your existing Laravel validation rules, ensuring your frontend and backend validation stay perfectly in sync.

## Community & Support

- 📖 **Documentation**: You're reading it!
- 🐛 **Bug Reports**: [GitHub Issues](https://github.com/romegasoftware/laravel-schema-generator/issues)
- 📦 **Package**: [Packagist](https://packagist.org/packages/romegasoftware/laravel-schema-generator)

---

Built with ❤️ by [Romega Software](https://romegasoftware.com)
