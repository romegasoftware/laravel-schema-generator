---
sidebar_position: 1
---

# FormRequest Examples

Real-world examples of using Laravel Zod Generator with FormRequest classes. These examples show common patterns and best practices for different types of applications.

## Basic CRUD Operations

### User Registration Form

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'date_of_birth' => 'required|date|before:18 years ago',
            'phone' => 'nullable|regex:/^\+?[\d\s\-\(\)]+$/',
            'terms_accepted' => 'required|accepted',
            'marketing_emails' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Please enter your first name',
            'last_name.required' => 'Please enter your last name',
            'email.unique' => 'This email address is already registered',
            'password.min' => 'Password must be at least 8 characters long',
            'date_of_birth.before' => 'You must be at least 18 years old to register',
            'phone.regex' => 'Please enter a valid phone number',
            'terms_accepted.accepted' => 'You must accept the terms and conditions',
        ];
    }
}
```

**Generated Zod Schema:**

```typescript
import { z } from "zod";

export const RegisterUserSchema = z.object({
  first_name: z.string().min(1, "Please enter your first name").max(100),
  last_name: z.string().min(1, "Please enter your last name").max(100),
  email: z.email().max(255),
  password: z.string().min(8, "Password must be at least 8 characters long"),
  password_confirmation: z.string(),
  date_of_birth: z.string().datetime(),
  phone: z
    .string()
    .regex(/^\+?[\d\s\-\(\)]+$/, "Please enter a valid phone number")
    .nullable(),
  terms_accepted: z.literal(true),
  marketing_emails: z.boolean().optional(),
});

