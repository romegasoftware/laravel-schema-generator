---
sidebar_position: 4
---

# Real-world Examples

Complete, production-ready examples showing how to use Laravel Zod Generator in real applications. These examples demonstrate full integration patterns, best practices, and common architectural approaches.

## E-commerce Platform

### Backend Laravel Structure

```php
<?php

// Product Management
namespace App\Http\Requests\Products;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Illuminate\Foundation\Http\FormRequest;

#[ValidationSchema]
class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0.01|max:999999.99',
            'compare_at_price' => 'nullable|numeric|gt:price',
            'sku' => 'required|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:50',
            'track_inventory' => 'boolean',
            'inventory_quantity' => 'required_if:track_inventory,true|integer|min:0',
            'allow_backorders' => 'boolean',
            'weight' => 'nullable|numeric|min:0',
            'category_id' => 'required|integer|exists:categories,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'tags' => 'array|max:20',
            'tags.*' => 'string|max:50',
            'images' => 'array|max:10',
            'images.*' => 'image|mimes:jpeg,png,webp|max:2048',
            'variants' => 'array',
            'variants.*.name' => 'required_with:variants|string|max:100',
            'variants.*.values' => 'required_with:variants|array|min:1|max:50',
            'variants.*.values.*' => 'string|max:100',
            'seo_title' => 'nullable|string|max:60',
            'seo_description' => 'nullable|string|max:160',
            'status' => 'required|in:draft,active,archived',
            'published_at' => 'nullable|date|after_or_equal:now',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required',
            'price.min' => 'Price must be at least $0.01',
            'sku.unique' => 'This SKU is already in use',
            'compare_at_price.gt' => 'Compare at price must be higher than the regular price',
            'inventory_quantity.required_if' => 'Inventory quantity is required when tracking inventory',
            'images.*.max' => 'Each image must be less than 2MB',
            'seo_title.max' => 'SEO title should not exceed 60 characters',
            'seo_description.max' => 'SEO description should not exceed 160 characters',
        ];
    }
}

#[ValidationSchema]
class CheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Cart items
            'line_items' => 'required|array|min:1|max:100',
            'line_items.*.product_id' => 'required|integer|exists:products,id',
            'line_items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'line_items.*.quantity' => 'required|integer|min:1|max:999',

            // Customer info
            'customer.email' => 'required|email|max:255',
            'customer.first_name' => 'required|string|max:100',
            'customer.last_name' => 'required|string|max:100',
            'customer.phone' => 'nullable|regex:/^\+?[\d\s\-\(\)]+$/',

            // Billing address
            'billing_address.first_name' => 'required|string|max:100',
            'billing_address.last_name' => 'required|string|max:100',
            'billing_address.company' => 'nullable|string|max:255',
            'billing_address.address1' => 'required|string|max:255',
            'billing_address.address2' => 'nullable|string|max:255',
            'billing_address.city' => 'required|string|max:100',
            'billing_address.province' => 'required|string|max:100',
            'billing_address.country' => 'required|string|size:2',
            'billing_address.postal_code' => 'required|string|regex:/^[A-Z0-9\s-]{3,10}$/',
            'billing_address.phone' => 'nullable|regex:/^\+?[\d\s\-\(\)]+$/',

            // Shipping address
            'shipping_address.same_as_billing' => 'boolean',
            'shipping_address.first_name' => 'required_if:shipping_address.same_as_billing,false|string|max:100',
            'shipping_address.last_name' => 'required_if:shipping_address.same_as_billing,false|string|max:100',
            'shipping_address.company' => 'nullable|string|max:255',
            'shipping_address.address1' => 'required_if:shipping_address.same_as_billing,false|string|max:255',
            'shipping_address.address2' => 'nullable|string|max:255',
            'shipping_address.city' => 'required_if:shipping_address.same_as_billing,false|string|max:100',
            'shipping_address.province' => 'required_if:shipping_address.same_as_billing,false|string|max:100',
            'shipping_address.country' => 'required_if:shipping_address.same_as_billing,false|string|size:2',
            'shipping_address.postal_code' => 'required_if:shipping_address.same_as_billing,false|string|regex:/^[A-Z0-9\s-]{3,10}$/',
            'shipping_address.phone' => 'nullable|regex:/^\+?[\d\s\-\(\)]+$/',

            // Shipping and payment
            'shipping_method' => 'required|string|exists:shipping_methods,code',
            'payment_method' => 'required|in:credit_card,paypal,apple_pay,google_pay',
            'discount_codes' => 'array|max:5',
            'discount_codes.*' => 'string|exists:discount_codes,code',

            // Additional options
            'gift_message' => 'nullable|string|max:500',
            'special_instructions' => 'nullable|string|max:500',
            'marketing_opt_in' => 'boolean',
            'terms_accepted' => 'required|accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'line_items.required' => 'Your cart is empty',
            'line_items.min' => 'Your cart must contain at least one item',
            'line_items.*.product_id.exists' => 'One or more products in your cart are no longer available',
            'customer.email.required' => 'Email address is required',
            'billing_address.postal_code.regex' => 'Please enter a valid postal code',
            'shipping_address.same_as_billing.required_if' => 'Shipping address is required',
            'terms_accepted.accepted' => 'You must accept the terms and conditions',
        ];
    }
}
```

