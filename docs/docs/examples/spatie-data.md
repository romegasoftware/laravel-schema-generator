---
sidebar_position: 2
---

# Spatie Data Examples

Real-world examples of using Laravel Zod Generator with Spatie Laravel Data classes. These examples demonstrate property-level validation attributes, data collections, and advanced patterns.

## Basic Data Classes

### User Profile Data

```php
<?php

namespace App\Data;

use Carbon\Carbon;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\*;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

#[ValidationSchema]
class UserProfileData extends Data
{
    public function __construct(
        #[Required, StringType, Max(100)]
        public string $first_name,

        #[Required, StringType, Max(100)]
        public string $last_name,

        #[Required, Email, Max(255)]
        public string $email,

        #[Nullable, StringType, Regex('/^\+?[\d\s\-\(\)]+$/')]
        public ?string $phone,

        #[Nullable, Url, Max(255)]
        public ?string $website,

        #[Nullable, StringType, Max(500)]
        public ?string $bio,

        #[Required, In(['active', 'inactive', 'pending'])]
        public string $status,

        #[Required, Date]
        public Carbon $created_at,

        #[Nullable, Date]
        public ?Carbon $email_verified_at,

        #[Required, Boolean]
        public bool $is_admin = false,

        #[DataCollectionOf(RoleData::class)]
        public DataCollection $roles,

        #[DataCollectionOf(AddressData::class)]
        public DataCollection $addresses,
    ) {}

    public static function messages(): array
    {
        return [
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'email.email' => 'Please enter a valid email address',
            'phone.regex' => 'Please enter a valid phone number',
            'website.url' => 'Please enter a valid website URL',
        ];
    }
}

#[ValidationSchema]
class RoleData extends Data
{
    public function __construct(
        #[Required, StringType, Max(50)]
        public string $name,

        #[Nullable, StringType, Max(255)]
        public ?string $description,

        #[DataCollectionOf(PermissionData::class)]
        public DataCollection $permissions,
    ) {}
}

#[ValidationSchema]
class PermissionData extends Data
{
    public function __construct(
        #[Required, StringType, Max(50)]
        public string $name,

        #[Required, StringType, Max(255)]
        public string $description,
    ) {}
}

#[ValidationSchema]
class AddressData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $street,

        #[Required, StringType, Max(100)]
        public string $city,

        #[Required, StringType, Size(2)]
        public string $state,

        #[Required, StringType, Regex('/^\d{5}(-\d{4})?$/')]
        public string $postal_code,

        #[Required, StringType, Size(2)]
        public string $country,

        #[Required, In(['home', 'work', 'billing', 'shipping'])]
        public string $type,

        #[Required, Boolean]
        public bool $is_primary = false,
    ) {}

    public static function messages(): array
    {
        return [
            'postal_code.regex' => 'Please enter a valid postal code (12345 or 12345-6789)',
            'state.size' => 'State must be a 2-letter code',
            'country.size' => 'Country must be a 2-letter code',
        ];
    }
}
```

**Generated Zod Schemas:**

```typescript
import { z } from "zod";

export const UserProfileSchema = z.object({
  first_name: z.string().min(1, "First name is required").max(100),
  last_name: z.string().min(1, "Last name is required").max(100),
  email: z.email("Please enter a valid email address").max(255),
  phone: z
    .string()
    .regex(/^\+?[\d\s\-\(\)]+$/, "Please enter a valid phone number")
    .nullable(),
  website: z
    .string()
    .url("Please enter a valid website URL")
    .max(255)
    .nullable(),
  bio: z.string().max(500).nullable(),
  status: z.enum(["active", "inactive", "pending"]),
  created_at: z.string().datetime(),
  email_verified_at: z.string().datetime().nullable(),
  is_admin: z.boolean(),
  roles: z.array(RoleSchema),
  addresses: z.array(AddressSchema),
});

export const RoleSchema = z.object({
  name: z.string().min(1).max(50),
  description: z.string().max(255).nullable(),
  permissions: z.array(PermissionSchema),
});

export const PermissionSchema = z.object({
  name: z.string().min(1).max(50),
  description: z.string().min(1).max(255),
});

export const AddressSchema = z.object({
  street: z.string().min(1).max(255),
  city: z.string().min(1).max(100),
  state: z.string().length(2, "State must be a 2-letter code"),
  postal_code: z
    .string()
    .regex(
      /^\d{5}(-\d{4})?$/,
      "Please enter a valid postal code (12345 or 12345-6789)"
    ),
  country: z.string().length(2, "Country must be a 2-letter code"),
  type: z.enum(["home", "work", "billing", "shipping"]),
  is_primary: z.boolean(),
});
```