export type RegisterUserSchemaType = z.infer<typeof RegisterUserSchema>;
```

### Product Management Form

```php
<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0.01|max:999999.99',
            'sku' => 'required|string|max:50|unique:products,sku',
            'category_id' => 'required|integer|exists:categories,id',
            'tags' => 'array|max:10',
            'tags.*' => 'string|max:50|regex:/^[a-zA-Z0-9\-_\s]+$/',
            'images' => 'array|max:5',
            'images.*' => 'image|mimes:jpeg,png,webp|max:2048',
            'specifications' => 'array',
            'specifications.weight' => 'nullable|numeric|min:0',
            'specifications.dimensions' => 'array',
            'specifications.dimensions.length' => 'nullable|numeric|min:0',
            'specifications.dimensions.width' => 'nullable|numeric|min:0',
            'specifications.dimensions.height' => 'nullable|numeric|min:0',
            'is_featured' => 'boolean',
            'status' => 'required|in:draft,active,inactive',
            'available_from' => 'nullable|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required',
            'price.min' => 'Price must be at least $0.01',
            'sku.unique' => 'This SKU is already in use',
            'tags.*.regex' => 'Tags can only contain letters, numbers, spaces, hyphens, and underscores',
            'images.*.max' => 'Each image must be less than 2MB',
        ];
    }
}
```

**Generated Zod Schema:**

```typescript
export const CreateProductSchema = z.object({
  name: z.string().min(1, "Product name is required").max(255),
  description: z.string().max(2000).nullable(),
  price: z.number().min(0.01, "Price must be at least $0.01").max(999999.99),
  sku: z.string().max(50),
  category_id: z.number().int(),
  tags: z
    .array(
      z
        .string()
        .max(50)
        .regex(
          /^[a-zA-Z0-9\-_\s]+$/,
          "Tags can only contain letters, numbers, spaces, hyphens, and underscores"
        )
    )
    .max(10)
    .optional(),
  images: z.array(z.unknown()).max(5).optional(), // File validation handled separately
  specifications: z
    .object({
      weight: z.number().min(0).nullable().optional(),
      dimensions: z
        .object({
          length: z.number().min(0).nullable().optional(),
          width: z.number().min(0).nullable().optional(),
          height: z.number().min(0).nullable().optional(),
        })
        .optional(),
    })
    .optional(),
  is_featured: z.boolean().optional(),
  status: z.enum(["draft", "active", "inactive"]),
  available_from: z.string().datetime().nullable(),
});
```

## E-commerce Examples

### Checkout Form

```php
<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class CheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Customer information
            'customer.email' => 'required|email|max:255',
            'customer.first_name' => 'required|string|max:100',
            'customer.last_name' => 'required|string|max:100',
            'customer.phone' => 'required|regex:/^\+?[\d\s\-\(\)]+$/',

            // Billing address
            'billing_address.street' => 'required|string|max:255',
            'billing_address.city' => 'required|string|max:100',
            'billing_address.state' => 'required|string|size:2',
            'billing_address.postal_code' => 'required|regex:/^\d{5}(-\d{4})?$/',
            'billing_address.country' => 'required|string|size:2',

            // Shipping address (optional, defaults to billing)
            'shipping_address.street' => 'nullable|string|max:255',
            'shipping_address.city' => 'nullable|string|max:100',
            'shipping_address.state' => 'nullable|string|size:2',
            'shipping_address.postal_code' => 'nullable|regex:/^\d{5}(-\d{4})?$/',
            'shipping_address.country' => 'nullable|string|size:2',

            // Order items
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:10',
            'items.*.price' => 'required|numeric|min:0',

            // Payment
            'payment.method' => 'required|in:credit_card,paypal,apple_pay',
            'payment.save_payment_method' => 'boolean',

            // Additional options
            'special_instructions' => 'nullable|string|max:500',
            'gift_wrap' => 'boolean',
            'newsletter_signup' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'customer.email.required' => 'Email address is required',
            'billing_address.postal_code.regex' => 'Please enter a valid postal code',
            'items.min' => 'Your cart must contain at least one item',
            'items.*.quantity.max' => 'Maximum quantity per item is 10',
        ];
    }
}
```

### Subscription Form

```php
<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class CreateSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|string|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly',
            'start_date' => 'nullable|date|after_or_equal:today',

            // Customer details
            'customer.email' => 'required|email|unique:customers,email',
            'customer.name' => 'required|string|max:255',
            'customer.company' => 'nullable|string|max:255',
            'customer.tax_id' => 'nullable|string|max:50',

            // Billing information
            'billing.payment_method' => 'required|in:credit_card,bank_transfer,invoice',
            'billing.currency' => 'required|string|size:3|in:USD,EUR,GBP,CAD',
            'billing.tax_exempt' => 'boolean',

            // Add-ons
            'addons' => 'array',
            'addons.*' => 'string|exists:subscription_addons,id',

            // Promotional codes
            'coupon_code' => 'nullable|string|max:50|exists:coupons,code',

            // Agreement
            'terms_accepted' => 'required|accepted',
            'data_processing_consent' => 'required|accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.exists' => 'Selected plan is not available',
            'customer.email.unique' => 'An account with this email already exists',
            'billing.currency.in' => 'Selected currency is not supported',
            'coupon_code.exists' => 'Invalid coupon code',
            'terms_accepted.accepted' => 'You must accept the terms of service',
            'data_processing_consent.accepted' => 'Data processing consent is required',
        ];
    }
}
```

## Content Management Examples

### Blog Post Form

```php
<?php