### Frontend React Implementation

```tsx
// Product Form Component
import React from "react";
import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  CreateProductSchema,
  CreateProductSchemaType,
} from "@/types/zod-schemas";

interface ProductFormProps {
  onSubmit: (data: CreateProductSchemaType) => Promise<void>;
  categories: Array<{ id: number; name: string }>;
  brands: Array<{ id: number; name: string }>;
}

export const ProductForm: React.FC<ProductFormProps> = ({
  onSubmit,
  categories,
  brands,
}) => {
  const {
    register,
    control,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<CreateProductSchemaType>({
    resolver: zodResolver(CreateProductSchema),
    defaultValues: {
      track_inventory: false,
      allow_backorders: false,
      status: "draft",
      variants: [],
    },
  });

  const {
    fields: variantFields,
    append: appendVariant,
    remove: removeVariant,
  } = useFieldArray({
    control,
    name: "variants",
  });

  const trackInventory = watch("track_inventory");

  const handleFormSubmit = async (data: CreateProductSchemaType) => {
    try {
      await onSubmit(data);
    } catch (error) {
      console.error("Failed to create product:", error);
    }
  };

  return (
    <form onSubmit={handleSubmit(handleFormSubmit)} className="space-y-8">
      {/* Basic Information */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-medium mb-4">Basic Information</h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Product Name *
            </label>
            <input
              {...register("name")}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="Enter product name"
            />
            {errors.name && (
              <p className="text-red-500 text-sm mt-1">{errors.name.message}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              SKU *
            </label>
            <input
              {...register("sku")}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="Enter product SKU"
            />
            {errors.sku && (
              <p className="text-red-500 text-sm mt-1">{errors.sku.message}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Price *
            </label>
            <input
              {...register("price", { valueAsNumber: true })}
              type="number"
              step="0.01"
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="0.00"
            />
            {errors.price && (
              <p className="text-red-500 text-sm mt-1">
                {errors.price.message}
              </p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Compare At Price
            </label>
            <input
              {...register("compare_at_price", { valueAsNumber: true })}
              type="number"
              step="0.01"
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="0.00"
            />
            {errors.compare_at_price && (
              <p className="text-red-500 text-sm mt-1">
                {errors.compare_at_price.message}
              </p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Category *
            </label>
            <select
              {...register("category_id", { valueAsNumber: true })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
            >
              <option value="">Select a category</option>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
            {errors.category_id && (
              <p className="text-red-500 text-sm mt-1">
                {errors.category_id.message}
              </p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Brand
            </label>
            <select
              {...register("brand_id", { valueAsNumber: true })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
            >
              <option value="">Select a brand</option>
              {brands.map((brand) => (
                <option key={brand.id} value={brand.id}>
                  {brand.name}
                </option>
              ))}
            </select>
            {errors.brand_id && (
              <p className="text-red-500 text-sm mt-1">
                {errors.brand_id.message}
              </p>
            )}
          </div>
        </div>

        <div className="mt-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Description
          </label>
          <textarea
            {...register("description")}
            rows={4}
            className="w-full px-3 py-2 border border-gray-300 rounded-md"
            placeholder="Enter product description"
          />
          {errors.description && (
            <p className="text-red-500 text-sm mt-1">
              {errors.description.message}
            </p>
          )}
        </div>
      </div>

      {/* Inventory */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-medium mb-4">Inventory</h3>

        <div className="space-y-4">
          <div className="flex items-center">
            <input
              {...register("track_inventory")}
              type="checkbox"
              className="h-4 w-4 text-blue-600"
            />
            <label className="ml-2 text-sm text-gray-700">
              Track inventory quantity
            </label>
          </div>

          {trackInventory && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Inventory Quantity *
                </label>
                <input
                  {...register("inventory_quantity", { valueAsNumber: true })}
                  type="number"
                  min="0"
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                />
                {errors.inventory_quantity && (
                  <p className="text-red-500 text-sm mt-1">
                    {errors.inventory_quantity.message}
                  </p>
                )}
              </div>

              <div className="flex items-center">
                <input
                  {...register("allow_backorders")}
                  type="checkbox"
                  className="h-4 w-4 text-blue-600"
                />
                <label className="ml-2 text-sm text-gray-700">
                  Allow backorders when out of stock
                </label>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Variants */}
      <div className="bg-white shadow rounded-lg p-6">
        <div className="flex justify-between items-center mb-4">
          <h3 className="text-lg font-medium">Product Variants</h3>
          <button
            type="button"
            onClick={() => appendVariant({ name: "", values: [""] })}
            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
          >
            Add Variant
          </button>
        </div>

        {variantFields.map((field, index) => (
          <div
            key={field.id}
            className="border border-gray-200 rounded-lg p-4 mb-4"
          >
            <div className="flex justify-between items-center mb-4">
              <h4 className="font-medium">Variant {index + 1}</h4>
              <button
                type="button"
                onClick={() => removeVariant(index)}
                className="text-red-600 hover:text-red-800"
              >
                Remove
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Variant Name
                </label>
                <input
                  {...register(`variants.${index}.name`)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder="e.g., Color, Size"
                />
                {errors.variants?.[index]?.name && (
                  <p className="text-red-500 text-sm mt-1">
                    {errors.variants[index]?.name?.message}
                  </p>
                )}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Values (comma-separated)
                </label>
                <input
                  {...register(`variants.${index}.values.0`)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder="e.g., Red, Blue, Green"
                />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* SEO */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-medium mb-4">SEO</h3>

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              SEO Title
            </label>
            <input
              {...register("seo_title")}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="Optimized title for search engines"
              maxLength={60}
            />
            {errors.seo_title && (
              <p className="text-red-500 text-sm mt-1">
                {errors.seo_title.message}
              </p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              SEO Description
            </label>
            <textarea
              {...register("seo_description")}
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="Description that appears in search results"
              maxLength={160}
            />
            {errors.seo_description && (
              <p className="text-red-500 text-sm mt-1">
                {errors.seo_description.message}
              </p>
            )}
          </div>
        </div>
      </div>

      {/* Status */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-medium mb-4">Status</h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Status *
            </label>
            <select
              {...register("status")}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
            >
              <option value="draft">Draft</option>
              <option value="active">Active</option>
              <option value="archived">Archived</option>
            </select>
            {errors.status && (
              <p className="text-red-500 text-sm mt-1">
                {errors.status.message}
              </p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Published At
            </label>
            <input
              {...register("published_at")}
              type="datetime-local"
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
            />
            {errors.published_at && (
              <p className="text-red-500 text-sm mt-1">
                {errors.published_at.message}
              </p>
            )}
          </div>
        </div>
      </div>

      {/* Submit */}
      <div className="flex justify-end space-x-4">
        <button
          type="button"
          className="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
        >
          Cancel
        </button>
        <button
          type="submit"
          disabled={isSubmitting}
          className="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
        >
          {isSubmitting ? "Creating..." : "Create Product"}
        </button>
      </div>
    </form>
  );
};
```

