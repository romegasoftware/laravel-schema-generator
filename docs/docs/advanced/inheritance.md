---
sidebar_position: 3
---

# Validation Inheritance

Learn how to reuse validation rules across different classes using the `#[InheritValidationFrom]` attribute. This powerful feature helps you maintain consistency and reduce duplication in your validation logic.

## Why Use Validation Inheritance?

- **DRY Principle**: Don't repeat validation rules across similar classes
- **Consistency**: Ensure the same validation logic is applied everywhere
- **Maintainability**: Change validation rules in one place
- **Reusability**: Share common validation patterns across your application
- **Domain Modeling**: Create reusable validation components for your domain

## Basic Inheritance

### Simple Field Inheritance

```php
<?php

// Define reusable validation rules
class CommonValidations
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'phone' => 'nullable|regex:/^\+?[\d\s\-\(\)]+$/',
            'postal_code' => 'required|regex:/^\d{5}(-\d{4})?$/',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Please enter a valid email address',
            'phone.regex' => 'Please enter a valid phone number',
            'postal_code.regex' => 'Please enter a valid postal code (12345 or 12345-6789)',
        ];
    }
}

// Use inherited validation
#[ValidationSchema]
class UserData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[InheritValidationFrom(CommonValidations::class, 'email')]
        public string $email,

        #[InheritValidationFrom(CommonValidations::class, 'phone')]
        public ?string $phone,
    ) {}
}
```

Generated TypeScript:

```typescript
export const UserSchema = z.object({
  name: z.string().min(1).max(255),
  email: z.email("Please enter a valid email address").max(255),
  phone: z
    .string()
    .regex(/^\+?[\d\s\-\(\)]+$/, "Please enter a valid phone number")
    .nullable(),
});
```

## Advanced Inheritance Patterns

### Domain-Specific Validation Libraries

Create validation libraries for different domains:

```php
<?php

namespace App\Validation\Domains;

class UserValidations
{
    public function rules(): array
    {
        return [
            'username' => 'required|string|alpha_dash|min:3|max:50|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:12|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'username.alpha_dash' => 'Username can only contain letters, numbers, dashes, and underscores',
            'username.unique' => 'This username is already taken',
            'password.min' => 'Password must be at least 12 characters long',
            'password.regex' => 'Password must contain uppercase, lowercase, number, and special character',
        ];
    }
}

class AddressValidations
{
    public function rules(): array
    {
        return [
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'postal_code' => 'required|regex:/^\d{5}(-\d{4})?$/',
            'country' => 'required|string|size:2',
        ];
    }

    public function messages(): array
    {
        return [
            'state.size' => 'State must be a 2-letter code',
            'postal_code.regex' => 'Please enter a valid postal code',
            'country.size' => 'Country must be a 2-letter code',
        ];
    }
}

class ContactValidations
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'phone' => 'nullable|regex:/^\+?1?[\d\s\-\(\)]{10,}$/',
            'website' => 'nullable|url|max:255',
        ];
    }
}
```

### Composed Data Classes

Combine multiple validation sources:

```php
<?php

#[ValidationSchema]
class UserProfileData extends Data
{
    public function __construct(
        // Basic user information
        #[InheritValidationFrom(UserValidations::class, 'first_name')]
        public string $first_name,

        #[InheritValidationFrom(UserValidations::class, 'last_name')]
        public string $last_name,

        #[InheritValidationFrom(UserValidations::class, 'username')]
        public string $username,

        // Contact information
        #[InheritValidationFrom(ContactValidations::class, 'email')]
        public string $email,

        #[InheritValidationFrom(ContactValidations::class, 'phone')]
        public ?string $phone,

        #[InheritValidationFrom(ContactValidations::class, 'website')]
        public ?string $website,
    ) {}
}

#[ValidationSchema]
class UserAddressData extends Data
{
    public function __construct(
        #[InheritValidationFrom(UserValidations::class, 'username')]
        public string $username,

        // Address fields
        #[InheritValidationFrom(AddressValidations::class, 'street')]
        public string $street,

        #[InheritValidationFrom(AddressValidations::class, 'city')]
        public string $city,

        #[InheritValidationFrom(AddressValidations::class, 'state')]
        public string $state,

        #[InheritValidationFrom(AddressValidations::class, 'postal_code')]
        public string $postal_code,

        #[InheritValidationFrom(AddressValidations::class, 'country')]
        public string $country = 'US',
    ) {}
}
```

