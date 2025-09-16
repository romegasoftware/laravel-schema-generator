<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\FieldMetadata;
use Illuminate\Validation\Validator;

/**
 * Service for building nested validation structures
 * 
 * Handles the construction of nested validation hierarchies for complex
 * data structures including arrays, objects, and nested Data classes.
 */
class NestedValidationBuilder
{
    protected LaravelValidationResolver $validationResolver;

    public function __construct(LaravelValidationResolver $validationResolver)
    {
        $this->validationResolver = $validationResolver;
    }

    /**
     * Build nested validation structure from metadata and rules
     */
    public function buildFromMetadata(
        string $baseField,
        array $fieldRules,
        array $metadata,
        Validator $validator
    ): ResolvedValidationSet {
        // Check if this is a nested object based on metadata
        $fieldMeta = $metadata[$baseField] ?? null;
        
        if ($fieldMeta instanceof FieldMetadata && $fieldMeta->isNestedDataObject()) {
            return $this->buildNestedObjectValidation($baseField, $fieldRules, $validator);
        }

        // Check if this is an array with nested rules
        if (isset($fieldRules['nested'])) {
            return $this->buildArrayValidation($baseField, $fieldRules, $validator);
        }

        // Regular field without nesting
        return $this->validationResolver->resolve(
            $baseField, 
            $fieldRules['rules'] ?? '', 
            $validator
        );
    }

    /**
     * Build validation for nested object (not array)
     */
    public function buildNestedObjectValidation(
        string $baseField,
        array $fieldRules,
        Validator $validator
    ): ResolvedValidationSet {
        // Resolve base object rules if they exist
        $baseRules = $fieldRules['rules'] ?? '';
        $baseValidationSet = $this->validationResolver->resolve($baseField, $baseRules, $validator);

        // Create nested validation structure for the object properties
        $objectProperties = $this->buildObjectProperties($baseField, $fieldRules, $validator);

        return ResolvedValidationSet::make(
            fieldName: $baseField,
            validations: $baseValidationSet->validations->all(),
            inferredType: 'object',
            nestedValidations: null,
            objectProperties: $objectProperties
        );
    }

    /**
     * Build validation for array fields
     */
    public function buildArrayValidation(
        string $baseField,
        array $fieldRules,
        Validator $validator
    ): ResolvedValidationSet {
        // Resolve base array rules
        $baseRules = $fieldRules['rules'] ?? 'array';
        $baseValidationSet = $this->validationResolver->resolve($baseField, $baseRules, $validator);

        // Create nested validation structure
        $nestedValidations = $this->buildNestedValidations($baseField, $fieldRules, $validator);

        return ResolvedValidationSet::make(
            fieldName: $baseField,
            validations: $baseValidationSet->validations->all(),
            inferredType: 'array',
            nestedValidations: $nestedValidations
        );
    }

    /**
     * Build nested validations for array items
     */
    protected function buildNestedValidations(
        string $baseField,
        array $fieldRules,
        Validator $validator
    ): ?ResolvedValidationSet {
        if (empty($fieldRules['nested'])) {
            return null;
        }

        // Direct array items (e.g., tags.*)
        if (isset($fieldRules['nested']['*'])) {
            return $this->validationResolver->resolve(
                $baseField.'.*',
                $fieldRules['nested']['*'],
                $validator
            );
        }

        // Nested object properties (e.g., categories.*.title)
        return $this->buildNestedObjectStructure($baseField, $fieldRules['nested'], $validator);
    }

    /**
     * Build nested object structure for array items
     */
    public function buildNestedObjectStructure(
        string $baseField,
        array $nestedRules,
        Validator $validator
    ): ResolvedValidationSet {
        $objectProperties = [];

        foreach ($nestedRules as $property => $rules) {
            $objectProperties[$property] = $this->processNestedProperty(
                $baseField,
                $property,
                $rules,
                $validator
            );
        }

        return ResolvedValidationSet::make(
            fieldName: $baseField.'.*[object]',
            validations: [],
            inferredType: 'object',
            nestedValidations: null,
            objectProperties: $objectProperties
        );
    }

    /**
     * Process a single nested property
     */
    protected function processNestedProperty(
        string $baseField,
        string $property,
        $rules,
        Validator $validator
    ): ResolvedValidationSet {
        // Clean property key (remove .* suffixes)
        $cleanPropertyKey = str_replace('.*', '', $property);

        if (is_array($rules) && isset($rules['nested'])) {
            if (isset($rules['isNestedObject']) && $rules['isNestedObject']) {
                // Nested object within array item
                $nestedObjectValidation = $this->buildNestedObjectStructure(
                    $baseField.'.*.'.$property,
                    $rules['nested'],
                    $validator
                );

                return ResolvedValidationSet::make(
                    fieldName: $baseField.'.*.'.$property,
                    validations: $nestedObjectValidation->validations->all(),
                    inferredType: 'object',
                    nestedValidations: null,
                    objectProperties: $nestedObjectValidation->objectProperties
                );
            } else {
                // Nested array structure
                return $this->buildArrayValidation(
                    $baseField.'.*.'.$property,
                    $rules,
                    $validator
                );
            }
        }

        // Simple property
        $rulesString = is_array($rules) ? ($rules['rules'] ?? '') : $rules;
        return $this->validationResolver->resolve(
            $baseField.'.*.'.$property,
            $rulesString,
            $validator
        );
    }

    /**
     * Build object properties for nested objects
     */
    protected function buildObjectProperties(
        string $baseField,
        array $fieldRules,
        Validator $validator
    ): array {
        $objectProperties = [];
        
        if (!empty($fieldRules['nested'])) {
            foreach ($fieldRules['nested'] as $property => $rules) {
                // Ensure rules is a string
                $rulesString = is_array($rules) ? ($rules['rules'] ?? '') : $rules;
                
                $propertyValidationSet = $this->validationResolver->resolve(
                    $baseField.'.'.$property,
                    $rulesString,
                    $validator
                );
                $objectProperties[$property] = $propertyValidationSet;
            }
        }

        return $objectProperties;
    }

    /**
     * Check if field should be treated as nested object
     */
    public function isNestedObject(array $fieldRules, array $metadata, string $field): bool
    {
        // Check explicit marker
        if (isset($fieldRules['isNestedObject']) && $fieldRules['isNestedObject']) {
            return true;
        }

        // Check metadata
        $fieldMeta = $metadata[$field] ?? null;
        if ($fieldMeta instanceof FieldMetadata && $fieldMeta->isNestedDataObject()) {
            return true;
        }

        return false;
    }
}