## E-commerce Data Models

### Product Catalog

```php
<?php

namespace App\Data\Products;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\*;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

#[ValidationSchema]
class ProductData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, StringType, Max(100), Unique('products', 'sku')]
        public string $sku,

        #[Nullable, StringType, Max(2000)]
        public ?string $description,

        #[Required, Numeric, Min(0.01), Max(999999.99)]
        public float $price,

        #[Nullable, Numeric, Min(0), Max(999999.99)]
        public ?float $sale_price,

        #[Required, Integer, Min(0)]
        public int $stock_quantity,

        #[Required, In(['draft', 'active', 'inactive', 'discontinued'])]
        public string $status,

        #[Required, Boolean]
        public bool $is_featured = false,

        #[Required, Boolean]
        public bool $track_inventory = true,

        #[Nullable, Numeric, Min(0)]
        public ?float $weight,

        public ProductDimensionsData $dimensions,

        public ProductCategoryData $category,

        #[DataCollectionOf(ProductImageData::class)]
        public DataCollection $images,

        #[DataCollectionOf(ProductVariantData::class)]
        public DataCollection $variants,

        #[DataCollectionOf(ProductAttributeData::class)]
        public DataCollection $attributes,

        #[ArrayType, Max(20)]
        public array $tags = [],
    ) {}

    public static function messages(): array
    {
        return [
            'sku.unique' => 'This SKU is already in use',
            'price.min' => 'Price must be at least $0.01',
            'stock_quantity.min' => 'Stock quantity cannot be negative',
        ];
    }
}

#[ValidationSchema]
class ProductDimensionsData extends Data
{
    public function __construct(
        #[Nullable, Numeric, Min(0)]
        public ?float $length,

        #[Nullable, Numeric, Min(0)]
        public ?float $width,

        #[Nullable, Numeric, Min(0)]
        public ?float $height,

        #[Required, StringType, In(['cm', 'in', 'm', 'ft'])]
        public string $unit = 'cm',
    ) {}
}

#[ValidationSchema]
class ProductCategoryData extends Data
{
    public function __construct(
        #[Required, Integer, Exists('categories', 'id')]
        public int $id,

        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, StringType, Max(255)]
        public string $slug,

        #[Nullable, Integer, Exists('categories', 'id')]
        public ?int $parent_id,
    ) {}
}

#[ValidationSchema]
class ProductImageData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $filename,

        #[Required, StringType, Max(255)]
        public string $alt_text,

        #[Required, Url]
        public string $url,

        #[Required, Integer, Min(1)]
        public int $sort_order,

        #[Required, Boolean]
        public bool $is_primary = false,
    ) {}
}

#[ValidationSchema]
class ProductVariantData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, StringType, Max(100)]
        public string $sku,

        #[Nullable, Numeric, Min(0.01)]
        public ?float $price_modifier,

        #[Required, Integer, Min(0)]
        public int $stock_quantity,

        #[DataCollectionOf(VariantAttributeData::class)]
        public DataCollection $attributes,
    ) {}
}

#[ValidationSchema]
class ProductAttributeData extends Data
{
    public function __construct(
        #[Required, StringType, Max(100)]
        public string $name,

        #[Required, StringType, Max(255)]
        public string $value,

        #[Required, In(['text', 'number', 'boolean', 'date', 'select'])]
        public string $type,

        #[Required, Boolean]
        public bool $is_variant_attribute = false,
    ) {}
}

#[ValidationSchema]
class VariantAttributeData extends Data
{
    public function __construct(
        #[Required, StringType, Max(100)]
        public string $name,

        #[Required, StringType, Max(255)]
        public string $value,
    ) {}
}
```

### Order Management

