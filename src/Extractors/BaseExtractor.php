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
     * Group validation rules by their base field name to handle nested arrays
     *
     * Example:
     * Input: [
     *   'items' => 'array',
     *   'items.*.variations' => 'array',
     *   'items.*.variations.*.type' => 'required|string',
     *   'items.*.variations.*.size' => 'string',
     *   'items.*.pricing' => 'array',
     *   'items.*.pricing.*.component' => 'required|in:base,tax,discount'
     * ]
     * Output: [
     *   'items' => [
     *     'rules' => 'array',
     *     'nested' => [
     *       'variations' => [
     *         'rules' => 'array',
     *         'nested' => [
     *           'type' => 'required|string',
     *           'size' => 'string'
     *         ]
     *       ],
     *       'pricing' => [
     *         'rules' => 'array',
     *         'nested' => [
     *           'component' => 'required|in:base,tax,discount'
     *         ]
     *       ]
     *     ]
     *   ]
     * ]
     */
    public function groupRulesByBaseField(array $rules): array
    {
        $grouped = [];

        // First pass: handle special nested object markers
        $nestedObjectFields = [];
        foreach ($rules as $field => $ruleSet) {
            if (str_ends_with($field, '.__isNestedObject') && $ruleSet === 'true') {
                $baseField = substr($field, 0, -17); // Remove .__isNestedObject
                $nestedObjectFields[$baseField] = true;
            }
        }

        foreach ($rules as $field => $ruleSet) {
            // Skip special marker fields
            if (str_contains($field, '.__isNestedObject') ||
                str_contains($field, '.__baseRules') ||
                str_contains($field, '.__nested.')) {
                continue;
            }

            if (str_contains($field, '.*')) {
                // Check if this is a nested object within an array
                $parts = explode('.*', $field, 2);
                $baseField = $parts[0];
                $remainingPath = $parts[1] ?? '';

                if ($remainingPath && ! str_contains($remainingPath, '.*')) {
                    // Check if this path corresponds to a nested object
                    $nestedObjectPath = $baseField.'.*.'.explode('.', ltrim($remainingPath, '.'))[0];
                    if (isset($nestedObjectFields[$nestedObjectPath])) {
                        // This is part of a nested object, handle it specially
                        $this->ruleGrouper->addNestedObjectInArray($grouped, $field, $ruleSet, $rules, $nestedObjectFields);

                        continue;
                    }
                }

                // Regular wildcard field
                $this->ruleGrouper->addNestedRule($grouped, $field, $ruleSet);
            } else {
                // Regular field or base array field
                if (! isset($grouped[$field])) {
                    $grouped[$field] = [
                        'rules' => $ruleSet,
                        'nested' => [],
                    ];
                } else {
                    // Field already exists from wildcard processing, add base rules
                    $grouped[$field]['rules'] = $ruleSet;
                }
            }
        }

        // Clean up - remove nested array if empty, mark as having nested rules if not
        $this->cleanupGroupedRules($grouped);

        return $grouped;
    }

    /**
     * Recursively handle nested array structures within the nested rules
     */
    public function addNestedRuleRecursively(array &$nested, string $path, string $ruleSet): void
    {
        if (str_contains($path, '.*')) {
            // Split on the first .* occurrence in the remaining path
            $parts = explode('.*', $path, 2);
            $currentField = $parts[0];
            $remainingPath = $parts[1] ?? '';

            // Initialize or convert nested field structure
            if (! isset($nested[$currentField])) {
                $nested[$currentField] = [
                    'rules' => null,
                    'nested' => [],
                ];
            } elseif (is_string($nested[$currentField])) {
                // Convert existing string rule to structured format
                $existingRule = $nested[$currentField];
                $nested[$currentField] = [
                    'rules' => $existingRule,
                    'nested' => [],
                ];
            }

            if ($remainingPath === '') {
                // Direct array items at this level
                $nested[$currentField]['nested']['*'] = $ruleSet;
            } else {
                // Remove leading dot and continue recursively
                $remainingPath = ltrim($remainingPath, '.');

                if (str_contains($remainingPath, '.*')) {
                    // More wildcards ahead - recurse deeper
                    $this->addNestedRuleRecursively($nested[$currentField]['nested'], $remainingPath, $ruleSet);
                } else {
                    // Final property - add it as a simple rule
                    $nested[$currentField]['nested'][$remainingPath] = $ruleSet;
                }
            }
        } else {
            // No wildcards left - simple property, store directly as rule
            $nested[$path] = $ruleSet;
        }
    }

    /**
     * Clean up grouped rules by removing empty nested arrays
     */
    public function cleanupGroupedRules(array &$grouped): void
    {
        foreach ($grouped as $field => &$data) {
            if (isset($data['nested'])) {
                $this->cleanupNestedRules($data['nested']);

                if (empty($data['nested'])) {
                    unset($data['nested']);
                }
            }
        }
    }

    /**
     * Recursively clean up nested rule structures
     */
    public function cleanupNestedRules(array &$nested): void
    {
        foreach ($nested as $key => &$value) {
            if (is_array($value) && isset($value['nested'])) {
                $this->cleanupNestedRules($value['nested']);

                if (empty($value['nested'])) {
                    unset($value['nested']);
                }
            }
        }
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
