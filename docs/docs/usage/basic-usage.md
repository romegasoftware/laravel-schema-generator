---
sidebar_position: 1
---

# Basic Usage

Learn how to use Laravel Zod Generator with different types of validation classes. This guide covers the most common use cases and patterns.

## The ValidationSchema Attribute

The `#[ValidationSchema]` attribute is the heart of Laravel Zod Generator. Add it to any class with validation rules to generate a Zod schema.

```php
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class YourValidationClass
{
    // Your validation logic here
}
```

## Laravel FormRequest Classes

The most common use case is with Laravel FormRequest classes.

### Basic FormRequest

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'age' => 'nullable|integer|min:13|max:120',
            'website' => 'nullable|url',
            'terms_accepted' => 'required|boolean|accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name',
            'email.unique' => 'This email address is already registered',
            'password.min' => 'Password must be at least 8 characters long',
            'terms_accepted.accepted' => 'You must accept the terms and conditions',
        ];
    }
}
```

### Generated Schema

```typescript
import { z } from "zod";

export const CreateUserRequestSchema = z.object({
  name: z.string().min(1, "Please enter your full name").max(255),
  email: z.email().max(255),
  password: z.string().min(8, "Password must be at least 8 characters long"),
  password_confirmation: z.string(),
  age: z.number().min(13).max(120).nullable(),
  website: z.url().nullable(),
  terms_accepted: z.boolean(),
});

export type CreateUserRequestSchemaType = z.infer<
  typeof CreateUserRequestSchema
>;
```

### Complex Validation Rules

```php
#[ValidationSchema]
class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0.01|max:99999.99',
            'category_id' => 'required|integer|exists:categories,id',
            'tags' => 'array|max:10',
            'tags.*' => 'string|max:50|regex:/^[a-zA-Z0-9\-_]+$/',
            'images' => 'array|max:5',
            'images.*' => 'image|mimes:jpeg,png,webp|max:2048',
            'description' => 'nullable|string|max:1000',
            'is_featured' => 'boolean',
            'available_at' => 'nullable|date|after:today',
            'metadata' => 'array',
            'metadata.weight' => 'nullable|numeric|min:0',
            'metadata.dimensions' => 'array',
            'metadata.dimensions.length' => 'nullable|numeric|min:0',
            'metadata.dimensions.width' => 'nullable|numeric|min:0',
            'metadata.dimensions.height' => 'nullable|numeric|min:0',
        ];
    }
}
```

## Spatie Data Classes

If you have `spatie/laravel-data` installed, you can use Data classes with property-level validation attributes.

### Basic Data Class

```php
<?php

namespace App\Data;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\*;

#[ValidationSchema]
class UserData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, Email, Max(255)]
        public string $email,

        #[Nullable, Integer, Min(18), Max(120)]
        public ?int $age,

        #[Boolean]
        public bool $is_active = true,

        #[ArrayType, Max(5)]
        public array $roles = [],
    ) {}
}
```

### Nested Data Classes

```php
#[ValidationSchema]
class AddressData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $street,

        #[Required, StringType, Max(100)]
        public string $city,

        #[Required, StringType, Regex('/^\d{5}(-\d{4})?$/')]
        public string $postal_code,

        #[Required, StringType, Size(2)]
        public string $country_code,
    ) {}
}

#[ValidationSchema]
class UserProfileData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, Email]
        public string $email,

        public AddressData $address,

        #[DataCollectionOf(AddressData::class)]
        public DataCollection $additional_addresses,
    ) {}
}
```

## Custom Validation Classes

You can use any PHP class that has a `rules()` method:

```php
<?php

