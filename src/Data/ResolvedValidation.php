<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data;

/**
 * Structured validation data with IDE support for unified validation strategy
 */
class ResolvedValidation
{
    public function __construct(
        /** The validation rule name (e.g., 'required', 'min', 'max') */
        public readonly string $rule,

        /** The validation rule parameters (e.g., [5] for min:5, ['email'] for in:email) */
        public readonly array $parameters = [],

        /** Laravel-resolved validation message */
        public readonly ?string $message = null,

        /** Whether this validation makes the field required */
        public bool $isRequired = false,

        /** Whether this validation makes the field nullable */
        public bool $isNullable = false,
    ) {}

    /**
     * Create a collection of resolved validations without relying on Spatie Data.
     *
     * @param  iterable<ResolvedValidation>  $validations
     */
    public static function collect(iterable $validations = []): ResolvedValidationCollection
    {
        return ResolvedValidationCollection::make($validations);
    }

    /**
     * Check if this validation has a message
     */
    public function hasMessage(): bool
    {
        return $this->message !== null;
    }

    /**
     * Get the first parameter value (for single parameter rules)
     */
    public function getFirstParameter(): mixed
    {
        return $this->parameters[0] ?? null;
    }

    /**
     * Check if this validation has parameters
     */
    public function hasParameters(): bool
    {
        return ! empty($this->parameters);
    }

    /**
     * Get a specific parameter by index
     */
    public function getParameter(int $index): mixed
    {
        return $this->parameters[$index] ?? null;
    }

    /**
     * Get all parameters as an array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
