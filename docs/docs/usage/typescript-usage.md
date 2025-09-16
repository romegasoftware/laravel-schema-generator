---
sidebar_position: 4
---

# TypeScript Usage

Learn how to effectively use the generated Zod schemas in your TypeScript applications. This guide covers patterns, best practices, and advanced usage techniques.

## Generated Schema Structure

Laravel Zod Generator creates TypeScript files with the following structure:

```typescript
import { z } from "zod";

// Schema definition
export const CreateUserRequestSchema = z.object({
  name: z.string().min(1).max(255),
  email: z.email(),
  password: z.string().min(8),
  age: z.number().min(18).nullable(),
});

// Inferred TypeScript type
export type CreateUserRequestSchemaType = z.infer<
  typeof CreateUserRequestSchema
>;
```

## Basic Usage Patterns

### Form Validation

#### React Hook Form Example

```tsx
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import {
  CreateUserRequestSchema,
  CreateUserRequestSchemaType,
} from "@/types/zod-schemas";

function UserForm() {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<CreateUserRequestSchemaType>({
    resolver: zodResolver(CreateUserRequestSchema),
  });

  const onSubmit = async (data: CreateUserRequestSchemaType) => {
    // Data is automatically validated and typed
    console.log(data); // TypeScript knows the exact shape

    try {
      await fetch("/api/users", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
    } catch (error) {
      console.error("Failed to create user:", error);
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <div>
        <input {...register("name")} placeholder="Name" />
        {errors.name && <span>{errors.name.message}</span>}
      </div>

      <div>
        <input {...register("email")} type="email" placeholder="Email" />
        {errors.email && <span>{errors.email.message}</span>}
      </div>

      <div>
        <input
          {...register("password")}
          type="password"
          placeholder="Password"
        />
        {errors.password && <span>{errors.password.message}</span>}
      </div>

      <div>
        <input
          {...register("age", { valueAsNumber: true })}
          type="number"
          placeholder="Age"
        />
        {errors.age && <span>{errors.age.message}</span>}
      </div>

      <button type="submit">Create User</button>
    </form>
  );
}
```

#### Vue with VeeValidate

```vue
<template>
  <form @submit="onSubmit">
    <Field name="name" v-slot="{ field, errors }">
      <input v-bind="field" type="text" placeholder="Name" />
      <span v-if="errors.length">{{ errors[0] }}</span>
    </Field>

    <Field name="email" v-slot="{ field, errors }">
      <input v-bind="field" type="email" placeholder="Email" />
      <span v-if="errors.length">{{ errors[0] }}</span>
    </Field>

    <button type="submit">Submit</button>
  </form>
</template>

<script setup lang="ts">
import { Field, useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import {
  CreateUserRequestSchema,
  CreateUserRequestSchemaType,
} from "@/types/zod-schemas";

const { handleSubmit } = useForm({
  validationSchema: toTypedSchema(CreateUserRequestSchema),
});

const onSubmit = handleSubmit(async (values: CreateUserRequestSchemaType) => {
  // Submit form data
  await submitUser(values);
});
</script>
```

### API Data Validation

#### Validating API Responses

```typescript
import { UserSchema, UserSchemaType } from "@/types/zod-schemas";

async function fetchUser(id: string): Promise<UserSchemaType> {
  const response = await fetch(`/api/users/${id}`);
  const data = await response.json();

  // Validate API response matches expected schema
  const result = UserSchema.safeParse(data);

  if (!result.success) {
    console.error("Invalid API response:", result.error.issues);
    throw new Error("Invalid user data received from API");
  }

  return result.data; // Type-safe user data
}

// Usage
try {
  const user = await fetchUser("123");
  // user is properly typed as UserSchemaType
  console.log(user.name, user.email);
} catch (error) {
  // Handle validation or network errors
}
```

#### Validating API Requests

```typescript
import { CreatePostSchema, CreatePostSchemaType } from "@/types/zod-schemas";

async function createPost(postData: unknown): Promise<void> {
  // Validate data before sending to API
  const result = CreatePostSchema.safeParse(postData);

  if (!result.success) {
    // Handle validation errors
    const errors = result.error.issues.map((issue) => ({
      field: issue.path.join("."),
      message: issue.message,
    }));

    throw new ValidationError(errors);
  }

  // Send validated data to API
  await fetch("/api/posts", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(result.data),
  });
}
```

## Advanced Patterns

### Schema Composition

Combine schemas for complex validation:

```typescript
import { z } from "zod";
import { UserSchema, AddressSchema } from "@/types/zod-schemas";

// Extend existing schemas
const UserWithAddressSchema = UserSchema.extend({
  address: AddressSchema,
  billing_address: AddressSchema.optional(),
});

// Partial schemas for updates
const UpdateUserSchema = UserSchema.partial();

// Pick specific fields
const PublicUserSchema = UserSchema.pick({
  name: true,
  email: true,
});

// Omit sensitive fields
const SafeUserSchema = UserSchema.omit({
  password: true,
  password_confirmation: true,
});
```