namespace App\Http\Requests\Blog;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class CreatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Basic post information
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:posts,slug|regex:/^[a-z0-9-]+$/',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string|min:100',

            // Post metadata
            'status' => 'required|in:draft,published,scheduled,archived',
            'published_at' => 'nullable|date|after_or_equal:now',
            'featured_image' => 'nullable|image|mimes:jpeg,png,webp|max:2048',

            // Categorization
            'category_id' => 'required|integer|exists:categories,id',
            'tags' => 'array|max:10',
            'tags.*' => 'string|max:50',

            // SEO
            'seo.meta_title' => 'nullable|string|max:60',
            'seo.meta_description' => 'nullable|string|max:160',
            'seo.meta_keywords' => 'nullable|string|max:255',
            'seo.canonical_url' => 'nullable|url',
            'seo.noindex' => 'boolean',
            'seo.nofollow' => 'boolean',

            // Social media
            'social.og_title' => 'nullable|string|max:60',
            'social.og_description' => 'nullable|string|max:160',
            'social.og_image' => 'nullable|url',
            'social.twitter_card' => 'nullable|in:summary,summary_large_image',

            // Publishing options
            'allow_comments' => 'boolean',
            'is_featured' => 'boolean',
            'password' => 'nullable|string|min:6',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Post title is required',
            'slug.unique' => 'This slug is already in use',
            'slug.regex' => 'Slug can only contain lowercase letters, numbers, and hyphens',
            'content.min' => 'Post content must be at least 100 characters long',
            'seo.meta_title.max' => 'Meta title should not exceed 60 characters for optimal SEO',
            'seo.meta_description.max' => 'Meta description should not exceed 160 characters',
        ];
    }
}
```

## API Examples

### API Resource Creation

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema(name: 'ApiCreateUser')]
class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // User data
            'username' => 'required|string|alpha_dash|min:3|max:50|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:12|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/',

            // Profile data
            'profile.first_name' => 'required|string|max:100',
            'profile.last_name' => 'required|string|max:100',
            'profile.avatar_url' => 'nullable|url',
            'profile.bio' => 'nullable|string|max:500',
            'profile.location' => 'nullable|string|max:100',
            'profile.website' => 'nullable|url',

            // Preferences
            'preferences.timezone' => 'required|string|in:' . implode(',', timezone_identifiers_list()),
            'preferences.language' => 'required|string|size:2|in:en,es,fr,de,it,pt,ja,ko,zh',
            'preferences.theme' => 'required|in:light,dark,auto',
            'preferences.notifications.email' => 'boolean',
            'preferences.notifications.push' => 'boolean',
            'preferences.notifications.sms' => 'boolean',

            // Role and permissions (admin only)
            'role' => 'nullable|string|exists:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',

            // API access
            'api_access.enabled' => 'boolean',
            'api_access.rate_limit' => 'nullable|integer|min:1|max:10000',
            'api_access.allowed_ips' => 'array',
            'api_access.allowed_ips.*' => 'ip',
        ];
    }

    public function messages(): array
    {
        return [
            'username.alpha_dash' => 'Username can only contain letters, numbers, dashes, and underscores',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character',
            'preferences.timezone.in' => 'Invalid timezone selected',
            'preferences.language.in' => 'Unsupported language',
            'api_access.rate_limit.max' => 'Rate limit cannot exceed 10,000 requests per hour',
        ];
    }
}
```

