<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;

/**
 * Simplified Laravel validation resolver with single responsibility:
 * Transform Laravel rules to structured ResolvedValidation format
 */
class LaravelValidationResolver
{
    private static ?array $typeInferenceRules = null;

    /**
     * Resolve Laravel validation rules into structured format
     */
    public function resolve(string $field, string $rules, Validator $validator): ResolvedValidationSet
    {
        $explodedRules = (new ValidationRuleParser($validator->getData()))
            ->explode(ValidationRuleParser::filterConditionalRules($rules, $validator->getData()));

        // Convert rules to ResolvedValidation objects
        $resolvedValidations = $this->resolveValidationRules($explodedRules->rules[0], $field, $validator);

        // Infer type from validation rules - use the first rule set
        // Convert array of rule strings to associative array for inferType
        $rulesForInference = [];
        foreach ($explodedRules->rules[0] as $rule) {
            [$ruleName, $parameters] = ValidationRuleParser::parse($rule);
            $rulesForInference[strtolower($ruleName)] = $parameters ?: true;
        }
        $inferredType = $this->inferType($rulesForInference);

        // Check if this is a wildcard field and resolve nested validations if needed
        $nestedValidations = null;
        if ($this->isWildcardField($field)) {
            $nestedValidations = $this->resolveWildcardField($field, $explodedRules->rules[0], $validator);
        }

        return ResolvedValidationSet::make($field, $resolvedValidations, $inferredType, $nestedValidations);
    }

    /**
     * Infer type from validation rules
     */
    private function inferType(array $validations): string
    {
        // Initialize type inference rules if not cached
        if (self::$typeInferenceRules === null) {
            self::$typeInferenceRules = $this->buildTypeInferenceRules();
        }

        // Check for direct type indicators in priority order
        foreach (self::$typeInferenceRules as $rule => $type) {
            if (isset($validations[$rule])) {
                // Handle enum special case
                if ($type === 'enum' && isset($validations['in']) && is_array($validations['in'])) {
                    return 'enum:'.implode(',', $validations['in']);
                }

                return $type;
            }
        }

        // Check for enum rules (in rule with multiple values)
        if (isset($validations['in']) && is_array($validations['in']) && count($validations['in']) > 1) {
            return 'enum:'.implode(',', $validations['in']);
        }

        if (isset($validations['fieldName']) && str_ends_with($validations['fieldName'], '*')) {
            return 'array';
        }

        // Default to string if no specific type detected
        return 'string';
    }

    /**
     * Build type inference rules mapping
     */
    private function buildTypeInferenceRules(): array
    {
        return [
            // Boolean types
            'boolean' => 'boolean',
            'bool' => 'boolean',

            // Number types
            'integer' => 'number',
            'numeric' => 'number',
            'int' => 'number',
            'float' => 'number',
            'decimal' => 'number',

            // Array types
            'array' => 'array',

            // Special string types
            'email' => 'email',
            'url' => 'url',
            'uuid' => 'uuid',
            'ulid' => 'string', // ULID as string for now
            'ip' => 'string',
            'ipv4' => 'string',
            'ipv6' => 'string',
            'mac_address' => 'string',
            'json' => 'string',
            'date' => 'string', // Date as string in TypeScript
            'date_format' => 'string',
            'before' => 'string',
            'after' => 'string',

            // File types (treated as special strings)
            'file' => 'string',
            'image' => 'string',
            'mimes' => 'string',
            'mimetypes' => 'string',

            // Enum indicator
            'in' => 'enum',

            // Default string type
            'string' => 'string',
        ];
    }

    /**
     * Check if a field is a wildcard field (contains .*)
     */
    private function isWildcardField(string $field): bool
    {
        return str_contains($field, '.*');
    }

    /**
     * Resolve wildcard field rules into nested validation structure
     */
    private function resolveWildcardField(string $field, array $rules, Validator $validator): ?ResolvedValidationSet
    {
        // Extract the item field name by removing the wildcard
        // e.g., "tags.*" becomes "tags.*[item]"
        $itemField = rtrim($field, '.*').'.*[item]';

        // Convert rules to ResolvedValidation objects using the extracted method
        $resolvedValidations = $this->resolveValidationRules($rules, $itemField, $validator);

        // Infer type for the nested item
        $inferredType = $this->inferType($rules);

        // Check for further nesting and handle recursively
        $nestedValidations = null;
        if ($this->hasDeepernesting($field)) {
            $nestedValidations = $this->resolveDeepNestedField($field, $rules, $validator);
        }

        return ResolvedValidationSet::make($itemField, $resolvedValidations, $inferredType, $nestedValidations);
    }

    /**
     * Check if the field has deeper nesting beyond one level
     */
    private function hasDeepernesting(string $field): bool
    {
        // Count the number of .* occurrences
        return substr_count($field, '.*') > 1;
    }

    /**
     * Handle deep nested field resolution recursively
     * Creates a chain of nested validations for multi-level wildcards like "items.*.variants.*"
     */
    private function resolveDeepNestedField(string $field, array $rules, Validator $validator): ?ResolvedValidationSet
    {
        // Find the position of the second .* occurrence
        $firstWildcard = strpos($field, '.*');
        if ($firstWildcard === false) {
            return null;
        }

        $secondWildcard = strpos($field, '.*', $firstWildcard + 2);
        if ($secondWildcard === false) {
            return null; // No second wildcard found
        }

        // Extract the nested field (everything after the first .*)
        $nestedField = substr($field, $firstWildcard + 2);

        // If the nested field still contains wildcards, process it recursively
        if ($this->isWildcardField($nestedField)) {
            return $this->resolveWildcardField($nestedField, $rules, $validator);
        }

        // If no more wildcards, this shouldn't happen with proper deep nesting detection
        return null;
    }

    /**
     * Convert individual validation rules to ResolvedValidation objects
     */
    private function resolveValidationRules(array $rules, string $field, Validator $validator): array
    {
        $resolvedValidations = [];

        foreach ($rules as $rule) {
            [$ruleName, $parameters] = ValidationRuleParser::parse($rule);

            $validatorReflection = new ReflectionClass($validator);
            $getMessage = $validatorReflection->getMethod('getMessage');

            $message = $validator->makeReplacements(
                $getMessage->invoke($validator, $field, $ruleName),
                $field,
                $ruleName,
                $parameters
            );

            $resolvedValidation = new ResolvedValidation(
                rule: $ruleName,
                parameters: $parameters,
                message: $message,
                isRequired: false,
                isNullable: false
            );

            switch ($ruleName) {
                case 'Required':
                    $resolvedValidation->isRequired = true;
                    break;
                case 'Nullable':
                    $resolvedValidation->isNullable = true;
                    break;
            }
            $resolvedValidations[] = $resolvedValidation;
        }

        return $resolvedValidations;
    }
}