### Conditional Validation

Handle complex conditional logic:

```typescript
import { z } from "zod";
import { CreateUserRequestSchema } from "@/types/zod-schemas";

const ConditionalUserSchema = CreateUserRequestSchema.extend({
  user_type: z.enum(["individual", "business"]),
}).refine(
  (data) => {
    if (data.user_type === "business") {
      return data.company_name && data.tax_id;
    }
    return data.first_name && data.last_name;
  },
  {
    message: "Business users must provide company name and tax ID",
    path: ["user_type"],
  }
);
```

### Array and Object Validation

Handle complex nested structures:

```typescript
import { z } from "zod";
import { ProductSchema, CategorySchema } from "@/types/zod-schemas";

// Array of schemas
const ProductListSchema = z.array(ProductSchema).min(1).max(100);

// Nested object validation
const OrderSchema = z.object({
  id: z.string().uuid(),
  items: z.array(
    z.object({
      product: ProductSchema,
      quantity: z.number().min(1),
      price: z.number().min(0),
    })
  ),
  total: z.number().min(0),
  shipping_address: AddressSchema,
  billing_address: AddressSchema.optional(),
});
```

### Transformation and Preprocessing

Transform data during validation:

```typescript
import { z } from "zod";

const PreprocessedUserSchema = z.object({
  email: z
    .string()
    .toLowerCase() // Transform to lowercase
    .email(),

  phone: z
    .string()
    .transform((val) => val.replace(/\D/g, "")) // Remove non-digits
    .regex(/^\d{10}$/, "Phone must be 10 digits"),

  birth_date: z
    .string()
    .transform((val) => new Date(val))
    .refine((date) => date < new Date(), "Birth date must be in the past"),
});

// Usage
const result = PreprocessedUserSchema.safeParse({
  email: "USER@EXAMPLE.COM",
  phone: "(555) 123-4567",
  birth_date: "1990-01-01",
});

if (result.success) {
  console.log(result.data.email); // 'user@example.com'
  console.log(result.data.phone); // '5551234567'
  console.log(result.data.birth_date); // Date object
}
```

## Error Handling

### Comprehensive Error Handling

```typescript
import { z } from "zod";
import { CreateUserRequestSchema } from "@/types/zod-schemas";

function handleValidationErrors(error: z.ZodError): Record<string, string> {
  const fieldErrors: Record<string, string> = {};

  error.issues.forEach((issue) => {
    const path = issue.path.join(".");

    switch (issue.code) {
      case z.ZodIssueCode.too_small:
        fieldErrors[path] = `Must be at least ${issue.minimum} characters`;
        break;
      case z.ZodIssueCode.too_big:
        fieldErrors[path] = `Must be no more than ${issue.maximum} characters`;
        break;
      case z.ZodIssueCode.invalid_string:
        if (issue.validation === "email") {
          fieldErrors[path] = "Please enter a valid email address";
        }
        break;
      default:
        fieldErrors[path] = issue.message;
    }
  });

  return fieldErrors;
}

// Usage
const result = CreateUserRequestSchema.safeParse(formData);
if (!result.success) {
  const errors = handleValidationErrors(result.error);
  setFormErrors(errors);
}
```

### Custom Error Messages

Override default error messages:

```typescript
const CustomUserSchema = CreateUserRequestSchema.extend({
  email: z
    .string()
    .min(1, "Email is required")
    .email("Please enter a valid email address")
    .max(255, "Email is too long"),

  password: z
    .string()
    .min(8, "Password must be at least 8 characters")
    .regex(
      /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/,
      "Password must contain uppercase, lowercase, and number"
    ),
});
```

## TypeScript Integration

### Type Guards

Create type guards using Zod schemas:

```typescript
import { UserSchemaType } from "@/types/zod-schemas";

function isUser(value: unknown): value is UserSchemaType {
  return UserSchema.safeParse(value).success;
}

// Usage
if (isUser(responseData)) {
  // responseData is now typed as UserSchemaType
  console.log(responseData.name);
}
```

### Generic Utilities

Create reusable utilities:

```typescript
import { z } from "zod";

type ApiResponse<T> = {
  data: T;
  message: string;
  status: "success" | "error";
};

function createApiResponseValidator<T extends z.ZodType>(
  dataSchema: T
): z.ZodType<ApiResponse<z.infer<T>>> {
  return z.object({
    data: dataSchema,
    message: z.string(),
    status: z.enum(["success", "error"]),
  });
}

// Usage
const UserResponseValidator = createApiResponseValidator(UserSchema);
type UserResponse = z.infer<typeof UserResponseValidator>;
```

### Branded Types

Create branded types for enhanced type safety:

```typescript
import { z } from "zod";

// Create branded types
const UserId = z.string().uuid().brand("UserId");
const Email = z.string().email().brand("Email");

type UserId = z.infer<typeof UserId>;
type Email = z.infer<typeof Email>;

// These are now distinct types
function getUser(id: UserId): Promise<User> {
  return fetch(`/api/users/${id}`).then((r) => r.json());
}

function sendEmail(to: Email, subject: string): void {
  // Send email logic
}

// Usage requires explicit validation
const userIdResult = UserId.safeParse("123e4567-e89b-12d3-a456-426614174000");
if (userIdResult.success) {
  getUser(userIdResult.data); // Type-safe
}
```