## Field Mapping and Transformation

### Different Field Names

Inherit validation from a field with a different name:

```php
<?php

class EmailValidations
{
    public function rules(): array
    {
        return [
            'primary_email' => 'required|email|max:255|unique:users,email',
            'backup_email' => 'nullable|email|max:255|different:primary_email',
        ];
    }

    public function messages(): array
    {
        return [
            'primary_email.unique' => 'This email address is already registered',
            'backup_email.different' => 'Backup email must be different from primary email',
        ];
    }
}

#[ValidationSchema]
class UserRegistrationData extends Data
{
    public function __construct(
        // Map 'primary_email' validation to 'email' field
        #[InheritValidationFrom(EmailValidations::class, 'primary_email')]
        public string $email,

        // Map 'backup_email' validation to 'recovery_email' field
        #[InheritValidationFrom(EmailValidations::class, 'backup_email')]
        public ?string $recovery_email,
    ) {}
}
```

### Complex Field Mapping

```php
<?php

class ProductValidations
{
    public function rules(): array
    {
        return [
            'item_name' => 'required|string|max:255',
            'item_price' => 'required|numeric|min:0.01|max:99999.99',
            'item_description' => 'nullable|string|max:1000',
            'item_category_id' => 'required|integer|exists:categories,id',
        ];
    }
}

#[ValidationSchema]
class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Direct field mapping
            'name' => $this->getInheritedRules(ProductValidations::class, 'item_name'),
            'price' => $this->getInheritedRules(ProductValidations::class, 'item_price'),
            'description' => $this->getInheritedRules(ProductValidations::class, 'item_description'),
            'category_id' => $this->getInheritedRules(ProductValidations::class, 'item_category_id'),

            // Additional fields specific to creation
            'sku' => 'required|string|unique:products,sku',
            'is_featured' => 'boolean',
        ];
    }
}
```

## Hierarchical Inheritance

### Multi-Level Inheritance

```php
<?php

// Base validation rules
class BaseValidations
{
    public function rules(): array
    {
        return [
            'id' => 'required|uuid',
            'created_at' => 'required|date',
            'updated_at' => 'required|date|after_or_equal:created_at',
        ];
    }
}

// Content-specific validations
class ContentValidations
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:contents,slug',
            'body' => 'nullable|string',
            'status' => 'required|in:draft,published,archived',
        ];
    }
}

// SEO-specific validations
class SeoValidations
{
    public function rules(): array
    {
        return [
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'meta_keywords' => 'nullable|string|max:255',
        ];
    }
}

// Composed article validation
#[ValidationSchema]
class ArticleData extends Data
{
    public function __construct(
        // Base entity fields
        #[InheritValidationFrom(BaseValidations::class, 'id')]
        public string $id,

        #[InheritValidationFrom(BaseValidations::class, 'created_at')]
        public string $created_at,

        #[InheritValidationFrom(BaseValidations::class, 'updated_at')]
        public string $updated_at,

        // Content fields
        #[InheritValidationFrom(ContentValidations::class, 'title')]
        public string $title,

        #[InheritValidationFrom(ContentValidations::class, 'slug')]
        public string $slug,

        #[InheritValidationFrom(ContentValidations::class, 'body')]
        public ?string $body,

        #[InheritValidationFrom(ContentValidations::class, 'status')]
        public string $status,

        // SEO fields
        #[InheritValidationFrom(SeoValidations::class, 'meta_title')]
        public ?string $meta_title,

        #[InheritValidationFrom(SeoValidations::class, 'meta_description')]
        public ?string $meta_description,

        #[InheritValidationFrom(SeoValidations::class, 'meta_keywords')]
        public ?string $meta_keywords,

        // Article-specific fields
        #[Required, StringType, Max(100)]
        public string $author,

        #[Nullable, Integer, Min(0)]
        public ?int $read_time,
    ) {}
}
```

