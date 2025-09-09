---
sidebar_position: 1
---

# Validation Rules Reference

Complete reference for Laravel validation rules and their Zod schema equivalents. This table shows how Laravel Zod Generator converts Laravel validation rules to Zod validation methods.

## String Validation

| Laravel Rule  | Zod Schema       | Notes                          |
| ------------- | ---------------- | ------------------------------ |
| `string`      | `z.string()`     | Base string validation         |
| `required`    | `.min(1)`        | For strings, ensures non-empty |
| `nullable`    | `.nullable()`    | Allows `null` values           |
| `optional`    | `.optional()`    | Field can be omitted           |
| `min:X`       | `.min(X)`        | Minimum length validation      |
| `max:X`       | `.max(X)`        | Maximum length validation      |
| `size:X`      | `.length(X)`     | Exact length validation        |
| `between:X,Y` | `.min(X).max(Y)` | Length between X and Y         |

### String Format Validation

| Laravel Rule      | Zod Schema                   | Notes                                 |
| ----------------- | ---------------------------- | ------------------------------------- |
| `email`           | `z.email()`                  | Email format validation               |
| `url`             | `z.url()`                    | URL format validation                 |
| `uuid`            | `z.uuid()`                   | UUID format validation                |
| `alpha`           | `.regex(/^[a-zA-Z]+$/)`      | Letters only                          |
| `alpha_dash`      | `.regex(/^[a-zA-Z0-9_-]+$/)` | Letters, numbers, dashes, underscores |
| `alpha_num`       | `.regex(/^[a-zA-Z0-9]+$/)`   | Letters and numbers only              |
| `regex:/pattern/` | `.regex(/pattern/)`          | Custom regex validation               |

### Examples

```php
// Laravel validation
'name' => 'required|string|max:255',
'email' => 'required|email|max:255',
'username' => 'required|alpha_dash|min:3|max:20',
'website' => 'nullable|url',
```

```typescript
// Generated Zod schemas
name: z.string().min(1).max(255),
email: z.email().max(255),
username: z.string().regex(/^[a-zA-Z0-9_-]+$/).min(3).max(20),
website: z.string().url().nullable(),
```

## Numeric Validation

| Laravel Rule         | Zod Schema                         | Notes                           |
| -------------------- | ---------------------------------- | ------------------------------- |
| `numeric`            | `z.number()`                       | Any number                      |
| `integer`            | `z.number().int()`                 | Integer only                    |
| `required`           | (implicit)                         | Numbers are required by default |
| `nullable`           | `.nullable()`                      | Allows `null` values            |
| `min:X`              | `.min(X)`                          | Minimum value                   |
| `max:X`              | `.max(X)`                          | Maximum value                   |
| `between:X,Y`        | `.min(X).max(Y)`                   | Value between X and Y           |
| `gt:X`               | `.gt(X)`                           | Greater than X                  |
| `gte:X`              | `.gte(X)`                          | Greater than or equal to X      |
| `lt:X`               | `.lt(X)`                           | Less than X                     |
| `lte:X`              | `.lte(X)`                          | Less than or equal to X         |
| `digits:X`           | `.int().min(10^(X-1)).max(10^X-1)` | Exact number of digits          |
| `digits_between:X,Y` | Complex validation                 | Range of digits                 |

### Examples

```php
// Laravel validation
'age' => 'required|integer|min:18|max:120',
'price' => 'required|numeric|min:0.01|max:99999.99',
'quantity' => 'nullable|integer|min:0',
'rating' => 'integer|between:1,5',
```

```typescript
// Generated Zod schemas
age: z.number().int().min(18).max(120),
price: z.number().min(0.01).max(99999.99),
quantity: z.number().int().min(0).nullable(),
rating: z.number().int().min(1).max(5),
```

## Boolean Validation

| Laravel Rule | Zod Schema        | Notes                            |
| ------------ | ----------------- | -------------------------------- |
| `boolean`    | `z.boolean()`     | True/false validation            |
| `accepted`   | `z.literal(true)` | Must be `true`                   |
| `nullable`   | `.nullable()`     | Allows `null` values             |
| `required`   | (implicit)        | Booleans are required by default |

### Examples

```php
// Laravel validation
'is_active' => 'boolean',
'terms_accepted' => 'required|accepted',
'newsletter' => 'nullable|boolean',
```

```typescript
// Generated Zod schemas
is_active: z.boolean(),
terms_accepted: z.literal(true),
newsletter: z.boolean().nullable(),
```

## Array Validation