## Performance Optimization

### Schema Caching

Cache compiled schemas for better performance:

```typescript
import { z } from "zod";

class SchemaCache {
  private cache = new Map<string, z.ValidationSchema>();

  getSchema(
    key: string,
    factory: () => z.ValidationSchema
  ): z.ValidationSchema {
    if (!this.cache.has(key)) {
      this.cache.set(key, factory());
    }
    return this.cache.get(key)!;
  }
}

const schemaCache = new SchemaCache();

// Usage
const cachedUserSchema = schemaCache.getSchema(
  "CreateUser",
  () => CreateUserRequestSchema
);
```

### Lazy Validation

Validate only when needed:

```typescript
import { z } from "zod";

class LazyValidator<T> {
  private _result: z.SafeParseReturnType<unknown, T> | null = null;

  constructor(
    private schema: z.ValidationSchema<T>,
    private data: unknown
  ) {}

  get isValid(): boolean {
    if (!this._result) {
      this._result = this.schema.safeParse(this.data);
    }
    return this._result.success;
  }

  get data(): T | null {
    return this.isValid ? (this._result as z.SafeParseSuccess<T>).data : null;
  }

  get errors(): z.ZodIssue[] {
    return this.isValid
      ? []
      : (this._result as z.SafeParseError<unknown>).error.issues;
  }
}

// Usage
const validator = new LazyValidator(UserSchema, userData);

if (validator.isValid) {
  console.log(validator.data); // Type-safe access
} else {
  console.log(validator.errors);
}
```

## Testing

### Unit Testing Schemas

```typescript
import { describe, it, expect } from "vitest";
import { CreateUserRequestSchema } from "@/types/zod-schemas";

describe("CreateUserRequestSchema", () => {
  it("validates correct user data", () => {
    const validData = {
      name: "John Doe",
      email: "john@example.com",
      password: "password123",
      age: 25,
    };

    const result = CreateUserRequestSchema.safeParse(validData);
    expect(result.success).toBe(true);
  });

  it("rejects invalid email", () => {
    const invalidData = {
      name: "John Doe",
      email: "not-an-email",
      password: "password123",
    };

    const result = CreateUserRequestSchema.safeParse(invalidData);
    expect(result.success).toBe(false);

    if (!result.success) {
      expect(result.error.issues).toContainEqual(
        expect.objectContaining({
          path: ["email"],
          code: "invalid_string",
        })
      );
    }
  });
});
```

### Integration Testing

```typescript
import { expect } from "@playwright/test";
import { CreateUserRequestSchema } from "@/types/zod-schemas";

test("form validation works end-to-end", async ({ page }) => {
  await page.goto("/register");

  // Fill form with invalid data
  await page.fill('[name="email"]', "invalid-email");
  await page.click('button[type="submit"]');

  // Expect validation error
  await expect(page.locator(".error")).toContainText(
    "Please enter a valid email"
  );

  // Fill form with valid data
  const validData = {
    name: "John Doe",
    email: "john@example.com",
    password: "password123",
    age: 25,
  };

  // Verify data structure matches our schema
  const result = CreateUserRequestSchema.safeParse(validData);
  expect(result.success).toBe(true);
});
```

## Best Practices

### Always Use Safe Parsing

```typescript
// Good: Use safeParse for error handling
const result = UserSchema.safeParse(data);
if (result.success) {
  // Handle valid data
} else {
  // Handle validation errors
}

// Avoid: parse() throws on validation failure
try {
  const user = UserSchema.parse(data); // Can throw
} catch (error) {
  // Error handling
}
```

### Validate at Boundaries

```typescript
// Validate data when it enters your system
async function handleApiRequest(request: Request) {
  const body = await request.json();
  const result = CreateUserRequestSchema.safeParse(body);

  if (!result.success) {
    return new Response("Invalid data", { status: 400 });
  }

  // Work with validated data
  return processUser(result.data);
}
```

### Use Type-Safe APIs

```typescript
// Create type-safe API clients
class UserAPI {
  async createUser(data: CreateUserRequestSchemaType): Promise<UserSchemaType> {
    // Validate input
    const validatedData = CreateUserRequestSchema.parse(data);

    const response = await fetch("/api/users", {
      method: "POST",
      body: JSON.stringify(validatedData),
    });

    const userData = await response.json();

    // Validate response
    return UserSchema.parse(userData);
  }
}
```

## Next Steps

- [Advanced Features](../advanced/custom-extractors.md) - Extend the generator
- [Examples](../examples/real-world.md) - See real-world implementations
- [Reference](../reference/validation-rules.md) - Complete validation rule mapping
- [Troubleshooting](../reference/troubleshooting.md) - Common issues and solutions