### Batch Operations

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class BatchUpdateUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'users' => 'required|array|min:1|max:100',
            'users.*.id' => 'required|integer|exists:users,id',
            'users.*.email' => 'nullable|email|max:255',
            'users.*.status' => 'nullable|in:active,inactive,suspended',
            'users.*.role' => 'nullable|string|exists:roles,name',
            'users.*.profile' => 'nullable|array',
            'users.*.profile.first_name' => 'nullable|string|max:100',
            'users.*.profile.last_name' => 'nullable|string|max:100',
            'users.*.preferences' => 'nullable|array',
            'users.*.preferences.notifications' => 'nullable|boolean',
            'users.*.preferences.theme' => 'nullable|in:light,dark,auto',

            // Batch operation options
            'options.send_notification' => 'boolean',
            'options.skip_validation' => 'boolean',
            'options.dry_run' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'users.max' => 'Cannot update more than 100 users in a single batch',
            'users.*.id.exists' => 'One or more user IDs do not exist',
            'users.*.email.email' => 'Invalid email format in batch data',
        ];
    }
}
```

## Advanced Patterns

### Multi-Step Form

```php
<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class OnboardingStepRequest extends FormRequest
{
    public function rules(): array
    {
        $step = $this->input('step', 1);

        $baseRules = [
            'step' => 'required|integer|in:1,2,3,4',
        ];

        return match ($step) {
            1 => array_merge($baseRules, [
                // Personal information
                'personal.first_name' => 'required|string|max:100',
                'personal.last_name' => 'required|string|max:100',
                'personal.email' => 'required|email|unique:users,email',
                'personal.phone' => 'required|regex:/^\+?[\d\s\-\(\)]+$/',
            ]),

            2 => array_merge($baseRules, [
                // Company information
                'company.name' => 'required|string|max:255',
                'company.size' => 'required|in:1-10,11-50,51-200,201-1000,1000+',
                'company.industry' => 'required|string|exists:industries,slug',
                'company.website' => 'nullable|url',
            ]),

            3 => array_merge($baseRules, [
                // Preferences
                'preferences.plan' => 'required|string|exists:plans,id',
                'preferences.features' => 'required|array|min:1',
                'preferences.features.*' => 'string|exists:features,id',
                'preferences.integrations' => 'array',
                'preferences.integrations.*' => 'string|exists:integrations,id',
            ]),

            4 => array_merge($baseRules, [
                // Final confirmation
                'confirmation.terms_accepted' => 'required|accepted',
                'confirmation.privacy_accepted' => 'required|accepted',
                'confirmation.marketing_consent' => 'boolean',
                'confirmation.setup_call' => 'boolean',
            ]),

            default => $baseRules,
        };
    }

    public function messages(): array
    {
        return [
            'personal.email.unique' => 'This email is already registered',
            'company.size.in' => 'Please select a valid company size',
            'preferences.features.min' => 'Please select at least one feature',
            'confirmation.terms_accepted.accepted' => 'You must accept the terms of service',
        ];
    }
}
```

### Conditional Validation

```php
<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class FlexibleOrderRequest extends FormRequest
{
    public function rules(): array
    {
        $orderType = $this->input('order_type', 'standard');
        $paymentMethod = $this->input('payment.method');

        $rules = [
            'order_type' => 'required|in:standard,express,scheduled,subscription',

            // Common fields
            'customer_id' => 'required|integer|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',

            // Payment
            'payment.method' => 'required|in:credit_card,paypal,bank_transfer,crypto',
            'payment.currency' => 'required|string|size:3',
        ];

        // Order type specific rules
        if ($orderType === 'express') {
            $rules['express_fee_accepted'] = 'required|accepted';
            $rules['delivery_window'] = 'required|in:2h,4h,same_day';
        }

        if ($orderType === 'scheduled') {
            $rules['scheduled_date'] = 'required|date|after:tomorrow';
            $rules['scheduled_time'] = 'required|date_format:H:i';
        }

        if ($orderType === 'subscription') {
            $rules['subscription.frequency'] = 'required|in:weekly,biweekly,monthly';
            $rules['subscription.duration'] = 'nullable|integer|min:1|max:24';
        }

        // Payment method specific rules
        if ($paymentMethod === 'bank_transfer') {
            $rules['bank_details.account_number'] = 'required|string';
            $rules['bank_details.routing_number'] = 'required|string';
        }

        if ($paymentMethod === 'crypto') {
            $rules['crypto.currency'] = 'required|in:BTC,ETH,LTC';
            $rules['crypto.wallet_address'] = 'required|string';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'express_fee_accepted.accepted' => 'You must accept the express delivery fee',
            'scheduled_date.after' => 'Scheduled orders must be at least 2 days in advance',
            'crypto.wallet_address.required' => 'Cryptocurrency wallet address is required',
        ];
    }
}
```

## Usage with Generated Schemas

### React Hook Form Integration

```tsx
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import {
  RegisterUserSchema,
  RegisterUserSchemaType,
} from "@/types/zod-schemas";