## Dynamic Inheritance

### Context-Aware Validation

```php
<?php

class DynamicUserValidations
{
    public function rules(): array
    {
        $currentUser = auth()->user();
        $isAdmin = $currentUser?->hasRole('admin');

        return [
            'email' => $isAdmin
                ? 'required|email|max:255' // Admins can use any email
                : 'required|email|max:255|ends_with:@company.com', // Regular users must use company email

            'role' => $isAdmin
                ? 'required|in:user,editor,admin' // Admins can assign any role
                : 'required|in:user,editor', // Regular users can't create admins
        ];
    }

    public function messages(): array
    {
        return [
            'email.ends_with' => 'Please use your company email address',
            'role.in' => 'Invalid role selection',
        ];
    }
}

#[ValidationSchema(name: 'CreateUser')]
class CreateUserData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[InheritValidationFrom(DynamicUserValidations::class, 'email')]
        public string $email,

        #[InheritValidationFrom(DynamicUserValidations::class, 'role')]
        public string $role,
    ) {}
}
```

### Environment-Specific Validation

```php
<?php

class EnvironmentValidations
{
    public function rules(): array
    {
        $isDev = app()->environment('local', 'development');

        return [
            'api_key' => $isDev
                ? 'required|string|min:10' // Relaxed for development
                : 'required|string|min:32|regex:/^[a-zA-Z0-9]+$/', // Strict for production

            'webhook_url' => $isDev
                ? 'nullable|url' // Optional for development
                : 'required|url|starts_with:https://', // Required HTTPS for production
        ];
    }
}
```

## Validation Mixins

### Trait-Based Inheritance

```php
<?php

trait TimestampValidationMixin
{
    public function getTimestampRules(): array
    {
        return [
            'created_at' => 'required|date',
            'updated_at' => 'required|date|after_or_equal:created_at',
        ];
    }
}

trait SoftDeleteValidationMixin
{
    public function getSoftDeleteRules(): array
    {
        return [
            'deleted_at' => 'nullable|date',
        ];
    }
}

class MixinValidations
{
    use TimestampValidationMixin, SoftDeleteValidationMixin;

    public function rules(): array
    {
        return array_merge(
            $this->getTimestampRules(),
            $this->getSoftDeleteRules(),
            [
                'status' => 'required|in:active,inactive',
            ]
        );
    }
}
```

## Error Handling and Debugging

### Inheritance Resolution

```php
<?php

// Debug inheritance resolution
class DebugInheritanceExtractor implements ExtractorInterface
{
    public function extract(ReflectionClass $class): array
    {
        $properties = [];

        foreach ($class->getProperties() as $property) {
            $inheritanceAttrs = $property->getAttributes(InheritValidationFrom::class);

            if (!empty($inheritanceAttrs)) {
                $attr = $inheritanceAttrs[0]->newInstance();

                logger()->debug('Resolving inheritance', [
                    'target_class' => $class->getName(),
                    'target_property' => $property->getName(),
                    'source_class' => $attr->className,
                    'source_field' => $attr->fieldName,
                ]);

                try {
                    $sourceRules = $this->resolveInheritedRules($attr);
                    logger()->debug('Inheritance resolved', [
                        'rules' => $sourceRules,
                    ]);
                } catch (Exception $e) {
                    logger()->error('Inheritance failed', [
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
        }

        return $properties;
    }
}
```

### Common Inheritance Errors

#### Class Not Found

```php
// Error: Class 'App\Validation\NonExistentClass' not found
#[InheritValidationFrom(NonExistentClass::class, 'field')]
public string $field;

// Solution: Check class name and namespace
#[InheritValidationFrom(\App\Validation\ExistingClass::class, 'field')]
public string $field;
```

#### Field Not Found