```php
<?php

namespace App\Data\Orders;

use Carbon\Carbon;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\*;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

#[ValidationSchema]
class OrderData extends Data
{
    public function __construct(
        #[Required, StringType, Max(50)]
        public string $order_number,

        #[Required, In(['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])]
        public string $status,

        #[Required, Numeric, Min(0)]
        public float $subtotal,

        #[Required, Numeric, Min(0)]
        public float $tax_amount,

        #[Required, Numeric, Min(0)]
        public float $shipping_amount,

        #[Required, Numeric, Min(0)]
        public float $discount_amount,

        #[Required, Numeric, Min(0)]
        public float $total_amount,

        #[Required, StringType, Size(3), In(['USD', 'EUR', 'GBP', 'CAD', 'AUD'])]
        public string $currency,

        #[Required, Date]
        public Carbon $created_at,

        #[Nullable, Date]
        public ?Carbon $shipped_at,

        #[Nullable, Date]
        public ?Carbon $delivered_at,

        #[Nullable, StringType, Max(500)]
        public ?string $notes,

        public CustomerData $customer,

        public OrderAddressData $billing_address,

        public OrderAddressData $shipping_address,

        #[DataCollectionOf(OrderItemData::class)]
        public DataCollection $items,

        public PaymentData $payment,

        public ShippingData $shipping,

        #[DataCollectionOf(OrderDiscountData::class)]
        public DataCollection $discounts,
    ) {}

    public static function messages(): array
    {
        return [
            'currency.in' => 'Unsupported currency',
            'total_amount.min' => 'Order total cannot be negative',
        ];
    }
}

#[ValidationSchema]
class CustomerData extends Data
{
    public function __construct(
        #[Required, Integer, Exists('customers', 'id')]
        public int $id,

        #[Required, Email, Max(255)]
        public string $email,

        #[Required, StringType, Max(100)]
        public string $first_name,

        #[Required, StringType, Max(100)]
        public string $last_name,

        #[Nullable, StringType, Regex('/^\+?[\d\s\-\(\)]+$/')]
        public ?string $phone,

        #[Required, In(['guest', 'registered', 'premium'])]
        public string $type,
    ) {}
}

#[ValidationSchema]
class OrderAddressData extends Data
{
    public function __construct(
        #[Required, StringType, Max(100)]
        public string $first_name,

        #[Required, StringType, Max(100)]
        public string $last_name,

        #[Nullable, StringType, Max(255)]
        public ?string $company,

        #[Required, StringType, Max(255)]
        public string $street_1,

        #[Nullable, StringType, Max(255)]
        public ?string $street_2,

        #[Required, StringType, Max(100)]
        public string $city,

        #[Required, StringType, Max(100)]
        public string $state,

        #[Required, StringType, Regex('/^\d{5}(-\d{4})?$/')]
        public string $postal_code,

        #[Required, StringType, Size(2)]
        public string $country,

        #[Nullable, StringType, Regex('/^\+?[\d\s\-\(\)]+$/')]
        public ?string $phone,
    ) {}
}

#[ValidationSchema]
class OrderItemData extends Data
{
    public function __construct(
        #[Required, Integer, Exists('products', 'id')]
        public int $product_id,

        #[Nullable, Integer, Exists('product_variants', 'id')]
        public ?int $variant_id,

        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, StringType, Max(100)]
        public string $sku,

        #[Required, Integer, Min(1)]
        public int $quantity,

        #[Required, Numeric, Min(0)]
        public float $unit_price,

        #[Required, Numeric, Min(0)]
        public float $total_price,

        #[DataCollectionOf(OrderItemAttributeData::class)]
        public DataCollection $attributes,
    ) {}
}

#[ValidationSchema]
class OrderItemAttributeData extends Data
{
    public function __construct(
        #[Required, StringType, Max(100)]
        public string $name,

        #[Required, StringType, Max(255)]
        public string $value,
    ) {}
}

#[ValidationSchema]
class PaymentData extends Data
{
    public function __construct(
        #[Required, In(['credit_card', 'debit_card', 'paypal', 'apple_pay', 'google_pay', 'bank_transfer', 'crypto'])]
        public string $method,

        #[Required, In(['pending', 'processing', 'completed', 'failed', 'refunded', 'partially_refunded'])]
        public string $status,

        #[Required, Numeric, Min(0)]
        public float $amount,

        #[Nullable, StringType, Max(255)]
        public ?string $transaction_id,

        #[Nullable, StringType, Max(255)]
        public ?string $gateway_response,

        #[Required, Date]
        public Carbon $processed_at,
    ) {}
}

#[ValidationSchema]
class ShippingData extends Data
{
    public function __construct(
        #[Required, StringType, Max(100)]
        public string $method,

        #[Required, StringType, Max(255)]
        public string $carrier,

        #[Nullable, StringType, Max(255)]
        public ?string $tracking_number,

        #[Required, Numeric, Min(0)]
        public float $cost,

        #[Nullable, Date]
        public ?Carbon $estimated_delivery,

        #[Nullable, Date]
        public ?Carbon $actual_delivery,
    ) {}
}

#[ValidationSchema]
class OrderDiscountData extends Data
{
    public function __construct(
        #[Required, StringType, Max(100)]
        public string $code,

        #[Required, StringType, Max(255)]
        public string $description,

        #[Required, In(['fixed', 'percentage'])]
        public string $type,

        #[Required, Numeric, Min(0)]
        public float $value,

        #[Required, Numeric, Min(0)]
        public float $discount_amount,
    ) {}
}
```