### Checkout Flow Implementation

```tsx
// Checkout Component
import React, { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { CheckoutSchema, CheckoutSchemaType } from "@/types/zod-schemas";

export const CheckoutForm: React.FC = () => {
  const [step, setStep] = useState(1);

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<CheckoutSchemaType>({
    resolver: zodResolver(CheckoutSchema),
    defaultValues: {
      shipping_address: {
        same_as_billing: true,
      },
      marketing_opt_in: false,
      terms_accepted: false,
    },
  });

  const sameAsBilling = watch("shipping_address.same_as_billing");

  const onSubmit = async (data: CheckoutSchemaType) => {
    try {
      // Process checkout
      const response = await fetch("/api/checkout", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        throw new Error("Checkout failed");
      }

      const result = await response.json();

      // Redirect to confirmation page
      window.location.href = `/orders/${result.order.id}/confirmation`;
    } catch (error) {
      console.error("Checkout error:", error);
    }
  };

  return (
    <div className="max-w-4xl mx-auto p-6">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div className="space-y-8">
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-8">
            {/* Customer Information */}
            <div className="bg-white shadow rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">
                Contact Information
              </h2>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Email Address *
                  </label>
                  <input
                    {...register("customer.email")}
                    type="email"
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="your@email.com"
                  />
                  {errors.customer?.email && (
                    <p className="text-red-500 text-sm mt-1">
                      {errors.customer.email.message}
                    </p>
                  )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      First Name *
                    </label>
                    <input
                      {...register("customer.first_name")}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    />
                    {errors.customer?.first_name && (
                      <p className="text-red-500 text-sm mt-1">
                        {errors.customer.first_name.message}
                      </p>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Last Name *
                    </label>
                    <input
                      {...register("customer.last_name")}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    />
                    {errors.customer?.last_name && (
                      <p className="text-red-500 text-sm mt-1">
                        {errors.customer.last_name.message}
                      </p>
                    )}
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Phone Number
                  </label>
                  <input
                    {...register("customer.phone")}
                    type="tel"
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="+1 (555) 123-4567"
                  />
                  {errors.customer?.phone && (
                    <p className="text-red-500 text-sm mt-1">
                      {errors.customer.phone.message}
                    </p>
                  )}
                </div>
              </div>
            </div>

            {/* Billing Address */}
            <div className="bg-white shadow rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Billing Address</h2>

              <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      First Name *
                    </label>
                    <input
                      {...register("billing_address.first_name")}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    />
                    {errors.billing_address?.first_name && (
                      <p className="text-red-500 text-sm mt-1">
                        {errors.billing_address.first_name.message}
                      </p>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Last Name *
                    </label>
                    <input
                      {...register("billing_address.last_name")}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    />
                    {errors.billing_address?.last_name && (
                      <p className="text-red-500 text-sm mt-1">
                        {errors.billing_address.last_name.message}
                      </p>
                    )}
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Company (optional)
                  </label>
                  <input
                    {...register("billing_address.company")}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Address *
                  </label>
                  <input
                    {...register("billing_address.address1")}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="Street address"
                  />
                  {errors.billing_address?.address1 && (
                    <p className="text-red-500 text-sm mt-1">
                      {errors.billing_address.address1.message}
                    </p>
                  )}
                </div>

                <div>
                  <input
                    {...register("billing_address.address2")}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="Apartment, suite, etc. (optional)"
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      City *
                    </label>
                    <input
                      {...register("billing_address.city")}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    />
                    {errors.billing_address?.city && (
                      <p className="text-red-500 text-sm mt-1">
                        {errors.billing_address.city.message}
                      </p>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Province *
                    </label>
                    <input
                      {...register("billing_address.province")}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    />
                    {errors.billing_address?.province && (
                      <p className="text-red-500 text-sm mt-1">
                        {errors.billing_address.province.message}
                      </p>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Postal Code *
                    </label>
                    <input
                      {...register("billing_address.postal_code")}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    />
                    {errors.billing_address?.postal_code && (
                      <p className="text-red-500 text-sm mt-1">
                        {errors.billing_address.postal_code.message}
                      </p>
                    )}
                  </div>
                </div>
              </div>
            </div>

            {/* Shipping Address */}
            <div className="bg-white shadow rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Shipping Address</h2>

              <div className="space-y-4">
                <div className="flex items-center">
                  <input
                    {...register("shipping_address.same_as_billing")}
                    type="checkbox"
                    className="h-4 w-4 text-blue-600"
                  />
                  <label className="ml-2 text-sm text-gray-700">
                    Same as billing address
                  </label>
                </div>

                {!sameAsBilling && (
                  <div className="space-y-4 pt-4 border-t">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          First Name *
                        </label>
                        <input
                          {...register("shipping_address.first_name")}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                        {errors.shipping_address?.first_name && (
                          <p className="text-red-500 text-sm mt-1">
                            {errors.shipping_address.first_name.message}
                          </p>
                        )}
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Last Name *
                        </label>
                        <input
                          {...register("shipping_address.last_name")}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                        {errors.shipping_address?.last_name && (
                          <p className="text-red-500 text-sm mt-1">
                            {errors.shipping_address.last_name.message}
                          </p>
                        )}
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Address *
                      </label>
                      <input
                        {...register("shipping_address.address1")}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md"
                      />
                      {errors.shipping_address?.address1 && (
                        <p className="text-red-500 text-sm mt-1">
                          {errors.shipping_address.address1.message}
                        </p>
                      )}
                    </div>

                    {/* Additional shipping address fields... */}
                  </div>
                )}
              </div>
            </div>

            {/* Payment */}
            <div className="bg-white shadow rounded-lg p-6">
              <h2 className="text-xl font-semibold mb-4">Payment Method</h2>

              <div className="space-y-4">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  {[
                    { value: "credit_card", label: "Credit Card" },
                    { value: "paypal", label: "PayPal" },
                    { value: "apple_pay", label: "Apple Pay" },
                    { value: "google_pay", label: "Google Pay" },
                  ].map((method) => (
                    <label
                      key={method.value}
                      className="flex items-center space-x-2"
                    >
                      <input
                        {...register("payment_method")}
                        type="radio"
                        value={method.value}
                        className="h-4 w-4 text-blue-600"
                      />
                      <span className="text-sm">{method.label}</span>
                    </label>
                  ))}
                </div>
                {errors.payment_method && (
                  <p className="text-red-500 text-sm">
                    {errors.payment_method.message}
                  </p>
                )}
              </div>
            </div>

            {/* Final Options */}
            <div className="bg-white shadow rounded-lg p-6">
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Gift Message (optional)
                  </label>
                  <textarea
                    {...register("gift_message")}
                    rows={3}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="Add a personal message..."
                  />
                </div>

                <div className="space-y-2">
                  <label className="flex items-center">
                    <input
                      {...register("marketing_opt_in")}
                      type="checkbox"
                      className="h-4 w-4 text-blue-600"
                    />
                    <span className="ml-2 text-sm text-gray-700">
                      I'd like to receive marketing emails about new products
                      and offers
                    </span>
                  </label>

                  <label className="flex items-center">
                    <input
                      {...register("terms_accepted")}
                      type="checkbox"
                      className="h-4 w-4 text-blue-600"
                    />
                    <span className="ml-2 text-sm text-gray-700">
                      I accept the terms and conditions *
                    </span>
                  </label>
                  {errors.terms_accepted && (
                    <p className="text-red-500 text-sm">
                      {errors.terms_accepted.message}
                    </p>
                  )}
                </div>
              </div>
            </div>

            <button
              type="submit"
              disabled={isSubmitting}
              className="w-full bg-blue-600 text-white py-3 px-6 rounded-md text-lg font-medium hover:bg-blue-700 disabled:opacity-50"
            >
              {isSubmitting ? "Processing..." : "Complete Order"}
            </button>
          </form>
        </div>

        {/* Order Summary Sidebar */}
        <div className="lg:sticky lg:top-6">
          <div className="bg-gray-50 rounded-lg p-6">
            <h2 className="text-xl font-semibold mb-4">Order Summary</h2>
            {/* Order summary content */}
          </div>
        </div>
      </div>
    </div>
  );
};
```