| Laravel Rule  | Zod Schema             | Notes                        |
| ------------- | ---------------------- | ---------------------------- |
| `array`       | `z.array(z.unknown())` | Basic array                  |
| `required`    | `.min(1)`              | Non-empty array              |
| `nullable`    | `.nullable()`          | Allows `null` values         |
| `min:X`       | `.min(X)`              | Minimum array length         |
| `max:X`       | `.max(X)`              | Maximum array length         |
| `size:X`      | `.length(X)`           | Exact array length           |
| `between:X,Y` | `.min(X).max(Y)`       | Array length between X and Y |

### Array Element Validation

| Laravel Rule      | Zod Schema                  | Notes                   |
| ----------------- | --------------------------- | ----------------------- |
| `array.*`         | Array element validation    | Applied to each element |
| `array.*.string`  | `z.array(z.string())`       | Array of strings        |
| `array.*.integer` | `z.array(z.number().int())` | Array of integers       |
| `array.*.email`   | `z.array(z.email())`        | Array of emails         |

### Examples

```php
// Laravel validation
'tags' => 'required|array|min:1|max:5',
'tags.*' => 'string|max:50',
'permissions' => 'array',
'permissions.*' => 'in:read,write,delete',
'scores' => 'array',
'scores.*' => 'integer|between:0,100',
```

```typescript
// Generated Zod schemas
tags: z.array(z.string().max(50)).min(1).max(5),
permissions: z.array(z.enum(['read', 'write', 'delete'])).optional(),
scores: z.array(z.number().int().min(0).max(100)).optional(),
```

## Enum/Choice Validation

| Laravel Rule   | Zod Schema                | Notes                        |
| -------------- | ------------------------- | ---------------------------- |
| `in:a,b,c`     | `z.enum(['a', 'b', 'c'])` | Must be one of listed values |
| `not_in:x,y,z` | Custom validation         | Complex to implement in Zod  |

### Examples

```php
// Laravel validation
'status' => 'required|in:draft,published,archived',
'role' => 'required|in:admin,editor,viewer',
'priority' => 'in:low,medium,high',
```

```typescript
// Generated Zod schemas
status: z.enum(['draft', 'published', 'archived']),
role: z.enum(['admin', 'editor', 'viewer']),
priority: z.enum(['low', 'medium', 'high']).optional(),
```

## Date/Time Validation

| Laravel Rule           | Zod Schema                      | Notes                  |
| ---------------------- | ------------------------------- | ---------------------- |
| `date`                 | `z.string().datetime()`         | ISO datetime string    |
| `date_format:Y-m-d`    | `.regex(/^\d{4}-\d{2}-\d{2}$/)` | Custom date format     |
| `before:date`          | Custom validation               | Complex temporal logic |
| `after:date`           | Custom validation               | Complex temporal logic |
| `before_or_equal:date` | Custom validation               | Complex temporal logic |
| `after_or_equal:date`  | Custom validation               | Complex temporal logic |

### Examples

```php
// Laravel validation
'birth_date' => 'required|date',
'created_at' => 'required|date_format:Y-m-d H:i:s',
'expires_at' => 'nullable|date|after:today',
```

```typescript
// Generated Zod schemas
birth_date: z.string().datetime(),
created_at: z.string().regex(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/),
expires_at: z.string().datetime().nullable(),
```

## File Validation

:::note
File validation rules are typically handled server-side. Zod schemas for file fields usually validate file metadata rather than the file content itself.
:::

| Laravel Rule    | Zod Schema          | Notes                           |
| --------------- | ------------------- | ------------------------------- |
| `file`          | Custom type handler | Usually validates file metadata |
| `image`         | Custom type handler | Validates image file metadata   |
| `mimes:jpg,png` | Custom validation   | MIME type validation            |
| `max:2048`      | Custom validation   | File size validation (in KB)    |

### File Metadata Validation Example

```typescript
// Custom file validation (not auto-generated)
const FileMetadataSchema = z.object({
  name: z.string().max(255),
  size: z.number().max(2048 * 1024), // 2MB in bytes
  type: z.enum(["image/jpeg", "image/png", "image/gif"]),
});
```

## Complex/Custom Rules

### Confirmed Fields

| Laravel Rule | Zod Schema        | Notes                     |
| ------------ | ----------------- | ------------------------- |
| `confirmed`  | Manual validation | Requires custom Zod logic |

```typescript
// Manual implementation required
const CreateUserSchema = z
  .object({
    password: z.string().min(8),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
  });
```

### Conditional Validation

| Laravel Rule                  | Zod Schema        | Notes                          |
| ----------------------------- | ----------------- | ------------------------------ |
| `required_if:field,value`     | Manual validation | Use `.refine()` for conditions |
| `required_unless:field,value` | Manual validation | Use `.refine()` for conditions |
| `required_with:field`         | Manual validation | Use `.refine()` for conditions |
| `required_without:field`      | Manual validation | Use `.refine()` for conditions |