## Content Management

### Blog System

```php
<?php

namespace App\Data\Blog;

use Carbon\Carbon;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\*;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

#[ValidationSchema]
class ArticleData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $title,

        #[Required, StringType, Max(255), Unique('articles', 'slug')]
        public string $slug,

        #[Nullable, StringType, Max(500)]
        public ?string $excerpt,

        #[Required, StringType, Min(100)]
        public string $content,

        #[Required, In(['draft', 'published', 'scheduled', 'archived'])]
        public string $status,

        #[Required, Date]
        public Carbon $created_at,

        #[Nullable, Date]
        public ?Carbon $published_at,

        #[Required, Boolean]
        public bool $is_featured = false,

        #[Required, Boolean]
        public bool $allow_comments = true,

        #[Nullable, StringType, Max(255)]
        public ?string $featured_image,

        #[Required, Integer, Min(0)]
        public int $view_count = 0,

        #[Required, Integer, Min(0)]
        public int $comment_count = 0,

        public AuthorData $author,

        public CategoryData $category,

        #[DataCollectionOf(TagData::class)]
        public DataCollection $tags,

        public SeoMetaData $seo,

        #[DataCollectionOf(ArticleCommentData::class)]
        public DataCollection $comments,
    ) {}

    public static function messages(): array
    {
        return [
            'title.required' => 'Article title is required',
            'slug.unique' => 'This slug is already in use',
            'content.min' => 'Article content must be at least 100 characters long',
        ];
    }
}

#[ValidationSchema]
class AuthorData extends Data
{
    public function __construct(
        #[Required, Integer, Exists('users', 'id')]
        public int $id,

        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, Email, Max(255)]
        public string $email,

        #[Nullable, StringType, Max(500)]
        public ?string $bio,

        #[Nullable, StringType, Max(255)]
        public ?string $avatar,

        #[Nullable, Url, Max(255)]
        public ?string $website,

        #[DataCollectionOf(SocialLinkData::class)]
        public DataCollection $social_links,
    ) {}
}

#[ValidationSchema]
class CategoryData extends Data
{
    public function __construct(
        #[Required, Integer, Exists('categories', 'id')]
        public int $id,

        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, StringType, Max(255)]
        public string $slug,

        #[Nullable, StringType, Max(500)]
        public ?string $description,

        #[Nullable, Integer, Exists('categories', 'id')]
        public ?int $parent_id,

        #[Required, StringType, Max(7), Regex('/^#[0-9A-Fa-f]{6}$/')]
        public string $color = '#000000',
    ) {}

    public static function messages(): array
    {
        return [
            'color.regex' => 'Color must be a valid hex color code (e.g., #FF5733)',
        ];
    }
}

#[ValidationSchema]
class TagData extends Data
{
    public function __construct(
        #[Required, StringType, Max(50)]
        public string $name,

        #[Required, StringType, Max(50)]
        public string $slug,

        #[Required, StringType, Max(7), Regex('/^#[0-9A-Fa-f]{6}$/')]
        public string $color = '#666666',
    ) {}
}

#[ValidationSchema]
class SeoMetaData extends Data
{
    public function __construct(
        #[Nullable, StringType, Max(60)]
        public ?string $title,

        #[Nullable, StringType, Max(160)]
        public ?string $description,

        #[Nullable, StringType, Max(255)]
        public ?string $keywords,

        #[Nullable, Url, Max(255)]
        public ?string $canonical_url,

        #[Nullable, StringType, Max(255)]
        public ?string $og_image,

        #[Required, Boolean]
        public bool $noindex = false,

        #[Required, Boolean]
        public bool $nofollow = false,
    ) {}

    public static function messages(): array
    {
        return [
            'title.max' => 'SEO title should not exceed 60 characters for optimal search results',
            'description.max' => 'Meta description should not exceed 160 characters',
        ];
    }
}

#[ValidationSchema]
class SocialLinkData extends Data
{
    public function __construct(
        #[Required, In(['twitter', 'facebook', 'linkedin', 'github', 'instagram', 'youtube'])]
        public string $platform,

        #[Required, Url, Max(255)]
        public string $url,
    ) {}
}

#[ValidationSchema]
class ArticleCommentData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $author_name,

        #[Required, Email, Max(255)]
        public string $author_email,

        #[Nullable, Url, Max(255)]
        public ?string $author_website,

        #[Required, StringType, Max(2000)]
        public string $content,

        #[Required, In(['pending', 'approved', 'rejected', 'spam'])]
        public string $status,

        #[Required, Date]
        public Carbon $created_at,

        #[Nullable, Integer, Exists('article_comments', 'id')]
        public ?int $parent_id,
    ) {}
}
```

