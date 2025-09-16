<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\Validator;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\ExtractorInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ValidationRuleFactory;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use RomegaSoftware\LaravelSchemaGenerator\Services\NestedRuleGrouper;

abstract class BaseExtractor implements ExtractorInterface
{
    use Macroable;

    public function __construct(
        protected LaravelValidationResolver $validationResolver,
        protected ValidationRuleFactory $ruleFactory = new ValidationRuleFactory,
        protected NestedRuleGrouper $ruleGrouper = new NestedRuleGrouper
    ) {}

    /**
     * ex $rules = [
     *   'items' => 'array',
     *   'items.*.variations' => 'array',
     *   'items.*.variations.*.type' => 'required|string',
     * ];
     *
     * @param  array<string, mixed>  $rules  Can be strings, arrays, or rule objects
     * @return SchemaPropertyData[]
     */
    public function resolveRulesFromValidator(Validator $validator, array $rules): array
    {
        // First, normalize all rules to string format
        $normalizedRules = [];
        foreach ($rules as $field => $rule) {
            $normalizedRules[$field] = $this->ruleFactory->normalizeRule($rule);
        }

        // Group rules by base field for nested array handling
        $groupedRules = $this->ruleGrouper->groupRulesByBaseField($normalizedRules);
        $properties = [];

        foreach ($groupedRules as $baseField => $fieldRules) {
            if (isset($fieldRules['nested'])) {
                // This is an array field with nested rules
                $resolvedValidationSet = $this->resolveArrayFieldWithNestedRules(
                    $baseField,
                    $fieldRules,
                    $validator
                );
            } else {
                // Regular field without nesting
                $resolvedValidationSet = $this->validationResolver->resolve($baseField, $fieldRules['rules'], $validator);
            }

            $properties[] = new SchemaPropertyData(
                name: $baseField,
                validator: $validator,
                isOptional: ! $resolvedValidationSet->isFieldRequired(),
                validations: $resolvedValidationSet,
            );
        }

        return $properties;
    }

    /**
     * Resolve array field with nested validation rules
     */
    public function resolveArrayFieldWithNestedRules(string $baseField, array $fieldRules, $validator): ResolvedValidationSet
    {
        // Resolve base array rules if they exist
        $baseRules = $fieldRules['rules'] ?? 'array';
        $baseValidationSet = $this->validationResolver->resolve($baseField, $baseRules, $validator);

        // Create nested validation structure
        $nestedValidations = null;
        if (! empty($fieldRules['nested'])) {
            if (isset($fieldRules['nested']['*'])) {
                // Direct array items (e.g., tags.*)
                $itemValidationSet = $this->validationResolver->resolve(
                    $baseField.'.*',
                    $fieldRules['nested']['*'],
                    $validator
                );
                $nestedValidations = $itemValidationSet;
            } else {
                // Nested object properties (e.g., categories.*.title)
                $nestedValidations = $this->createNestedObjectValidation($baseField, $fieldRules['nested'], $validator);
            }
        }

        // Create final validation set with nested structure
        return ResolvedValidationSet::make(
            $baseField,
            $baseValidationSet->validations->all(),
            'array',
            $nestedValidations
        );
    }

    /**
     * Create nested object validation for array items with multiple properties
     * Handles multi-level nesting recursively
     * Example: categories.*.title, categories.*.slug -> object with title and slug properties
     * Example: items.*.variations.*.type -> nested array with objects containing type property
     */
    public function createNestedObjectValidation(string $baseField, array $nestedRules, $validator): ResolvedValidationSet
    {
        $objectProperties = [];

        foreach ($nestedRules as $property => $rules) {
            if (is_array($rules) && isset($rules['nested'])) {
                // Check if this is a nested object (not an array)
                if (isset($rules['isNestedObject']) && $rules['isNestedObject']) {
                    // This is a nested object within the array item
                    $nestedObjectValidationSet = $this->createNestedObjectValidation(
                        $baseField.'.*.'.$property,
                        $rules['nested'],
                        $validator
                    );

                    // Strip the .* suffix from the property key for cleaner names in TypeScript
                    $cleanPropertyKey = str_replace('.*', '', $property);

                    // Override the inferred type to be 'object' instead of array
                    $objectProperties[$cleanPropertyKey] = ResolvedValidationSet::make(
                        fieldName: $baseField.'.*.'.$property,
                        validations: $nestedObjectValidationSet->validations->all(),
                        inferredType: 'object',
                        nestedValidations: null,
                        objectProperties: $nestedObjectValidationSet->objectProperties
                    );
                } else {
                    // This property is itself a nested array structure
                    $nestedValidationSet = $this->resolveArrayFieldWithNestedRules(
                        $baseField.'.*.'.$property,
                        $rules,
                        $validator
                    );

                    // Strip the .* suffix from the property key for cleaner names in TypeScript
                    $cleanPropertyKey = str_replace('.*', '', $property);
                    $objectProperties[$cleanPropertyKey] = $nestedValidationSet;
                }
            } else {
                // Simple property - ensure rules is a string
                $rulesString = is_array($rules) ? (isset($rules['rules']) ? $rules['rules'] : '') : $rules;

                // Strip the .* suffix from the property key for cleaner names in TypeScript
                $cleanPropertyKey = str_replace('.*', '', $property);

                $propertyValidationSet = $this->validationResolver->resolve(
                    $baseField.'.*.'.$property,
                    $rulesString,
                    $validator
                );
                $objectProperties[$cleanPropertyKey] = $propertyValidationSet;
            }
        }

        // Create a validation set that represents an object with nested properties
        return ResolvedValidationSet::make(
            fieldName: $baseField.'.*[object]',
            validations: [],
            inferredType: 'object',
            nestedValidations: null,
            objectProperties: $objectProperties
        );
    }
}
