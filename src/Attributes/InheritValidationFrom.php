<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Attributes;

use Attribute;

/**
 * Inherit validation rules from another class's property.
 *
 * This is particularly useful when you want to reuse validation rules
 * from shared data objects or other classes.
 *
 * @example
 * class PostalCodeData extends Data
 * {
 *     public function __construct(
 *         #[StringType, Regex('/^\d{5}(-\d{4})?$/')]
 *         public string $code
 *     ) {}
 * }
 *
 * class AddressData extends Data
 * {
 *     public function __construct(
 *         #[InheritValidationFrom(PostalCodeData::class, 'code')]
 *         public string $postal_code
 *     ) {}
 * }
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
class InheritValidationFrom
{
    public function __construct(
        /**
         * The fully-qualified class name to inherit validation from
         */
        public string $class,

        /**
         * The property name in the target class to inherit from.
         * If null, uses the same property name as the current property.
         */
        public ?string $property = null,

        /**
         * Whether inherited rules should also be enforced at runtime validation.
         */
        public bool $enforceRuntime = true
    ) {}
}
