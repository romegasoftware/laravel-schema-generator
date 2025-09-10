<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\Validator;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\ExtractorInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;

abstract class BaseExtractor implements ExtractorInterface
{
    use Macroable;

    public function __construct(
        protected LaravelValidationResolver $validationResolver
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
            $normalizedRules[$field] = $this->normalizeRule($rule);
        }

        // Group rules by base field for nested array handling
        $groupedRules = $this->groupRulesByBaseField($normalizedRules);
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
     * Normalize a rule to string format
     * Handles strings, arrays, and Laravel rule objects
     *
     * @param  mixed  $rule
     */
    protected function normalizeRule($rule): string
    {
        if (is_string($rule)) {
            return $rule;
        }

        if (is_array($rule)) {
            $normalizedRules = [];
            foreach ($rule as $singleRule) {
                if (is_string($singleRule)) {
                    $normalizedRules[] = $singleRule;
                } elseif (is_object($singleRule)) {
                    // Handle Laravel rule objects
                    $normalizedRules[] = $this->resolveRuleObject($singleRule);
                } else {
                    // Skip non-string, non-object rules
                    continue;
                }
            }

            return implode('|', $normalizedRules);
        }

        if (is_object($rule)) {
            return $this->resolveRuleObject($rule);
        }

        // Default to empty string for unhandled types
        return '';
    }

    /**
     * Resolve a Laravel rule object to its string representation
     *
     * @param  object  $rule
     */
    protected function resolveRuleObject($rule): string
    {
        // Check if it's a Laravel validation rule object that implements __toString
        if (method_exists($rule, '__toString')) {
            return (string) $rule;
        }

        // Special handling for Enum rule which doesn't have __toString
        if ($rule instanceof \Illuminate\Validation\Rules\Enum) {
            // Try to extract the enum values from the Enum rule
            $enumValues = $this->extractEnumValues($rule);
            if (! empty($enumValues)) {
                // Convert to 'in' rule with the enum values
                return 'in:'.implode(',', $enumValues);
            }

            // Fallback to generic enum rule
            return 'enum';
        }

        // For other rule objects, try to get the class name as a fallback
        $className = get_class($rule);
        $shortName = substr($className, strrpos($className, '\\') + 1);

        return strtolower($shortName);
    }

    /**
     * Extract enum values from an Enum rule object
     */
    protected function extractEnumValues(\Illuminate\Validation\Rules\Enum $enumRule): array
    {
        try {
            // Use reflection to access the protected type property
            $reflection = new \ReflectionClass($enumRule);
            $typeProperty = $reflection->getProperty('type');
            $typeProperty->setAccessible(true);
            $enumClass = $typeProperty->getValue($enumRule);

            // Check if there's an 'only' property for filtered values
            if ($reflection->hasProperty('only')) {
                $onlyProperty = $reflection->getProperty('only');
                $onlyProperty->setAccessible(true);
                $onlyValues = $onlyProperty->getValue($enumRule);

                if (! empty($onlyValues)) {
                    // Return the filtered enum values
                    $values = [];
                    foreach ($onlyValues as $enumCase) {
                        $values[] = $enumCase->value ?? $enumCase->name;
                    }

                    return $values;
                }
            }

            // Get all enum cases if it's a valid enum class
            if (enum_exists($enumClass)) {
                $values = [];
                foreach ($enumClass::cases() as $case) {
                    // For backed enums, use the value; for pure enums, use the name
                    $values[] = $case->value ?? $case->name;
                }

                return $values;
            }
        } catch (\Exception $e) {
            // If we can't extract values, return empty array
        }

        return [];
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

        foreach ($rules as $field => $ruleSet) {
            if (str_contains($field, '.*')) {
                // This is a wildcard field - handle multi-level nesting recursively
                $this->addNestedRule($grouped, $field, $ruleSet);
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
     * Recursively add nested rules to the grouped structure
     * Handles multi-level wildcards like items.*.variations.*.type
     */
    public function addNestedRule(array &$grouped, string $field, string $ruleSet): void
    {
        // Split on the first .* occurrence only
        $parts = explode('.*', $field, 2);
        $baseField = $parts[0];
        $remainingPath = $parts[1] ?? '';

        // Initialize base field if not exists
        if (! isset($grouped[$baseField])) {
            $grouped[$baseField] = [
                'rules' => null,
                'nested' => [],
            ];
        }

        if ($remainingPath === '') {
            // Direct array items (e.g., tags.*)
            $grouped[$baseField]['nested']['*'] = $ruleSet;
        } else {
            // Remove leading dot and process remaining path
            $remainingPath = ltrim($remainingPath, '.');

            if (str_contains($remainingPath, '.*')) {
                // Still has wildcards - need to handle nested arrays recursively
                $this->addNestedRuleRecursively($grouped[$baseField]['nested'], $remainingPath, $ruleSet);
            } else {
                // No more wildcards - this is a simple nested property
                // But we need to make sure we don't overwrite existing structured data
                if (! isset($grouped[$baseField]['nested'][$remainingPath])) {
                    $grouped[$baseField]['nested'][$remainingPath] = $ruleSet;
                } elseif (is_array($grouped[$baseField]['nested'][$remainingPath]) && isset($grouped[$baseField]['nested'][$remainingPath]['rules'])) {
                    // Already a structured field, update its rules
                    $grouped[$baseField]['nested'][$remainingPath]['rules'] = $ruleSet;
                } else {
                    // Simple field, just update it
                    $grouped[$baseField]['nested'][$remainingPath] = $ruleSet;
                }
            }
        }
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
                // This property is itself a nested array structure
                $nestedValidationSet = $this->resolveArrayFieldWithNestedRules(
                    $baseField.'.*.'.$property,
                    $rules,
                    $validator
                );
                $objectProperties[$property] = $nestedValidationSet;
            } else {
                // Simple property
                $propertyValidationSet = $this->validationResolver->resolve(
                    $baseField.'.*.'.$property,
                    $rules,
                    $validator
                );
                $objectProperties[$property] = $propertyValidationSet;
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
