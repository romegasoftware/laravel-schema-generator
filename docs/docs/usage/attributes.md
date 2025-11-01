---
sidebar_position: 2
---

# Using Attributes

Laravel Zod Generator provides powerful PHP attributes to control schema generation. Learn how to use these attributes to customize your generated Zod schemas.

## ValidationSchema Attribute

The primary attribute that marks classes for schema generation.

### Basic Usage

```php
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class CreateUserRequest extends FormRequest
{
    // Schema will be generated with name "CreateUserRequestSchema"
}
```

### Custom Schema Name

```php
#[ValidationSchema(name: 'UserRegistrationForm')]
class CreateUserRequest extends FormRequest
{
    // Schema will be generated with name "UserRegistrationFormSchema"
}
```

### Multiple Attributes

You can add multiple `#[ValidationSchema]` attributes with different names:

```php
#[ValidationSchema(name: 'CreateUser')]
#[ValidationSchema(name: 'RegisterUser')]
class CreateUserRequest extends FormRequest
{
    // Generates both CreateUserRequestSchema and RegisterUserSchema
}
```

## InheritValidationFrom Attribute

Reuse validation rules from other classes. This is particularly powerful with Spatie Data classes.

### Basic Inheritance

```php
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;

class PostalCodeValidator
{
    public function rules(): array
    {
        return [
            'postal_code' => 'required|regex:/^\d{5}(-\d{4})?$/',
        ];
    }

    public function messages(): array
    {
        return [
            'postal_code.regex' => 'Invalid postal code format (use 12345 or 12345-6789)',
        ];
    }
}

#[ValidationSchema]
class AddressData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $street,

        #[InheritValidationFrom(PostalCodeValidator::class, 'postal_code')]
        public string $postal_code,
    ) {}
}
```

By default the attribute also applies the inherited rules when the Data class validates incoming payloads. If you only need the rules for schema generation, disable runtime enforcement with the `enforceRuntime` flag:

```php
#[InheritValidationFrom(PostalCodeValidator::class, enforceRuntime: false)]
public ?string $postal_code,
```

### Runtime Validation

When runtime enforcement is enabled (default), the attribute hooks into Spatie Data’s validator so any request payload must satisfy the inherited rules. This keeps schema generation and runtime validation perfectly in sync.

### Combining with Local Rules

Inherited rules are merged with rules defined on the consuming class. If you add stricter requirements locally—such as making a field required—they stack on top of the inherited rules:

```php
#[ValidationSchema]
class FranchiseUpdateData extends Data
{
    public function __construct(
        #[InheritValidationFrom(PostalCodeValidator::class, 'postal_code')]
        public ?string $postal_code,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'postal_code' => ['required'],
        ];
    }
}
```

The generated schema now reflects both sets of rules and the runtime validator enforces them together.

### Field Mapping

You can inherit validation from a differently named field:

```php
class UserEmailValidator
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
        ];
    }
}

#[ValidationSchema]
class UpdateUserData extends Data
{
    public function __construct(
        // Inherit 'email' rules for the 'new_email' field
        #[InheritValidationFrom(UserEmailValidator::class, 'email')]
        public string $new_email,
    ) {}
}
```

### Importing an Entire Validator

When working with collections of nested data, you can import every rule from a source class without listing each property. Apply `#[InheritValidationFrom(SomeData::class)]` to a collection or nested object and the generator will merge the full schema.

```php
use Closure;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use Closure;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaRule;
use Spatie\LaravelData\Attributes\Validation\DataCollectionOf;
use Spatie\LaravelData\Support\Validation\ValidationContext;

#[ValidationSchema]
final class OrderCreateRequestData extends Data
{
    public function __construct(
        #[Required]
        #[DataCollectionOf(OrderItemRequestData::class), InheritValidationFrom(OrderItemRequestData::class)]
        public DataCollection $items,
    ) {}

    public static function rules(?ValidationContext $validationContext = null): array
    {
        return [
            'items' => [
                'required',
                'array',
                SchemaRule::make(
                    static function (string $attribute, mixed $value, Closure $fail, string $message): void {
                        if (collect($value)->sum('quantity') < 12) {
                            $fail($message);
                        }
                    }
                )
                    ->append(static function (string $encodedMessage): string {
                        return <<<ZOD
                            .superRefine((items, ctx) => {
                                const total = items.reduce((sum, item) => sum + item.quantity, 0);
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
        ];
    }
}

#[ValidationSchema]
final class OrderItemRequestData extends Data
{
    public function __construct(
        #[Required, IntegerType]
        public int $item_id,

        #[Required, IntegerType, Min(1), MultipleOf(3)]
        public int $quantity,
    ) {}
}
```