export function RegistrationForm() {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterUserSchemaType>({
    resolver: zodResolver(RegisterUserSchema),
  });

  const onSubmit = async (data: RegisterUserSchemaType) => {
    try {
      const response = await fetch("/api/auth/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      if (response.ok) {
        // Handle successful registration
        router.push("/dashboard");
      }
    } catch (error) {
      console.error("Registration failed:", error);
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      <div>
        <input
          {...register("first_name")}
          placeholder="First Name"
          className="w-full px-3 py-2 border rounded"
        />
        {errors.first_name && (
          <p className="text-red-500 text-sm">{errors.first_name.message}</p>
        )}
      </div>

      <div>
        <input
          {...register("email")}
          type="email"
          placeholder="Email"
          className="w-full px-3 py-2 border rounded"
        />
        {errors.email && (
          <p className="text-red-500 text-sm">{errors.email.message}</p>
        )}
      </div>

      <div>
        <input
          {...register("password")}
          type="password"
          placeholder="Password"
          className="w-full px-3 py-2 border rounded"
        />
        {errors.password && (
          <p className="text-red-500 text-sm">{errors.password.message}</p>
        )}
      </div>

      <div className="flex items-center">
        <input
          {...register("terms_accepted")}
          type="checkbox"
          className="mr-2"
        />
        <label>I accept the terms and conditions</label>
        {errors.terms_accepted && (
          <p className="text-red-500 text-sm ml-2">
            {errors.terms_accepted.message}
          </p>
        )}
      </div>

      <button
        type="submit"
        className="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700"
      >
        Register
      </button>
    </form>
  );
}
```

### Vue Composition API

```vue
<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <div>
      <input
        v-model="form.name"
        type="text"
        placeholder="Product Name"
        class="w-full px-3 py-2 border rounded"
        :class="{ 'border-red-500': errors.name }"
      />
      <p v-if="errors.name" class="text-red-500 text-sm">{{ errors.name }}</p>
    </div>

    <div>
      <input
        v-model.number="form.price"
        type="number"
        step="0.01"
        placeholder="Price"
        class="w-full px-3 py-2 border rounded"
        :class="{ 'border-red-500': errors.price }"
      />
      <p v-if="errors.price" class="text-red-500 text-sm">{{ errors.price }}</p>
    </div>

    <div>
      <select
        v-model="form.status"
        class="w-full px-3 py-2 border rounded"
        :class="{ 'border-red-500': errors.status }"
      >
        <option value="">Select Status</option>
        <option value="draft">Draft</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <p v-if="errors.status" class="text-red-500 text-sm">
        {{ errors.status }}
      </p>
    </div>

    <button
      type="submit"
      class="w-full bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700"
      :disabled="isSubmitting"
    >
      {{ isSubmitting ? "Creating..." : "Create Product" }}
    </button>
  </form>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import {
  CreateProductSchema,
  CreateProductSchemaType,
} from "@/types/zod-schemas";

const form = reactive<Partial<CreateProductSchemaType>>({
  name: "",
  price: 0,
  status: "",
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const handleSubmit = async () => {
  errors.value = {};

  const result = CreateProductSchema.safeParse(form);

  if (!result.success) {
    const fieldErrors: Record<string, string> = {};
    result.error.errors.forEach((error) => {
      fieldErrors[error.path[0]] = error.message;
    });
    errors.value = fieldErrors;
    return;
  }

  isSubmitting.value = true;

  try {
    const response = await fetch("/api/products", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(result.data),
    });

    if (response.ok) {
      // Handle success
      console.log("Product created successfully");
    }
  } catch (error) {
    console.error("Failed to create product:", error);
  } finally {
    isSubmitting.value = false;
  }
};
</script>
```

## Best Practices

### Keep FormRequests Focused

Create separate FormRequest classes for different operations:

```php
// Good: Separate classes for different operations
CreateUserRequest
UpdateUserRequest
DeleteUserRequest

// Avoid: One class trying to handle everything
UserRequest
```

### Use Descriptive Schema Names

```php
// Good: Descriptive names
#[ValidationSchema(name: 'UserRegistrationForm')]
#[ValidationSchema(name: 'ProductCreationForm')]

// Avoid: Generic names
#[ValidationSchema(name: 'UserForm')]
#[ValidationSchema(name: 'Form')]
```

### Group Related Validations

Use nested arrays for related data:

```php
// Good: Grouped related fields
'billing_address.street' => 'required|string',
'billing_address.city' => 'required|string',
'shipping_address.street' => 'nullable|string',

// Avoid: Flat structure for related data
'billing_street' => 'required|string',
'billing_city' => 'required|string',
'shipping_street' => 'nullable|string',
```

### Provide Meaningful Error Messages

```php
public function messages(): array
{
    return [
        'email.unique' => 'This email address is already registered',
        'password.min' => 'Password must be at least 8 characters long',
        'terms_accepted.accepted' => 'You must accept the terms and conditions',
    ];
}
```

### Test Your Forms

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Requests\Auth\RegisterUserRequest;

class RegistrationFormTest extends TestCase
{
    public function test_registration_form_validation(): void
    {
        $validData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'securePassword123',
            'password_confirmation' => 'securePassword123',
            'terms_accepted' => true,
        ];

        $request = new RegisterUserRequest();
        $validator = validator($validData, $request->rules());

        $this->assertTrue($validator->passes());
    }
}
```

## Next Steps

- [Spatie Data Examples](./spatie-data.md) - Using with Spatie Data classes
- [Custom Validation Examples](./custom-validation.md) - Advanced validation patterns
- [Real-world Examples](./real-world.md) - Complete application examples
- [Basic Usage](../usage/basic-usage.md) - Learn the fundamentals