## Validation Inheritance Examples

### Reusable Validation Components

```php
<?php

namespace App\Data\Common;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\Validation\*;
use Spatie\LaravelData\Data;

// Base validation rules
class CommonFieldValidations
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'phone' => 'nullable|regex:/^\+?[\d\s\-\(\)]+$/',
            'website' => 'nullable|url|max:255',
            'postal_code' => 'required|regex:/^\d{5}(-\d{4})?$/',
            'country_code' => 'required|string|size:2',
            'currency_code' => 'required|string|size:3|in:USD,EUR,GBP,CAD,AUD',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Please enter a valid email address',
            'phone.regex' => 'Please enter a valid phone number',
            'postal_code.regex' => 'Please enter a valid postal code',
            'country_code.size' => 'Country code must be 2 characters',
            'currency_code.in' => 'Unsupported currency',
        ];
    }
}

// Using inheritance in Data classes
#[ValidationSchema]
class ContactData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[InheritValidationFrom(CommonFieldValidations::class, 'email')]
        public string $email,

        #[InheritValidationFrom(CommonFieldValidations::class, 'phone')]
        public ?string $phone,

        #[InheritValidationFrom(CommonFieldValidations::class, 'website')]
        public ?string $website,

        #[Required, StringType, Max(255)]
        public string $company,
    ) {}
}

#[ValidationSchema]
class BusinessLocationData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $business_name,

        #[Required, StringType, Max(255)]
        public string $address,

        #[Required, StringType, Max(100)]
        public string $city,

        #[InheritValidationFrom(CommonFieldValidations::class, 'postal_code')]
        public string $postal_code,

        #[InheritValidationFrom(CommonFieldValidations::class, 'country_code')]
        public string $country_code,

        #[InheritValidationFrom(CommonFieldValidations::class, 'email')]
        public string $contact_email,

        #[InheritValidationFrom(CommonFieldValidations::class, 'phone')]
        public ?string $contact_phone,
    ) {}
}

#[ValidationSchema]
class PricingData extends Data
{
    public function __construct(
        #[Required, Numeric, Min(0.01)]
        public float $amount,

        #[InheritValidationFrom(CommonFieldValidations::class, 'currency_code')]
        public string $currency,

        #[Required, In(['one_time', 'recurring'])]
        public string $type,

        #[Nullable, In(['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])]
        public ?string $billing_cycle,
    ) {}
}
```

### Complex Inheritance Patterns

