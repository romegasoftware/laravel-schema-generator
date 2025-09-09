<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use Spatie\LaravelData\DataCollection;

class RequestClassExtractor extends BaseExtractor
{
    public function __construct(
        protected LaravelValidationResolver $validationResolver
    ) {
    }

    /**
     * Check if this extractor can handle the given class
     */
    public function canHandle(ReflectionClass $class): bool
    {
        // Check if class has ValidationSchema attribute
        if (empty($class->getAttributes(ValidationSchema::class))) {
            return false;
        }

        // Check if it's a FormRequest or has a rules method
        if ($class->isSubclassOf(FormRequest::class)) {
            return true;
        }

        // Check for any class with a rules() method
        return $class->hasMethod('rules');
    }

    /**
     * Extract validation schema information from the class
     */
    public function extract(ReflectionClass $class): ExtractedSchemaData
    {
        $schemaName = $this->getSchemaName($class);
        $properties = $this->transformRulesToProperties($class);

        return new ExtractedSchemaData(
            name: $schemaName,
            properties: SchemaPropertyData::collect($properties, DataCollection::class),
            className: $class->getName(),
            type: 'request',
        );
    }

    /**
     * Get the priority of this extractor
     */
    public function getPriority(): int
    {
        return 10; // Lower priority than DataClassExtractor
    }

    /**
     * Get the schema name from the attribute or generate one
     */
    protected function getSchemaName(ReflectionClass $class): string
    {
        $attributes = $class->getAttributes(ValidationSchema::class);

        if (! empty($attributes)) {
            $zodAttribute = $attributes[0]->newInstance();
            if ($zodAttribute->name) {
                return $zodAttribute->name;
            }
        }

        // Generate default name
        $className = $class->getShortName();

        if (str_ends_with($className, 'Request')) {
            return substr($className, 0, -7).'Schema';
        }

        return $className.'Schema';
    }

    /**
     * Transform Laravel validation rules to properties array
     *
     * @return SchemaPropertyData[]
     */
    protected function transformRulesToProperties(ReflectionClass $class): array
    {
        $instance = $class->newInstance();
        $instance->setContainer(app());
        $validationRules = $class->getMethod('validationRules');
        $validatorInstance = $class->getMethod('getValidatorInstance');

        // Check if method is static
        if ($validatorInstance->isStatic()) {
            $validator = $validatorInstance->invoke(null);
        } else {
            $validator = $validatorInstance->invoke($instance);
        }

        if ($validationRules->isStatic()) {
            $rules = $validationRules->invoke(null);
        } else {
            $rules = $validationRules->invoke($instance);
        }

        // Group rules by base field for nested array handling
        $groupedRules = $this->groupRulesByBaseField($rules);
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
    protected function groupRulesByBaseField(array $rules): array
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
    protected function addNestedRule(array &$grouped, string $field, string $ruleSet): void
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
    protected function addNestedRuleRecursively(array &$nested, string $path, string $ruleSet): void
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
    protected function cleanupGroupedRules(array &$grouped): void
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
    protected function cleanupNestedRules(array &$nested): void
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
    protected function resolveArrayFieldWithNestedRules(string $baseField, array $fieldRules, $validator): ResolvedValidationSet
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
    protected function createNestedObjectValidation(string $baseField, array $nestedRules, $validator): ResolvedValidationSet
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