## SaaS Application

### User Management System

```php
<?php

namespace App\Http\Requests\Admin;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Illuminate\Foundation\Http\FormRequest;

#[ValidationSchema]
class CreateTeamMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Personal Information
            'personal.first_name' => 'required|string|max:100',
            'personal.last_name' => 'required|string|max:100',
            'personal.email' => 'required|email|max:255|unique:users,email',
            'personal.phone' => 'nullable|regex:/^\+?[\d\s\-\(\)]+$/',
            'personal.avatar' => 'nullable|image|mimes:jpeg,png|max:2048',

            // Account Settings
            'account.username' => 'required|string|alpha_dash|min:3|max:50|unique:users,username',
            'account.password' => 'required|string|min:12|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/',
            'account.password_confirmation' => 'required|same:account.password',
            'account.timezone' => 'required|string|in:' . implode(',', timezone_identifiers_list()),
            'account.language' => 'required|string|in:en,es,fr,de,pt,ja,ko,zh',
            'account.two_factor_enabled' => 'boolean',

            // Role and Permissions
            'permissions.role' => 'required|string|exists:roles,name',
            'permissions.custom_permissions' => 'array|max:50',
            'permissions.custom_permissions.*' => 'string|exists:permissions,name',
            'permissions.department_access' => 'array|max:20',
            'permissions.department_access.*' => 'integer|exists:departments,id',

            // Billing and Limits
            'limits.api_rate_limit' => 'nullable|integer|min:100|max:10000',
            'limits.storage_limit_gb' => 'nullable|integer|min:1|max:1000',
            'limits.project_limit' => 'nullable|integer|min:1|max:100',
            'limits.team_member_limit' => 'nullable|integer|min:0|max:1000',

            // Integration Settings
            'integrations.slack_webhook' => 'nullable|url',
            'integrations.github_username' => 'nullable|string|alpha_dash|max:39',
            'integrations.jira_email' => 'nullable|email',
            'integrations.discord_user_id' => 'nullable|string|regex:/^[0-9]{17,19}$/',

            // Notification Preferences
            'notifications.email_enabled' => 'boolean',
            'notifications.push_enabled' => 'boolean',
            'notifications.sms_enabled' => 'boolean',
            'notifications.digest_frequency' => 'required|in:never,daily,weekly,monthly',
            'notifications.categories' => 'array|max:10',
            'notifications.categories.*' => 'string|in:security,billing,updates,marketing,system',

            // Onboarding
            'onboarding.send_welcome_email' => 'boolean',
            'onboarding.schedule_intro_call' => 'boolean',
            'onboarding.assign_mentor' => 'nullable|integer|exists:users,id',
            'onboarding.start_date' => 'nullable|date|after_or_equal:today',

            // Compliance
            'compliance.background_check_required' => 'boolean',
            'compliance.nda_signed' => 'boolean',
            'compliance.gdpr_consent' => 'required|boolean',
            'compliance.data_retention_agreement' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'personal.email.unique' => 'This email address is already registered',
            'account.username.unique' => 'This username is already taken',
            'account.password.regex' => 'Password must contain uppercase, lowercase, number, and special character',
            'permissions.role.exists' => 'Selected role does not exist',
            'limits.api_rate_limit.max' => 'API rate limit cannot exceed 10,000 requests per hour',
            'integrations.discord_user_id.regex' => 'Discord User ID must be 17-19 digits',
            'compliance.gdpr_consent.required' => 'GDPR consent is required',
            'compliance.data_retention_agreement.required' => 'Data retention agreement acceptance is required',
        ];
    }
}
```