namespace App\Validators;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class ApiUserValidator
{
    public function rules(): array
    {
        return [
            'username' => 'required|string|alpha_dash|max:50|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:12|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'role' => 'required|in:admin,editor,viewer',
            'permissions' => 'array',
            'permissions.*' => 'string|in:read,write,delete',
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character',
        ];
    }
}
```

> Need to reuse Laravel rule objects or closure validators? See the dedicated [Custom Validation Rules](./custom-validation-rules.md) guide for attaching Zod overrides without rewriting your validation logic.

## Array Validation

Laravel Zod Generator properly handles array validation:

### Simple Arrays

```php
public function rules(): array
{
    return [
        'tags' => 'required|array|min:1|max:5',
        'tags.*' => 'string|max:50',
    ];
}
```

Generates:

```typescript
tags: z.array(z.string().max(50)).min(1).max(5),
```

### Nested Arrays

```php
public function rules(): array
{
    return [
        'users' => 'required|array',
        'users.*.name' => 'required|string|max:255',
        'users.*.email' => 'required|email',
        'users.*.roles' => 'array',
        'users.*.roles.*' => 'string|in:admin,user,guest',
    ];
}
```

Generates:

```typescript
users: z.array(z.object({
  name: z.string().min(1).max(255),
  email: z.email(),
  roles: z.array(z.enum(['admin', 'user', 'guest'])).optional(),
})),
```

## Conditional Validation

Handle conditional validation rules:

```php
public function rules(): array
{
    return [
        'user_type' => 'required|in:individual,business',
        'first_name' => 'required_if:user_type,individual|string|max:255',
        'last_name' => 'required_if:user_type,individual|string|max:255',
        'company_name' => 'required_if:user_type,business|string|max:255',
        'tax_id' => 'required_if:user_type,business|string',
    ];
}
```

:::note
Some Laravel conditional rules like `required_if` don't have direct Zod equivalents. The generated schema will include the base validation rules, but you may need to add custom logic for complex conditionals.
:::

## File Uploads

Handle file validation (though files are typically handled server-side):

```php
public function rules(): array
{
    return [
        'avatar' => 'nullable|image|mimes:jpeg,png,gif|max:2048',
        'documents' => 'array|max:5',
        'documents.*' => 'file|mimes:pdf,doc,docx|max:10240',
    ];
}
```

For file uploads, consider validating file metadata in TypeScript:

```typescript
const FileMetadataSchema = z.object({
  name: z.string().max(255),
  size: z.number().max(2048 * 1024), // 2MB in bytes
  type: z.enum(["image/jpeg", "image/png", "image/gif"]),
});
```

## Custom Validation Messages

Laravel validation messages are preserved in the generated schemas:

```php
public function rules(): array
{
    return [
        'email' => 'required|email|max:255',
        'password' => 'required|min:8',
    ];
}

public function messages(): array
{
    return [
        'email.required' => 'Please enter your email address',
        'email.email' => 'Please enter a valid email address',
        'password.min' => 'Password must be at least 8 characters',
    ];
}
```

Generates:

```typescript
export const YourSchema = z.object({
  email: z
    .email("Please enter a valid email address")
    .min(1, "Please enter your email address")
    .max(255),
  password: z.string().min(8, "Password must be at least 8 characters"),
});
```

## Best Practices

### Organize Your Validation Classes

```php
// Group related validations
namespace App\Http\Requests\Auth;
namespace App\Http\Requests\Posts;
namespace App\Http\Requests\Users;
```

### Use Descriptive Schema Names

```php
#[ValidationSchema(name: 'UserRegistrationForm')]
class CreateUserRequest extends FormRequest { /* ... */ }

#[ValidationSchema(name: 'PostCreationForm')]
class CreatePostRequest extends FormRequest { /* ... */ }
```

### Keep Validation Rules Simple

Complex Laravel validation logic may not translate perfectly to Zod. Keep rules focused on data validation rather than business logic.

### Test Your Schemas

Always test the generated schemas with real data:

```typescript
import { describe, it, expect } from "vitest";
import { CreateUserRequestSchema } from "@/types/zod-schemas";

describe("CreateUserRequestSchema", () => {
  it("validates valid user data", () => {
    const validData = {
      name: "John Doe",
      email: "john@example.com",
      password: "password123",
      password_confirmation: "password123",
      terms_accepted: true,
    };

    const result = CreateUserRequestSchema.safeParse(validData);
    expect(result.success).toBe(true);
  });

  it("rejects invalid data", () => {
    const invalidData = {
      name: "",
      email: "invalid-email",
      password: "123", // Too short
    };

    const result = CreateUserRequestSchema.safeParse(invalidData);
    expect(result.success).toBe(false);
  });
});
```

## Next Steps

- [Using Attributes](./attributes.md) - Learn about ValidationSchema attribute options
- [Generation Process](./generation.md) - Understand the generation command
- [TypeScript Usage](./typescript-usage.md) - Advanced TypeScript patterns
- [Custom Type Handlers](../advanced/custom-type-handlers.md) - Override default behavior