```typescript
// Manual conditional validation
const UserSchema = z
  .object({
    user_type: z.enum(["individual", "business"]),
    first_name: z.string().optional(),
    last_name: z.string().optional(),
    company_name: z.string().optional(),
  })
  .refine(
    (data) => {
      if (data.user_type === "individual") {
        return data.first_name && data.last_name;
      } else {
        return data.company_name;
      }
    },
    {
      message: "Required fields missing for user type",
    }
  );
```

## Database Validation

:::warning
Database validation rules like `unique` and `exists` are not enforced client-side as they require server-side database access.
:::

| Laravel Rule          | Zod Schema   | Notes                       |
| --------------------- | ------------ | --------------------------- |
| `unique:table,column` | Not enforced | Server-side validation only |
| `exists:table,column` | Not enforced | Server-side validation only |

The generated Zod schemas will include the basic field validation (string, email, etc.) but will not enforce uniqueness or existence constraints.

```php
// Laravel validation
'email' => 'required|email|unique:users,email',
'category_id' => 'required|integer|exists:categories,id',
```

```typescript
// Generated Zod schemas (without database constraints)
email: z.email(), // unique constraint not enforced
category_id: z.number().int(), // exists constraint not enforced
```

## Custom Messages

Laravel validation messages are preserved in the generated Zod schemas:

```php
// Laravel with custom messages
public function messages(): array
{
    return [
        'name.required' => 'Please enter your name',
        'email.email' => 'Please enter a valid email address',
        'password.min' => 'Password must be at least 8 characters long',
    ];
}
```

```typescript
// Generated Zod with custom messages
name: z.string().min(1, 'Please enter your name'),
email: z.email('Please enter a valid email address'),
password: z.string().min(8, 'Password must be at least 8 characters long'),
```

## Unsupported Rules

Some Laravel validation rules don't have direct Zod equivalents and require custom implementation:

### Server-Side Only Rules

- `unique` - Requires database access
- `exists` - Requires database access
- `mimes` - File content validation
- `dimensions` - Image dimension validation

### Complex Conditional Rules

- `required_if` - Use Zod's `.refine()` method
- `required_unless` - Use Zod's `.refine()` method
- `required_with` - Use Zod's `.refine()` method
- `required_without` - Use Zod's `.refine()` method

### Custom Business Logic Rules

- Custom validation rules created with `make:rule` - Requires custom type handlers

## Custom Type Handlers for Unsupported Rules

You can create custom type handlers to handle unsupported or complex validation rules:

```php
<?php

namespace App\ZodTypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerInterface;

class CustomRuleHandler implements TypeHandlerInterface
{
    public function canHandleProperty(array $property): bool
    {
        $validations = $property['validations'] ?? [];
        return isset($validations['custom_rule']);
    }

    public function handle(array $property): ZodBuilder
    {
        // Implement custom Zod validation logic
        $builder = new ZodStringBuilder();
        $builder->regex('/custom-pattern/', 'Custom validation message');
        return $builder;
    }

    public function getPriority(): int
    {
        return 300;
    }

    public function canHandle(string $type): bool
    {
        return false; // Only handle based on property validation
    }
}
```

## Best Practices

### Use Appropriate Types

Choose the most specific Zod type for your data:

```typescript
// Good: Specific types
age: z.number().int().min(0),
email: z.email(),
status: z.enum(['active', 'inactive']),

// Avoid: Generic types
age: z.string(), // Should be number
email: z.string(), // Should use email validation
status: z.string(), // Should use enum
```

### Handle Edge Cases

Consider nullable and optional fields:

```typescript
// Handle all states properly
phone: z.string().regex(/^\+?[\d\s-()]+$/).nullable(), // Can be null
website: z.string().url().optional(), // Can be omitted
```

### Preserve Error Messages

Always include meaningful error messages:

```php
// Laravel with descriptive messages
public function messages(): array
{
    return [
        'password.min' => 'Password must be at least 8 characters long',
        'phone.regex' => 'Please enter a valid phone number',
    ];
}
```

### Test Generated Schemas

Always test that your generated schemas work as expected:

```typescript
import { describe, it, expect } from "vitest";
import { CreateUserSchema } from "@/types/zod-schemas";

describe("CreateUserSchema", () => {
  it("validates valid data", () => {
    const validData = {
      name: "John Doe",
      email: "john@example.com",
      age: 25,
    };

    const result = CreateUserSchema.safeParse(validData);
    expect(result.success).toBe(true);
  });
});
```

## Next Steps

- [Troubleshooting](./troubleshooting.md) - Common issues and solutions
- [Custom Type Handlers](../advanced/custom-type-handlers.md) - Handle unsupported rules
- [Examples](../examples/real-world.md) - See validation rules in action