Generates:

```typescript
export const OrderCreateRequestDataSchema = z.object({
  items: z.array(OrderItemRequestDataSchema).superRefine((items, ctx) => {
    const total = items.reduce((sum, item) => sum + item.quantity, 0);
    if (total < 12) {
      ctx.addIssue({
        code: "custom",
        message: "You must order at least 12 total cases.",
        path: ["items"],
      });
    }
  }),
});
```

This is especially useful for reusing Spatie Data request objects across multiple entry points.

Because the literal starts with `.`, the refinement is appended to the inferred array builder that comes from `#[InheritValidationFrom]`. The callable passed to `append()` receives the JSON-encoded message as its first argument (and the raw string as a second argument if you declare it). If you remove the dot, make sure the literal contains the complete builder (for example `z.array(z.object({ … }))`) because it replaces the default output instead of extending it.

### Complex Inheritance

Inherit multiple rules from different sources:

```php
class CommonValidations
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|regex:/^\+?[\d\s\-\(\)]+$/',
        ];
    }
}

class PasswordValidations
{
    public function rules(): array
    {
        return [
            'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
        ];
    }
}

#[ValidationSchema]
class UserRegistrationData extends Data
{
    public function __construct(
        #[InheritValidationFrom(CommonValidations::class, 'name')]
        public string $full_name,

        #[InheritValidationFrom(CommonValidations::class, 'email')]
        public string $email,

        #[InheritValidationFrom(CommonValidations::class, 'phone')]
        public string $phone,

        #[InheritValidationFrom(PasswordValidations::class, 'password')]
        public string $password,
    ) {}
}
```

## Combining with Spatie Attributes

You can combine Laravel Zod Generator attributes with Spatie Data validation attributes:

```php
use Spatie\LaravelData\Attributes\Validation\*;

#[ValidationSchema]
class ProductData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, Numeric, Min(0.01)]
        public float $price,

        // Combine Spatie validation with inheritance
        #[
            Required,
            StringType,
            InheritValidationFrom(CategoryValidator::class, 'category_code')
        ]
        public string $category,

        #[Nullable, StringType, Max(1000)]
        public ?string $description = null,
    ) {}
}
```

## Attribute Parameters

### ValidationSchema Parameters

| Parameter | Type     | Description        | Example                                   |
| --------- | -------- | ------------------ | ----------------------------------------- |
| `name`    | `string` | Custom schema name | `#[ValidationSchema(name: 'CustomForm')]` |

### InheritValidationFrom Parameters

| Parameter   | Type     | Description           | Example                      |
| ----------- | -------- | --------------------- | ---------------------------- |
| `className` | `string` | Class to inherit from | `PostalCodeValidator::class` |
| `fieldName` | `string` | Field name to inherit | `'postal_code'`              |

## Advanced Patterns

### Conditional Inheritance

Use inheritance based on application logic:

```php
class BaseUserValidator
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ];
    }
}

class AdminUserValidator
{
    public function rules(): array
    {
        return [
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|in:read,write,delete,admin',
        ];
    }
}

#[ValidationSchema(name: 'CreateRegularUser')]
class CreateUserData extends Data
{
    public function __construct(
        #[InheritValidationFrom(BaseUserValidator::class, 'name')]
        public string $name,

        #[InheritValidationFrom(BaseUserValidator::class, 'email')]
        public string $email,
    ) {}
}

#[ValidationSchema(name: 'CreateAdminUser')]
class CreateAdminUserData extends Data
{
    public function __construct(
        #[InheritValidationFrom(BaseUserValidator::class, 'name')]
        public string $name,

        #[InheritValidationFrom(BaseUserValidator::class, 'email')]
        public string $email,

        #[InheritValidationFrom(AdminUserValidator::class, 'permissions')]
        public array $permissions,
    ) {}
}
```

### Validation Libraries

Create reusable validation libraries:

```php
// app/Validators/CommonFields.php
namespace App\Validators;

class CommonFields
{
    public function rules(): array
    {
        return [
            'uuid' => 'required|uuid',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|regex:/^\+?[\d\s\-\(\)]+$/',
            'postal_code' => 'required|regex:/^\d{5}(-\d{4})?$/',
            'url' => 'nullable|url|max:255',
            'slug' => 'required|slug|max:100',
            'currency_amount' => 'required|numeric|min:0|max:999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid phone number',
            'postal_code.regex' => 'Please enter a valid postal code (12345 or 12345-6789)',
            'slug.slug' => 'Slug can only contain letters, numbers, and hyphens',
        ];
    }
}

// Usage across your application
#[ValidationSchema]
class ProductData extends Data
{
    public function __construct(
        #[InheritValidationFrom(CommonFields::class, 'uuid')]
        public string $id,

        #[InheritValidationFrom(CommonFields::class, 'slug')]
        public string $slug,

        #[InheritValidationFrom(CommonFields::class, 'currency_amount')]
        public float $price,

        #[Required, StringType, Max(255)]
        public string $name,
    ) {}
}
```

### Multi-tenancy Support

Handle tenant-specific validation:

```php
class TenantAValidator
{
    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:tenant_a_companies,id',
            'department' => 'required|in:sales,marketing,engineering',
        ];
    }
}

class TenantBValidator
{
    public function rules(): array
    {
        return [
            'organization_id' => 'required|exists:tenant_b_orgs,id',
            'team' => 'required|in:dev,qa,ops,design',
        ];
    }
}

#[ValidationSchema(name: 'TenantAUser')]
class TenantAUserData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $name,

        #[InheritValidationFrom(TenantAValidator::class, 'company_id')]
        public int $company_id,

        #[InheritValidationFrom(TenantAValidator::class, 'department')]
        public string $department,
    ) {}
}

#[ValidationSchema(name: 'TenantBUser')]
class TenantBUserData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $name,

        #[InheritValidationFrom(TenantBValidator::class, 'organization_id')]
        public int $organization_id,

        #[InheritValidationFrom(TenantBValidator::class, 'team')]
        public string $team,
    ) {}
}
```

## Error Handling

### Invalid Inheritance

If inheritance fails, you'll get descriptive error messages:

```php
#[InheritValidationFrom(NonExistentClass::class, 'field')]
public string $field;

// Error: Class 'NonExistentClass' not found
```

```php
#[InheritValidationFrom(ExistingClass::class, 'non_existent_field')]
public string $field;

// Error: Field 'non_existent_field' not found in validation rules
```

### Circular Dependencies

The system prevents circular inheritance:

```php
class ClassA
{
    #[InheritValidationFrom(ClassB::class, 'field')]
    public string $field;
}

class ClassB
{
    #[InheritValidationFrom(ClassA::class, 'field')]
    public string $field;
}

// Error: Circular dependency detected
```

## Performance Considerations

### Caching Inherited Rules

For better performance with complex inheritance chains:

```php
class OptimizedValidator
{
    private static array $cachedRules = [];

    public function rules(): array
    {
        $cacheKey = static::class;

        if (!isset(self::$cachedRules[$cacheKey])) {
            self::$cachedRules[$cacheKey] = $this->buildRules();
        }

        return self::$cachedRules[$cacheKey];
    }

    private function buildRules(): array
    {
        // Expensive rule building logic
        return [
            'field' => 'complex|validation|chain',
        ];
    }
}
```

### Limiting Inheritance Depth

Keep inheritance chains shallow for better performance and maintainability:

```php
// Good: Direct inheritance
#[InheritValidationFrom(BaseValidator::class, 'field')]

// Avoid: Deep inheritance chains
#[InheritValidationFrom(Level1Validator::class, 'field')] // which inherits from Level2Validator, etc.
```

## Testing Attributes

Test your attributes work correctly:

```php
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AttributeTest extends TestCase
{
    public function test_zod_schema_attribute(): void
    {
        $reflection = new ReflectionClass(CreateUserRequest::class);
        $attributes = $reflection->getAttributes(ValidationSchema::class);

        $this->assertCount(1, $attributes);
        $this->assertEquals('UserCreation', $attributes[0]->newInstance()->name);
    }

    public function test_inheritance_attribute(): void
    {
        $reflection = new ReflectionClass(UserData::class);
        $property = $reflection->getProperty('email');
        $attributes = $property->getAttributes(InheritValidationFrom::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertEquals(CommonValidations::class, $instance->className);
        $this->assertEquals('email', $instance->fieldName);
    }
}
```

## Next Steps

- [Generation Process](./generation.md) - Learn how schemas are generated
- [TypeScript Usage](./typescript-usage.md) - Use generated schemas effectively
- [Custom Extractors](../advanced/custom-extractors.md) - Handle complex inheritance patterns
- [Examples](../examples/spatie-data.md) - See real-world examples