```php
// Error: Field 'non_existent_field' not found in validation rules
#[InheritValidationFrom(ExistingClass::class, 'non_existent_field')]
public string $field;

// Solution: Check available fields in the source class
class ExistingClass
{
    public function rules(): array
    {
        return [
            'existing_field' => 'required|string', // Use this field name
        ];
    }
}
```

#### Circular Dependencies

```php
// Error: Circular dependency detected
class ClassA
{
    public function rules(): array
    {
        return [
            'field' => $this->getInheritedRules(ClassB::class, 'field'),
        ];
    }
}

class ClassB
{
    public function rules(): array
    {
        return [
            'field' => $this->getInheritedRules(ClassA::class, 'field'), // Circular!
        ];
    }
}
```

## Testing Inheritance

### Unit Tests for Inherited Validation

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ValidationInheritanceTest extends TestCase
{
    public function test_inheritance_attribute_resolution(): void
    {
        $reflection = new ReflectionClass(UserProfileData::class);
        $emailProperty = $reflection->getProperty('email');

        $attributes = $emailProperty->getAttributes(InheritValidationFrom::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertEquals(ContactValidations::class, $instance->className);
        $this->assertEquals('email', $instance->fieldName);
    }

    public function test_inherited_rules_are_applied(): void
    {
        // Test that the generated schema includes inherited validation
        $generatedSchema = $this->generateSchemaFor(UserProfileData::class);

        $this->assertStringContainsString('email', $generatedSchema);
        $this->assertStringContainsString('z.email()', $generatedSchema);
        $this->assertStringContainsString('max(255)', $generatedSchema);
    }

    public function test_inherited_messages_are_preserved(): void
    {
        $generatedSchema = $this->generateSchemaFor(UserProfileData::class);

        $this->assertStringContainsString('Please enter a valid email address', $generatedSchema);
    }
}
```

### Integration Tests

```php
<?php

namespace Tests\Integration;

use Tests\TestCase;

class InheritanceIntegrationTest extends TestCase
{
    public function test_full_inheritance_workflow(): void
    {
        // Generate schemas with inheritance
        $this->artisan('schema:generate --dry-run')
             ->expectsOutputToContain('UserProfileSchema')
             ->expectsOutputToContain('Generated 1 schemas successfully!');

        // Verify the generated TypeScript is valid
        $output = $this->getGeneratedOutput();

        // Should include inherited email validation
        $this->assertStringContainsString('email: z.email(', $output);
        $this->assertStringContainsString('.max(255)', $output);

        // Should include custom error messages
        $this->assertStringContainsString('Please enter a valid email address', $output);
    }
}
```

## Best Practices

### Organize Validation Classes by Domain

```php
// Group related validations
namespace App\Validation\Domains\User;
namespace App\Validation\Domains\Product;
namespace App\Validation\Domains\Order;
```

### Use Descriptive Names

```php
// Good: Descriptive names
class UserAccountValidations
class ProductCatalogValidations
class OrderProcessingValidations

// Avoid: Generic names
class Validations
class Rules
class CommonStuff
```

### Keep Inheritance Chains Shallow

```php
// Good: Direct inheritance
#[InheritValidationFrom(UserValidations::class, 'email')]

// Avoid: Deep chains
#[InheritValidationFrom(Level1::class, 'field')] // which inherits from Level2, etc.
```

### Document Inheritance Relationships

```php
/**
 * User profile validation that inherits common field validations.
 *
 * Inherits from:
 * - UserValidations: username, email, password rules
 * - ContactValidations: phone, website rules
 * - AddressValidations: address-related rules
 */
#[ValidationSchema]
class UserProfileData extends Data
{
    // Properties with inheritance attributes...
}
```

### Test Inheritance Thoroughly

Always test that:

- Inheritance resolution works correctly
- Generated schemas include inherited rules
- Error messages are preserved
- Field mapping works as expected

## Next Steps

- [Integration](./integration.md) - Integrate with existing TypeScript workflows
- [Examples](../examples/spatie-data.md) - See inheritance in action
- [Reference](../reference/troubleshooting.md) - Debug inheritance issues
- [Custom Extractors](./custom-extractors.md) - Handle complex inheritance patterns
