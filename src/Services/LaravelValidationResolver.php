<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Traits\Makeable;

/**
 * Simplified Laravel validation resolver with single responsibility:
 * Transform Laravel rules to structured ResolvedValidation format
 */
class LaravelValidationResolver
{
    use Makeable;

    public function __construct(
        private MessageResolutionService $messageService = new MessageResolutionService,
        private TypeInferenceService $typeInferenceService = new TypeInferenceService
    ) {}

    /**
     * Resolve Laravel validation rules into structured format
     */
    public function resolve(string $field, string $rules, Validator $validator): ResolvedValidationSet
    {
        // Handle empty rules
        if (empty($rules)) {
            return ResolvedValidationSet::make($field, [], 'string', null);
        }

        $explodedRules = (new ValidationRuleParser($validator->getData()))
            ->explode(ValidationRuleParser::filterConditionalRules([$rules], $validator->getData()));

        // Convert rules to ResolvedValidation objects
        $resolvedValidations = $this->resolveValidationRules($explodedRules->rules[0], $field, $validator);

        // Infer type from validation rules - use the first rule set
        // Convert array of rule strings to associative array for inferType
        $rulesForInference = [];
        foreach ($explodedRules->rules[0] as $rule) {
            [$ruleName, $parameters] = ValidationRuleParser::parse($rule);
            $rulesForInference[strtolower($ruleName)] = $parameters ?: true;
        }
        $inferredType = $this->typeInferenceService->inferType($rulesForInference);

        // Check if this is a wildcard field and resolve nested validations if needed
        $nestedValidations = null;
        if ($this->isWildcardField($field)) {
            $nestedValidations = $this->resolveWildcardField($field, $explodedRules->rules[0], $validator);
        }

        return ResolvedValidationSet::make($field, $resolvedValidations, $inferredType, $nestedValidations);
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
    private function resolveWildcardField(string $field, array $rules, Validator $validator): ResolvedValidationSet
    {
        // Extract the item field name by removing the wildcard
        // e.g., "tags.*" becomes "tags.*[item]"
        $itemField = rtrim($field, '.*').'.*[item]';

        // Convert rules to ResolvedValidation objects using the extracted method
        $resolvedValidations = $this->resolveValidationRules($rules, $itemField, $validator);

        // Infer type for the nested item
        $rulesForInference = [];
        foreach ($rules as $rule) {
            [$ruleName, $parameters] = ValidationRuleParser::parse($rule);
            $rulesForInference[strtolower($ruleName)] = $parameters ?: true;
        }
        $inferredType = $this->typeInferenceService->inferType($rulesForInference);

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

        // Determine if this field is numeric based on validation rules
        $isNumericField = $this->typeInferenceService->isNumericField($rules);

        foreach ($rules as $rule) {
            [$ruleName, $parameters] = ValidationRuleParser::parse($rule);

            // Resolve the message using the message service
            $message = $this->messageService->with(
                field: $field,
                ruleName: $ruleName,
                validator: $validator,
                parameters: $parameters,
                isNumericField: $isNumericField,
                rules: $rules,
            )->resolveCustomMessage();

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
                case 'Present':
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
