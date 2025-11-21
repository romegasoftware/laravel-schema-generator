<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data;

use Illuminate\Support\Collection;

/**
 * Collection of resolved validations for a field with convenient access methods
 */
class ResolvedValidationSet
{
    public function __construct(
        /** Field name this validation set applies to */
        public readonly string $fieldName,

        /** Collection of resolved validation rules */
        public readonly ResolvedValidationCollection $validations,

        /** Inferred type based on validation rules */
        public readonly string $inferredType = 'string',

        /** Whether the field is required */
        public readonly bool $isRequired = false,

        /** Whether the field is nullable */
        public readonly bool $isNullable = false,

        /** Nested validation set for array items (for wildcard fields like tags.*) */
        public readonly ?ResolvedValidationSet $nestedValidations = null,

        /** @var array<string, ResolvedValidationSet> Object properties for nested objects (for fields like categories.*.title) */
        public readonly array $objectProperties = [],
    ) {}

    /**
     * Create a new validation set from an array of ResolvedValidation objects
     */
    public static function make(
        string $fieldName,
        array $validations,
        string $inferredType = 'string',
        ?ResolvedValidationSet $nestedValidations = null,
        array $objectProperties = []
    ): self {
        $isRequired = false;
        $isNullable = false;

        foreach ($validations as $validation) {
            if ($validation->isRequired) {
                $isRequired = true;
            }
            if ($validation->isNullable) {
                $isNullable = true;
            }
        }

        return new self(
            fieldName: $fieldName,
            validations: ResolvedValidation::collect($validations),
            inferredType: $inferredType,
            isRequired: $isRequired,
            isNullable: $isNullable,
            nestedValidations: $nestedValidations,
            objectProperties: $objectProperties
        );
    }

    /**
     * Check if a specific validation rule exists
     */
    public function hasValidation(string $rule): bool
    {
        return $this->getValidation($rule) !== null;
    }

    /**
     * Get a specific validation by rule name
     */
    public function getValidation(string $rule): ?ResolvedValidation
    {
        $normalizedRule = strtolower($rule);

        return $this->validations
            ->toCollection()
            ->first(fn (ResolvedValidation $v) => strtolower($v->rule) === $normalizedRule);
    }

    /**
     * Get all validations with a specific rule name (for rules that can appear multiple times)
     */
    public function getValidations(string $rule): Collection
    {
        $normalizedRule = strtolower($rule);

        return $this->validations
            ->toCollection()
            ->filter(fn (ResolvedValidation $v) => strtolower($v->rule) === $normalizedRule);
    }

    /**
     * Get message for a specific rule
     */
    public function getMessage(string $rule): ?string
    {
        $validation = $this->getValidation($rule);

        return $validation?->message;
    }

    /**
     * Get the first parameter for a specific validation rule
     */
    public function getValidationParameter(string $rule): mixed
    {
        $validation = $this->getValidation($rule);

        return $validation?->getFirstParameter();
    }

    /**
     * Get all parameters for a specific validation rule
     */
    public function getValidationParameters(string $rule): array
    {
        $validation = $this->getValidation($rule);

        return $validation?->getParameters() ?? [];
    }

    /**
     * Check if the field has any validations that would make it required
     */
    public function isFieldRequired(): bool
    {
        return $this->isRequired;
    }

    /**
     * Check if the field has any validations that would make it nullable
     */
    public function isFieldNullable(): bool
    {
        return $this->isNullable;
    }

    /**
     * Get all validation rules as an array of rule names
     */
    public function getRuleNames(): array
    {
        return $this->validations->toCollection()->map(fn (ResolvedValidation $v) => $v->rule)->toArray();
    }

    /**
     * Check if this validation set has nested validations (for array items)
     */
    public function hasNestedValidations(): bool
    {
        return $this->nestedValidations !== null;
    }

    /**
     * Get nested validation set for array items
     */
    public function getNestedValidations(): ?ResolvedValidationSet
    {
        return $this->nestedValidations;
    }

    /**
     * Check if this validation set has object properties (for nested objects)
     */
    public function hasObjectProperties(): bool
    {
        return ! empty($this->objectProperties);
    }

    /**
     * Get object properties for nested objects
     *
     * @return array<string, ResolvedValidationSet>
     */
    public function getObjectProperties(): array
    {
        return $this->objectProperties;
    }

    /**
     * Convert to array for backward compatibility with existing validation rules
     */
    public function toValidationArray(): array
    {
        $result = [
            'required' => $this->isRequired,
            'nullable' => $this->isNullable,
        ];

        foreach ($this->validations as $validation) {
            if ($validation->hasParameters()) {
                if (count($validation->parameters) === 1) {
                    $result[$validation->rule] = $validation->getFirstParameter();
                } else {
                    $result[$validation->rule] = $validation->parameters;
                }
            } else {
                $result[$validation->rule] = true;
            }
        }

        // Add custom messages if any exist
        $customMessages = [];
        foreach ($this->validations as $validation) {
            if ($validation->message !== null) {
                $customMessages[$validation->rule] = $validation->message;
            }
        }

        if (! empty($customMessages)) {
            $result['customMessages'] = $customMessages;
        }

        return $result;
    }
}