```php
<?php

namespace App\Data\Financial;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\Validation\*;
use Spatie\LaravelData\Data;

class FinancialValidations
{
    public function rules(): array
    {
        return [
            'account_number' => 'required|string|regex:/^[0-9]{10,12}$/',
            'routing_number' => 'required|string|regex:/^[0-9]{9}$/',
            'swift_code' => 'nullable|string|regex:/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/',
            'iban' => 'nullable|string|regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{4}[0-9]{7}([A-Z0-9]?){0,16}$/',
            'tax_id' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.regex' => 'Account number must be 10-12 digits',
            'routing_number.regex' => 'Routing number must be exactly 9 digits',
            'swift_code.regex' => 'Invalid SWIFT code format',
            'iban.regex' => 'Invalid IBAN format',
        ];
    }
}

class BusinessValidations
{
    public function rules(): array
    {
        return [
            'legal_name' => 'required|string|max:255',
            'dba_name' => 'nullable|string|max:255',
            'registration_number' => 'required|string|max:100',
            'business_type' => 'required|in:sole_proprietorship,partnership,llc,corporation,non_profit',
            'industry_code' => 'required|string|regex:/^[0-9]{4,6}$/',
        ];
    }

    public function messages(): array
    {
        return [
            'legal_name.required' => 'Legal business name is required',
            'registration_number.required' => 'Business registration number is required',
            'industry_code.regex' => 'Industry code must be 4-6 digits',
        ];
    }
}

#[ValidationSchema]
class PaymentAccountData extends Data
{
    public function __construct(
        // Basic account information
        #[InheritValidationFrom(FinancialValidations::class, 'account_number')]
        public string $account_number,

        #[InheritValidationFrom(FinancialValidations::class, 'routing_number')]
        public string $routing_number,

        #[InheritValidationFrom(FinancialValidations::class, 'swift_code')]
        public ?string $swift_code,

        // Account details
        #[Required, In(['checking', 'savings', 'business_checking', 'business_savings'])]
        public string $account_type,

        #[Required, StringType, Max(255)]
        public string $bank_name,

        #[Required, Boolean]
        public bool $is_primary = false,

        // Account holder information
        #[Required, StringType, Max(255)]
        public string $account_holder_name,

        #[Required, In(['individual', 'business'])]
        public string $account_holder_type,
    ) {}
}

#[ValidationSchema]
class BusinessPaymentAccountData extends Data
{
    public function __construct(
        // Inherit financial account details
        #[InheritValidationFrom(FinancialValidations::class, 'account_number')]
        public string $account_number,

        #[InheritValidationFrom(FinancialValidations::class, 'routing_number')]
        public string $routing_number,

        #[InheritValidationFrom(FinancialValidations::class, 'tax_id')]
        public ?string $tax_id,

        // Inherit business details
        #[InheritValidationFrom(BusinessValidations::class, 'legal_name')]
        public string $business_name,

        #[InheritValidationFrom(BusinessValidations::class, 'dba_name')]
        public ?string $dba_name,

        #[InheritValidationFrom(BusinessValidations::class, 'registration_number')]
        public string $registration_number,

        #[InheritValidationFrom(BusinessValidations::class, 'business_type')]
        public string $business_type,

        // Additional business-specific fields
        #[Required, Date]
        public string $business_established_date,

        #[Required, StringType, Max(255)]
        public string $authorized_signatory,

        #[Required, StringType, Max(100)]
        public string $signatory_title,
    ) {}
}
```

## API Data Transfer Objects

### RESTful API Resources

