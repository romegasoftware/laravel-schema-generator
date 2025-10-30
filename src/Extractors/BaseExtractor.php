<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\Validator;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\ExtractorInterface;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\SchemaAnnotatedRule;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaFragment;
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
        $schemaOverrides = $this->extractSchemaOverrides($rules);

        // First, normalize all rules to string format
        $normalizedRules = [];
        foreach ($rules as $field => $rule) {
            $normalizedRules[$field] = $this->ruleFactory->normalizeRule($rule);
        }

        // Group rules by base field for nested array handling
        $groupedRules = $this->ruleGrouper->groupRulesByBaseField($normalizedRules);
        $properties = [];

        foreach ($groupedRules as $baseField => $fieldRules) {
            if (($fieldRules['isNestedObject'] ?? false) === true) {
                // Treat structured fields without wildcards as objects instead of arrays
                $resolvedValidationSet = $this->nestedValidationBuilder->buildNestedObjectValidation(
                    $baseField,
                    $fieldRules,
                    $validator
                );
            } elseif (isset($fieldRules['nested'])) {
                // This is an array field with nested rules
                $resolvedValidationSet = $this->resolveArrayFieldWithNestedRules(
                    $baseField,
                    $fieldRules,
                    $validator
                );
            } else {
                // Regular field without nesting
                $resolvedValidationSet = $this->validationResolver->resolve(
                    $baseField,
                    $fieldRules['rules'] ?? '',
                    $validator
                );
            }

            $properties[] = new SchemaPropertyData(
                name: $baseField,
                validator: $validator,
                isOptional: ! $resolvedValidationSet->isFieldRequired(),
                validations: $resolvedValidationSet,
                schemaOverride: $this->resolveSchemaOverrideForField($baseField, $schemaOverrides),
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
     * Extract schema overrides defined directly on validation rules.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, list<SchemaFragment>>
     */
    protected function extractSchemaOverrides(array $rules): array
    {
        $overrides = [];

        foreach ($rules as $field => $rule) {
            $fragment = $this->findSchemaFragment($rule);

            if ($fragment !== null) {
                $overrides[$field][] = $fragment;
            }
        }

        return $overrides;
    }

    protected function findSchemaFragment(mixed $rule): ?SchemaFragment
    {
        if ($rule instanceof SchemaAnnotatedRule) {
            return $rule->schemaFragment();
        }

        if (is_array($rule) || $rule instanceof \Traversable) {
            foreach ($rule as $nestedRule) {
                $fragment = $this->findSchemaFragment($nestedRule);

                if ($fragment !== null) {
                    return $fragment;
                }
            }
        }

        return null;
    }

    protected function resolveSchemaOverrideForField(string $field, array $schemaOverrides): ?SchemaFragment
    {
        $fragments = $schemaOverrides[$field] ?? $schemaOverrides[$field.'.*'] ?? null;

        if ($fragments === null) {
            return null;
        }

        $normalized = is_array($fragments)
            ? array_values(array_filter($fragments, static fn ($fragment) => $fragment instanceof SchemaFragment))
            : ($fragments instanceof SchemaFragment ? [$fragments] : []);

        if ($normalized === []) {
            return null;
        }

        return $this->combineSchemaFragments($normalized);
    }

    /**
     * @param  list<SchemaFragment>  $fragments
     */
    protected function combineSchemaFragments(array $fragments): ?SchemaFragment
    {
        $base = null;
        $suffix = '';

        foreach ($fragments as $fragment) {
            if ($fragment->replaces()) {
                $base = $fragment->code();
            } elseif ($fragment->appends()) {
                $suffix .= $fragment->code();
            } else {
                $base ??= $fragment->code();
            }
        }

        if ($base !== null) {
            return SchemaFragment::literal($base.$suffix);
        }

        if ($suffix !== '') {
            return SchemaFragment::literal($suffix);
        }

        return null;
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
     * @param  array<string, SchemaFragment|list<SchemaFragment>>  $base
     * @param  array<string, SchemaFragment|list<SchemaFragment>>  $additional
     * @return array<string, list<SchemaFragment>>
     */
    protected function mergeSchemaOverrideBuckets(array $base, array $additional): array
    {
        foreach ($additional as $field => $fragments) {
            $existing = $base[$field] ?? [];
            $existingList = is_array($existing) ? $existing : [$existing];
            /** @var array<int, SchemaFragment|mixed> $existingList */
            $existingFragments = [];
            foreach ($existingList as $fragment) {
                if ($fragment instanceof SchemaFragment) {
                    $existingFragments[] = $fragment;
                }
            }

            $incomingList = is_array($fragments) ? $fragments : [$fragments];
            /** @var array<int, SchemaFragment|mixed> $incomingList */
            $currentFragments = [];
            foreach ($incomingList as $fragment) {
                if ($fragment instanceof SchemaFragment) {
                    $currentFragments[] = $fragment;
                }
            }

            if ($currentFragments === []) {
                $base[$field] = $existingFragments;

                continue;
            }

            $base[$field] = array_merge($existingFragments, $currentFragments);
        }

        return $base;
    }

    /**
     * Create validation properties from grouped rules
     */
    protected function createPropertiesFromGroupedRules(
        array $groupedRules,
        Validator $validator,
        array $metadata = [],
        array $schemaOverrides = []
    ): array {
        $properties = [];

        foreach ($groupedRules as $baseField => $fieldRules) {
            $resolvedValidationSet = $this->resolveFieldValidation($baseField, $fieldRules, $validator, $metadata);

            $properties[] = new SchemaPropertyData(
                name: $baseField,
                validator: $validator,
                isOptional: ! $resolvedValidationSet->isFieldRequired(),
                validations: $resolvedValidationSet,
                schemaOverride: $this->resolveSchemaOverrideForField($baseField, $schemaOverrides),
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

        if (isset($fieldRules['isNestedObject']) && $fieldRules['isNestedObject'] === true) {
            return $this->nestedValidationBuilder->buildNestedObjectValidation($baseField, $fieldRules, $validator);
        }

        // Handle nested rules
        if (isset($fieldRules['nested'])) {
            return $this->resolveArrayFieldWithNestedRules($baseField, $fieldRules, $validator);
        }

        // Regular field without nesting
        return $this->validationResolver->resolve($baseField, $fieldRules['rules'] ?? '', $validator);
    }
}
