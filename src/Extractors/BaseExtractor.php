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
use RomegaSoftware\LaravelSchemaGenerator\Services\NestedValidationBuilder;

abstract class BaseExtractor implements ExtractorInterface
{
    use Macroable;

    protected NestedValidationBuilder $nestedValidationBuilder;

    public function __construct(
        protected LaravelValidationResolver $validationResolver,
        protected ValidationRuleFactory $ruleFactory = new ValidationRuleFactory,
        protected NestedRuleGrouper $ruleGrouper = new NestedRuleGrouper
    ) {
        $this->nestedValidationBuilder = new NestedValidationBuilder($validationResolver);
    }

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
        // Delegate to the specialized builder service
        return $this->nestedValidationBuilder->buildNestedObjectStructure($baseField, $nestedRules, $validator);
    }

    /**
     * Normalize all rules to string format
     */
    protected function normalizeRules(array $rules): array
    {
        $normalizedRules = [];
        foreach ($rules as $field => $rule) {
            $normalizedRules[$field] = $this->ruleFactory->normalizeRule($rule);
        }

        return $normalizedRules;
    }

    /**
     * Create validation properties from grouped rules
     */
    protected function createPropertiesFromGroupedRules(array $groupedRules, Validator $validator, array $metadata = []): array
    {
        $properties = [];

        foreach ($groupedRules as $baseField => $fieldRules) {
            $resolvedValidationSet = $this->resolveFieldValidation($baseField, $fieldRules, $validator, $metadata);

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
     * Resolve validation for a single field
     */
    protected function resolveFieldValidation(string $baseField, array $fieldRules, Validator $validator, array $metadata = []): ResolvedValidationSet
    {
        // Use builder for complex nested structures
        if (! empty($metadata)) {
            return $this->nestedValidationBuilder->buildFromMetadata($baseField, $fieldRules, $metadata, $validator);
        }

        // Handle nested rules
        if (isset($fieldRules['nested'])) {
            return $this->resolveArrayFieldWithNestedRules($baseField, $fieldRules, $validator);
        }

        // Regular field without nesting
        return $this->validationResolver->resolve($baseField, $fieldRules['rules'] ?? '', $validator);
    }
}