### Subscription Management

```tsx
// Subscription Management Component
import React, { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  CreateTeamMemberSchema,
  CreateTeamMemberSchemaType,
} from "@/types/zod-schemas";

interface TeamMember {
  id: number;
  name: string;
  email: string;
  role: string;
  status: string;
}

export const TeamManagement: React.FC = () => {
  const [showAddMember, setShowAddMember] = useState(false);
  const [members, setMembers] = useState<TeamMember[]>([]);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<CreateTeamMemberSchemaType>({
    resolver: zodResolver(CreateTeamMemberSchema),
    defaultValues: {
      account: {
        two_factor_enabled: true,
        timezone: "UTC",
        language: "en",
      },
      notifications: {
        email_enabled: true,
        push_enabled: true,
        sms_enabled: false,
        digest_frequency: "weekly",
        categories: ["security", "updates"],
      },
      onboarding: {
        send_welcome_email: true,
        schedule_intro_call: false,
      },
      compliance: {
        background_check_required: false,
        nda_signed: false,
        gdpr_consent: false,
        data_retention_agreement: false,
      },
    },
  });

  const onSubmit = async (data: CreateTeamMemberSchemaType) => {
    try {
      const response = await fetch("/api/team/members", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        throw new Error("Failed to create team member");
      }

      const newMember = await response.json();
      setMembers((prev) => [...prev, newMember]);
      setShowAddMember(false);
      reset();
    } catch (error) {
      console.error("Error creating team member:", error);
    }
  };

  if (!showAddMember) {
    return (
      <div className="max-w-6xl mx-auto p-6">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold">Team Management</h1>
          <button
            onClick={() => setShowAddMember(true)}
            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
          >
            Add Team Member
          </button>
        </div>

        <div className="bg-white shadow rounded-lg overflow-hidden">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Member
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Role
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {members.map((member) => (
                <tr key={member.id}>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div className="text-sm font-medium text-gray-900">
                        {member.name}
                      </div>
                      <div className="text-sm text-gray-500">
                        {member.email}
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className="text-sm text-gray-900">{member.role}</span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span
                      className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        member.status === "active"
                          ? "bg-green-100 text-green-800"
                          : "bg-yellow-100 text-yellow-800"
                      }`}
                    >
                      {member.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button className="text-blue-600 hover:text-blue-900 mr-4">
                      Edit
                    </button>
                    <button className="text-red-600 hover:text-red-900">
                      Remove
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Add Team Member</h1>
        <button
          onClick={() => setShowAddMember(false)}
          className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
        >
          Cancel
        </button>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-8">
        {/* Personal Information */}
        <div className="bg-white shadow rounded-lg p-6">
          <h3 className="text-lg font-medium mb-4">Personal Information</h3>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                First Name *
              </label>
              <input
                {...register("personal.first_name")}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              />
              {errors.personal?.first_name && (
                <p className="text-red-500 text-sm mt-1">
                  {errors.personal.first_name.message}
                </p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Last Name *
              </label>
              <input
                {...register("personal.last_name")}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              />
              {errors.personal?.last_name && (
                <p className="text-red-500 text-sm mt-1">
                  {errors.personal.last_name.message}
                </p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Email Address *
              </label>
              <input
                {...register("personal.email")}
                type="email"
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              />
              {errors.personal?.email && (
                <p className="text-red-500 text-sm mt-1">
                  {errors.personal.email.message}
                </p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Phone Number
              </label>
              <input
                {...register("personal.phone")}
                type="tel"
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              />
              {errors.personal?.phone && (
                <p className="text-red-500 text-sm mt-1">
                  {errors.personal.phone.message}
                </p>
              )}
            </div>
          </div>
        </div>

        {/* Account Settings */}
        <div className="bg-white shadow rounded-lg p-6">
          <h3 className="text-lg font-medium mb-4">Account Settings</h3>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Username *
              </label>
              <input
                {...register("account.username")}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              />
              {errors.account?.username && (
                <p className="text-red-500 text-sm mt-1">
                  {errors.account.username.message}
                </p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Timezone *
              </label>
              <select
                {...register("account.timezone")}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              >
                <option value="UTC">UTC</option>
                <option value="America/New_York">Eastern Time</option>
                <option value="America/Chicago">Central Time</option>
                <option value="America/Denver">Mountain Time</option>
                <option value="America/Los_Angeles">Pacific Time</option>
              </select>
              {errors.account?.timezone && (
                <p className="text-red-500 text-sm mt-1">
                  {errors.account.timezone.message}
                </p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Password *
              </label>
              <input
                {...register("account.password")}
                type="password"
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              />
              {errors.account?.password && (
                <p className="text-red-500 text-sm mt-1">
                  {errors.account.password.message}
                </p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Confirm Password *
              </label>
              <input
                {...register("account.password_confirmation")}
                type="password"
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              />
              {errors.account?.password_confirmation && (
                <p className="text-red-500 text-sm mt-1">
                  {errors.account.password_confirmation.message}
                </p>
              )}
            </div>
          </div>

          <div className="mt-4">
            <label className="flex items-center">
              <input
                {...register("account.two_factor_enabled")}
                type="checkbox"
                className="h-4 w-4 text-blue-600"
              />
              <span className="ml-2 text-sm text-gray-700">
                Enable two-factor authentication
              </span>
            </label>
          </div>
        </div>

        {/* Permissions */}
        <div className="bg-white shadow rounded-lg p-6">
          <h3 className="text-lg font-medium mb-4">Permissions & Access</h3>

          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Role *
              </label>
              <select
                {...register("permissions.role")}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              >
                <option value="">Select a role</option>
                <option value="admin">Administrator</option>
                <option value="manager">Manager</option>
                <option value="developer">Developer</option>
                <option value="designer">Designer</option>
                <option value="viewer">Viewer</option>
              </select>
              {errors.permissions?.role && (
                <p className="text-red-500 text-sm mt-1">
                  {errors.permissions.role.message}
                </p>
              )}
            </div>
          </div>
        </div>

        {/* Compliance */}
        <div className="bg-white shadow rounded-lg p-6">
          <h3 className="text-lg font-medium mb-4">Compliance & Legal</h3>

          <div className="space-y-4">
            <label className="flex items-center">
              <input
                {...register("compliance.nda_signed")}
                type="checkbox"
                className="h-4 w-4 text-blue-600"
              />
              <span className="ml-2 text-sm text-gray-700">
                Non-disclosure agreement signed
              </span>
            </label>

            <label className="flex items-center">
              <input
                {...register("compliance.gdpr_consent")}
                type="checkbox"
                className="h-4 w-4 text-blue-600"
              />
              <span className="ml-2 text-sm text-gray-700">
                GDPR data processing consent *
              </span>
            </label>
            {errors.compliance?.gdpr_consent && (
              <p className="text-red-500 text-sm">
                {errors.compliance.gdpr_consent.message}
              </p>
            )}

            <label className="flex items-center">
              <input
                {...register("compliance.data_retention_agreement")}
                type="checkbox"
                className="h-4 w-4 text-blue-600"
              />
              <span className="ml-2 text-sm text-gray-700">
                Data retention agreement accepted *
              </span>
            </label>
            {errors.compliance?.data_retention_agreement && (
              <p className="text-red-500 text-sm">
                {errors.compliance.data_retention_agreement.message}
              </p>
            )}
          </div>
        </div>

        <div className="flex justify-end space-x-4">
          <button
            type="button"
            onClick={() => setShowAddMember(false)}
            className="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            {isSubmitting ? "Creating..." : "Create Team Member"}
          </button>
        </div>
      </form>
    </div>
  );
};
```

## Best Practices Summary

### Schema Organization

```typescript
// Group related schemas
import {
  // User management
  CreateTeamMemberSchema,
  UpdateUserProfileSchema,

  // E-commerce
  CreateProductSchema,
  CheckoutSchema,

  // Content management
  CreateArticleSchema,
  UpdateArticleSchema,
} from "@/types/zod-schemas";
```

### Error Handling Patterns

```typescript
// Centralized validation error handling
export class ValidationService {
  static handleValidationErrors(error: z.ZodError): Record<string, string> {
    const fieldErrors: Record<string, string> = {};

    error.issues.forEach((issue) => {
      const path = issue.path.join(".");
      fieldErrors[path] = issue.message;
    });

    return fieldErrors;
  }

  static async validateAndSubmit<T>(
    schema: z.ValidationSchema<T>,
    data: unknown,
    submitFn: (validData: T) => Promise<void>
  ): Promise<void> {
    const result = schema.safeParse(data);

    if (!result.success) {
      const errors = this.handleValidationErrors(result.error);
      throw new ValidationError("Validation failed", errors);
    }

    await submitFn(result.data);
  }
}
```

### Performance Optimization

```typescript
// Lazy load schemas for large applications
const SchemaLoader = {
  productSchema: null as z.ValidationSchema | null,

  async getProductSchema() {
    if (!this.productSchema) {
      const module = await import("@/types/schemas/product-schemas");
      this.productSchema = module.CreateProductSchema;
    }
    return this.productSchema;
  },
};
```

### Testing Strategies

```typescript
// Component testing with schema validation
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { CreateProductSchema } from '@/types/zod-schemas';
import { ProductForm } from './ProductForm';

describe('ProductForm', () => {
  it('validates form data according to schema', async () => {
    const validData = {
      name: 'Test Product',
      sku: 'TEST-001',
      price: 29.99,
      category_id: 1,
      status: 'draft',
    };

    // Ensure test data matches schema
    const result = CreateProductSchema.safeParse(validData);
    expect(result.success).toBe(true);

    const onSubmit = jest.fn();
    render(<ProductForm onSubmit={onSubmit} categories={[]} brands={[]} />);

    // Fill form and submit
    fireEvent.change(screen.getByPlaceholderText('Enter product name'), {
      target: { value: validData.name }
    });
    // ... fill other fields

    fireEvent.click(screen.getByText('Create Product'));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining(validData));
    });
  });
});
```

### Integration with State Management

```typescript
// Redux Toolkit with schema validation
import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { CreateProductSchema } from "@/types/zod-schemas";

export const createProduct = createAsyncThunk(
  "products/create",
  async (productData: unknown, { rejectWithValue }) => {
    // Validate with schema before API call
    const result = CreateProductSchema.safeParse(productData);

    if (!result.success) {
      return rejectWithValue(result.error.issues);
    }

    const response = await fetch("/api/products", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(result.data),
    });

    if (!response.ok) {
      throw new Error("Failed to create product");
    }

    return response.json();
  }
);
```

These real-world examples demonstrate how Laravel Zod Generator integrates into complete applications, providing type-safe validation from backend to frontend while maintaining development efficiency and code quality.

## Next Steps

- [FormRequest Examples](./form-request.md) - Focus on Laravel FormRequest patterns
- [Spatie Data Examples](./spatie-data.md) - Advanced Data class usage
- [Custom Validation Examples](./custom-validation.md) - Extending the generator
- [Integration Guide](../advanced/integration.md) - CI/CD and build processes