```php
<?php

namespace App\Data\Api;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\*;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

#[ValidationSchema(name: 'ApiUser')]
class UserApiData extends Data
{
    public function __construct(
        #[Required, Integer, Min(1)]
        public int $id,

        #[Required, StringType, Max(255)]
        public string $username,

        #[Required, Email, Max(255)]
        public string $email,

        #[Required, StringType, Max(255)]
        public string $display_name,

        #[Nullable, Url, Max(255)]
        public ?string $avatar_url,

        #[Required, In(['active', 'inactive', 'suspended', 'pending_verification'])]
        public string $status,

        #[Required, Date]
        public string $created_at,

        #[Nullable, Date]
        public ?string $last_login_at,

        #[Required, Boolean]
        public bool $email_verified,

        #[Required, Boolean]
        public bool $two_factor_enabled,

        #[DataCollectionOf(RoleApiData::class)]
        public DataCollection $roles,

        #[DataCollectionOf(PermissionApiData::class)]
        public DataCollection $permissions,

        public UserPreferencesApiData $preferences,

        public UserStatsApiData $stats,
    ) {}
}

#[ValidationSchema(name: 'ApiRole')]
class RoleApiData extends Data
{
    public function __construct(
        #[Required, Integer, Min(1)]
        public int $id,

        #[Required, StringType, Max(100)]
        public string $name,

        #[Required, StringType, Max(255)]
        public string $display_name,

        #[Nullable, StringType, Max(500)]
        public ?string $description,

        #[Required, Integer, Min(0)]
        public int $level,

        #[Required, Boolean]
        public bool $is_system_role,
    ) {}
}

#[ValidationSchema(name: 'ApiPermission')]
class PermissionApiData extends Data
{
    public function __construct(
        #[Required, Integer, Min(1)]
        public int $id,

        #[Required, StringType, Max(100)]
        public string $name,

        #[Required, StringType, Max(255)]
        public string $display_name,

        #[Required, StringType, Max(100)]
        public string $resource,

        #[Required, StringType, Max(50)]
        public string $action,
    ) {}
}

#[ValidationSchema(name: 'ApiUserPreferences')]
class UserPreferencesApiData extends Data
{
    public function __construct(
        #[Required, In(['light', 'dark', 'auto'])]
        public string $theme,

        #[Required, StringType, In(array_keys(config('app.supported_locales')))]
        public string $language,

        #[Required, StringType]
        public string $timezone,

        #[Required, In(['12', '24'])]
        public string $time_format,

        #[Required, In(['MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD'])]
        public string $date_format,

        public NotificationPreferencesApiData $notifications,
    ) {}
}

#[ValidationSchema(name: 'ApiNotificationPreferences')]
class NotificationPreferencesApiData extends Data
{
    public function __construct(
        #[Required, Boolean]
        public bool $email_enabled,

        #[Required, Boolean]
        public bool $push_enabled,

        #[Required, Boolean]
        public bool $sms_enabled,

        #[Required, Boolean]
        public bool $marketing_emails,

        #[Required, Boolean]
        public bool $security_alerts,

        #[Required, Boolean]
        public bool $product_updates,
    ) {}
}

#[ValidationSchema(name: 'ApiUserStats')]
class UserStatsApiData extends Data
{
    public function __construct(
        #[Required, Integer, Min(0)]
        public int $login_count,

        #[Required, Integer, Min(0)]
        public int $posts_created,

        #[Required, Integer, Min(0)]
        public int $comments_made,

        #[Required, Integer, Min(0)]
        public int $reputation_score,

        #[Required, Date]
        public string $member_since,

        #[Nullable, Date]
        public ?string $last_activity,
    ) {}
}
```

### API Request/Response Collections

```php
<?php

namespace App\Data\Api\Collections;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\*;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

#[ValidationSchema(name: 'ApiPaginatedResponse')]
class PaginatedResponseData extends Data
{
    public function __construct(
        #[DataCollectionOf('mixed')] // Dynamic data type
        public DataCollection $data,

        public PaginationMetaData $meta,

        public PaginationLinksData $links,
    ) {}
}

#[ValidationSchema(name: 'ApiPaginationMeta')]
class PaginationMetaData extends Data
{
    public function __construct(
        #[Required, Integer, Min(1)]
        public int $current_page,

        #[Required, Integer, Min(1)]
        public int $last_page,

        #[Required, Integer, Min(0)]
        public int $per_page,

        #[Required, Integer, Min(0)]
        public int $total,

        #[Required, Integer, Min(0)]
        public int $from,

        #[Required, Integer, Min(0)]
        public int $to,

        #[Required, StringType, Max(255)]
        public string $path,
    ) {}
}

#[ValidationSchema(name: 'ApiPaginationLinks')]
class PaginationLinksData extends Data
{
    public function __construct(
        #[Nullable, Url]
        public ?string $first,

        #[Nullable, Url]
        public ?string $last,

        #[Nullable, Url]
        public ?string $prev,

        #[Nullable, Url]
        public ?string $next,
    ) {}
}

#[ValidationSchema(name: 'ApiBatchResponse')]
class BatchResponseData extends Data
{
    public function __construct(
        #[Required, Integer, Min(0)]
        public int $total_processed,

        #[Required, Integer, Min(0)]
        public int $successful,

        #[Required, Integer, Min(0)]
        public int $failed,

        #[DataCollectionOf(BatchResultData::class)]
        public DataCollection $results,

        #[DataCollectionOf(BatchErrorData::class)]
        public DataCollection $errors,

        #[Required, Numeric, Min(0)]
        public float $processing_time_seconds,
    ) {}
}

#[ValidationSchema(name: 'ApiBatchResult')]
class BatchResultData extends Data
{
    public function __construct(
        #[Required, Integer, Min(1)]
        public int $id,

        #[Required, In(['success', 'failed', 'skipped'])]
        public string $status,

        #[Nullable, StringType, Max(500)]
        public ?string $message,

        #[Required] // Mixed data
        public mixed $data,
    ) {}
}

#[ValidationSchema(name: 'ApiBatchError')]
class BatchErrorData extends Data
{
    public function __construct(
        #[Required, Integer, Min(1)]
        public int $id,

        #[Required, StringType, Max(100)]
        public string $error_code,

        #[Required, StringType, Max(500)]
        public string $error_message,

        #[Nullable, ArrayType]
        public ?array $validation_errors,
    ) {}
}
```

