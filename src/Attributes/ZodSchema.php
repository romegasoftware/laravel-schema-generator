<?php

namespace RomegaSoftware\LaravelZodGenerator\Attributes;

use Attribute;

/**
 * Indicates that a Zod validation schema should be generated for this class.
 *
 * This attribute can be applied to:
 * - Spatie Data classes (when spatie/laravel-data is installed)
 * - Laravel FormRequest classes
 * - Any PHP class with a rules() method
 *
 * @example
 * #[ZodSchema]
 * class UserCreateRequest extends FormRequest
 * {
 *     public function rules(): array
 *     {
 *         return [
 *             'email' => 'required|email',
 *             'password' => 'required|min:8',
 *         ];
 *     }
 * }
 * @example With custom schema name
 * #[ZodSchema(name: 'CustomUserSchema')]
 * class UserData extends Data
 * {
 *     // ...
 * }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ZodSchema
{
    public function __construct(
        public ?string $name = null,
    ) {}
}