## Generated TypeScript Usage

### Frontend Integration

```typescript
import { z } from 'zod';
import {
  UserProfileSchema,
  ProductSchema,
  OrderSchema,
  ArticleSchema,
  ContactSchema,
} from '@/types/zod-schemas';

// Type inference
type UserProfile = z.infer<typeof UserProfileSchema>;
type Product = z.infer<typeof ProductSchema>;
type Order = z.infer<typeof OrderSchema>;

// Form validation with React Hook Form
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

function ContactForm() {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<z.infer<typeof ContactSchema>>({
    resolver: zodResolver(ContactSchema),
  });

  const onSubmit = async (data: z.infer<typeof ContactSchema>) => {
    // Data is fully validated and type-safe
    const response = await fetch('/api/contacts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <input {...register('name')} placeholder="Name" />
      {errors.name && <span>{errors.name.message}</span>}

      <input {...register('email')} type="email" placeholder="Email" />
      {errors.email && <span>{errors.email.message}</span>}

      <input {...register('phone')} placeholder="Phone" />
      {errors.phone && <span>{errors.phone.message}</span>}

      <button type="submit">Submit</button>
    </form>
  );
}

// API response validation
async function fetchUserProfile(id: number): Promise<UserProfile> {
  const response = await fetch(`/api/users/${id}`);
  const data = await response.json();

  // Validate API response
  const result = UserProfileSchema.safeParse(data);

  if (!result.success) {
    console.error('Invalid API response:', result.error);
    throw new Error('Invalid user profile data');
  }

  return result.data;
}

// Complex nested validation
async function createOrder(orderData: unknown): Promise<void> {
  const result = OrderSchema.safeParse(orderData);

  if (!result.success) {
    const errors = result.error.issues.map(issue => ({
      field: issue.path.join('.'),
      message: issue.message,
    }));

    throw new ValidationError('Order validation failed', errors);
  }

  await fetch('/api/orders', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(result.data),
  });
}
```

## Best Practices

### Use Meaningful Data Class Names

```php
// Good: Descriptive names
UserProfileData
ProductCatalogData
OrderCheckoutData

// Avoid: Generic names
UserData
Data
Model
```

### Leverage Data Collections

```php
// Good: Proper collection typing
#[DataCollectionOf(AddressData::class)]
public DataCollection $addresses,

#[DataCollectionOf(OrderItemData::class)]
public DataCollection $items,
```

### Group Related Properties

```php
// Good: Grouped related data
public AddressData $billing_address,
public AddressData $shipping_address,
public PaymentData $payment,

// Avoid: Flat structure
public string $billing_street,
public string $billing_city,
public string $shipping_street,
```

### Use Validation Inheritance

```php
// Good: Reuse common validation patterns
#[InheritValidationFrom(CommonValidations::class, 'email')]
public string $email,
```

### Provide Custom Messages

```php
public static function messages(): array
{
    return [
        'email.email' => 'Please enter a valid email address',
        'price.min' => 'Price must be at least $0.01',
    ];
}
```

## Next Steps

- [Custom Validation Examples](./custom-validation.md) - Advanced patterns
- [Real-world Examples](./real-world.md) - Complete applications
- [Validation Inheritance](../advanced/inheritance.md) - Reuse patterns
- [Basic Usage](../usage/basic-usage.md) - Learn the fundamentals
