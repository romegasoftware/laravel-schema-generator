<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Validation\NestedRules;
use Illuminate\Validation\Validator;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\FieldMetadata;
use RomegaSoftware\LaravelSchemaGenerator\Data\FieldType;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Resolvers\DataValidatorResolver;
use Spatie\LaravelData\Support\DataConfig;

/**
 * Extractor for Spatie Laravel Data classes
 * This class is only loaded when spatie/laravel-data is installed
 */
class DataClassExtractor extends BaseExtractor
{
    public function __construct(
        protected LaravelValidationResolver $validationResolver,
        protected DataValidatorResolver $dataValidatorResolver
    ) {}

    /**
     * Check if this extractor can handle the given class
     */
    public function canHandle(ReflectionClass $class): bool
    {
        // Check if class has ValidationSchema attribute
        if (empty($class->getAttributes(ValidationSchema::class))) {
            return false;
        }

        // Check if Spatie Data is available
        if (! class_exists(\Spatie\LaravelData\Data::class)) {
            return false;
        }

        // Check if class extends Spatie Data
        return $class->isSubclassOf(\Spatie\LaravelData\Data::class);
    }

    /**
     * Extract validation schema information from the Data class
     */
    public function extract(ReflectionClass $class): ExtractedSchemaData
    {
        $schemaName = $this->getSchemaName($class);

        // Build metadata for all fields
        $metadata = $this->buildFieldMetadata($class);

        // Extract properties with metadata
        $properties = $this->recursivelyExtractPropertiesWithMetadata($class, '', $metadata);

        $dependencies = $this->extractDependencies($properties);

        return new ExtractedSchemaData(
            name: $schemaName,
            properties: SchemaPropertyData::collect($properties, DataCollection::class),
            className: $class->getName(),
            type: 'data',
            dependencies: $dependencies,
        );
    }

    /**
     * Get the priority of this extractor
     */
    public function getPriority(): int
    {
        return 20; // Higher priority than RequestClassExtractor
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

        return $className.'Schema';
    }

    /**
     * Extract properties from the Data class constructor
     */
    protected function extractRules(ReflectionClass $class): array
    {
        $dataClass = $class->newInstanceWithoutConstructor();
        $rules = $dataClass->getValidationRules([]);

        // Process InheritValidationFrom attributes to merge inherited rules
        $constructor = $class->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                $inheritAttributes = $parameter->getAttributes(InheritValidationFrom::class);

                if (! empty($inheritAttributes)) {
                    foreach ($inheritAttributes as $inheritAttribute) {
                        $inheritInstance = $inheritAttribute->newInstance();
                        $sourceClass = new ReflectionClass($inheritInstance->class);
                        $sourceProperty = $inheritInstance->property ?? $parameter->getName();

                        // Get rules from the source class for the specific property
                        $sourceDataClass = $sourceClass->newInstanceWithoutConstructor();
                        $sourceRules = $sourceDataClass->getValidationRules([]);

                        // Find the source property rules
                        if (isset($sourceRules[$sourceProperty])) {
                            // Override the current property's rules with inherited ones
                            $currentPropertyName = $parameter->getName();
                            $rules[$currentPropertyName] = $sourceRules[$sourceProperty];
                        }
                    }
                }
            }
        }

        return $rules;
    }

    /**
     * Build field metadata for a Data class
     */
    protected function buildFieldMetadata(ReflectionClass $class, string $prefix = ''): array
    {
        $metadata = [];
        $dataConfig = app(DataConfig::class)->getDataClass($class->getName());

        foreach ($dataConfig->properties as $property) {
            $fieldName = $property->inputMappedName ?? $property->name;
            $fullFieldName = $prefix ? $prefix.'.'.$fieldName : $fieldName;

            // Determine field type
            $fieldType = FieldType::Regular;
            $dataClass = null;

            if ($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataObject) {
                $fieldType = FieldType::DataObject;
                $dataClass = $property->type->dataClass;
            } elseif ($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataCollection) {
                $fieldType = FieldType::DataCollection;
                $dataClass = $property->type->dataClass;
            } elseif ($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataArray) {
                $fieldType = FieldType::DataCollection;
                $dataClass = $property->type->dataClass;
            } elseif ($property->type->type === 'array') {
                $fieldType = FieldType::Array;
            }

            $fieldMeta = new FieldMetadata(
                fieldName: $fullFieldName,
                propertyName: $property->name,
                type: $fieldType,
                dataClass: $dataClass,
                mappedName: $property->inputMappedName,
            );

            // Recursively build metadata for nested Data objects
            if ($fieldType === FieldType::DataObject && $dataClass) {
                $nestedClass = new ReflectionClass($dataClass);
                $nestedMetadata = $this->buildFieldMetadata($nestedClass, $fullFieldName);
                foreach ($nestedMetadata as $child) {
                    $fieldMeta->addChild($child);
                }
            } elseif ($fieldType === FieldType::DataCollection && $dataClass) {
                // For collections, build metadata with .* prefix
                $nestedClass = new ReflectionClass($dataClass);
                $nestedMetadata = $this->buildFieldMetadata($nestedClass, $fullFieldName.'.*');
                foreach ($nestedMetadata as $child) {
                    $fieldMeta->addChild($child);
                }
            }

            // Store metadata by both property name and mapped name (if different)
            $metadata[$property->name] = $fieldMeta;
            if ($property->inputMappedName && $property->inputMappedName !== $property->name) {
                $metadata[$property->inputMappedName] = $fieldMeta;
            }
        }

        return $metadata;
    }

    /**
     * Find field metadata in parent metadata when in array context
     */
    protected function findFieldMetadataInParent(string $field, array $metadata): ?FieldMetadata
    {
        // Search through all metadata entries to find a match
        foreach ($metadata as $meta) {
            if ($meta instanceof FieldMetadata) {
                // Check if this metadata has the field as a child
                $child = $meta->getChild($field);
                if ($child) {
                    return $child;
                }

                // Also check by mapped name if it exists
                if ($meta->mappedName === $field || $meta->propertyName === $field) {
                    return $meta;
                }
            }
        }

        return null;
    }

    /**
     * Flatten metadata to include all nested fields
     */
    protected function flattenMetadata(array $metadata): array
    {
        $flattened = [];

        foreach ($metadata as $key => $meta) {
            if ($meta instanceof FieldMetadata) {
                $flattened[$key] = $meta;
                $flattened[$meta->fieldName] = $meta;

                // Add all children recursively
                $this->addChildrenToFlattened($meta, $flattened);
            }
        }

        return $flattened;
    }

    /**
     * Add children metadata to flattened array recursively
     */
    protected function addChildrenToFlattened(FieldMetadata $parent, array &$flattened): void
    {
        foreach ($parent->children as $child) {
            $flattened[$child->propertyName] = $child;
            $flattened[$child->fieldName] = $child;

            if ($child->mappedName) {
                $flattened[$child->mappedName] = $child;
            }

            // Recursively add children
            $this->addChildrenToFlattened($child, $flattened);
        }
    }

    /**
     * Extract properties using field metadata
     */
    protected function recursivelyExtractPropertiesWithMetadata(ReflectionClass $class, string $prefix, array $metadata): array
    {
        $rules = collect($this->extractRules($class));
        $allRules = [];

        // Process rules with metadata guidance
        foreach ($rules as $field => $rule) {
            $fullField = $prefix ? $prefix.'.'.$field : $field;

            // Check if we have metadata for this field (check both field name and mapped names)
            $fieldMeta = $metadata[$field] ?? null;

            // If not found and we're in an array context, try without the array prefix
            if (! $fieldMeta && str_contains($prefix, '.*')) {
                // Look for field metadata in parent metadata structure
                $fieldMeta = $this->findFieldMetadataInParent($field, $metadata);
            }

            if ($rule instanceof NestedRules) {
                // This is a DataCollection/array
                $parentProperty = substr($field, 0, -2); // Remove .* suffix
                $parentMeta = $metadata[$parentProperty] ?? null;

                if ($parentMeta && $parentMeta->dataClass) {
                    $parentField = $prefix ? $prefix.'.'.$parentProperty : $parentProperty;
                    $nestedPrefix = $parentField.'.*';
                    $reflectedDataChildClass = new ReflectionClass($parentMeta->dataClass);

                    // Recursively extract with child metadata
                    $childMetadata = [];
                    foreach ($parentMeta->children as $child) {
                        // Store by both property name and mapped name
                        $childMetadata[$child->propertyName] = $child;
                        if ($child->mappedName && $child->mappedName !== $child->propertyName) {
                            $childMetadata[$child->mappedName] = $child;
                        }
                    }

                    $nestedRules = $this->recursivelyExtractPropertiesWithMetadata(
                        $reflectedDataChildClass,
                        $nestedPrefix,
                        $childMetadata
                    );

                    $allRules = array_merge($allRules, $nestedRules);
                }
            } elseif ($fieldMeta && $fieldMeta->isNestedDataObject() && ! str_contains($prefix, '.*')) {
                // This is a nested Data object at root level - group its properties
                // Skip grouping for array contexts (let them be processed normally)
                $groupedRules = [];

                // Collect all rules for this object and its properties
                foreach ($rules as $f => $r) {
                    if ($f === $field || str_starts_with($f, $field.'.')) {
                        $groupedRules[$f] = $r;
                    }
                }

                // Add marker to indicate this is a nested object
                $allRules[$fullField.'.__type'] = 'nested_object';
                $allRules[$fullField.'.__class'] = $fieldMeta->dataClass;

                // Add the grouped rules, but skip the base array rule if metadata says it's an object
                foreach ($groupedRules as $f => $r) {
                    $fullF = $prefix ? $prefix.'.'.$f : $f;
                    // If this is the base field and rule contains 'array', override it since metadata says it's an object
                    if ($f === $field && is_array($r) && in_array('array', $r)) {
                        // Replace array rule with a basic validation for the object
                        $baseRules = array_filter($r, fn ($rule) => $rule !== 'array');
                        if (empty($baseRules)) {
                            $baseRules = ['required']; // Default to required if no other rules
                        }
                        $allRules[$fullF] = $this->normalizeRule($baseRules);
                    } else {
                        $allRules[$fullF] = $this->normalizeRule($r);
                    }
                }
            } elseif ($fieldMeta && $fieldMeta->isNestedDataObject() && str_contains($prefix, '.*')) {
                // This is a nested Data object within an array context
                // Process it normally but override the array rule if present
                if (is_array($rule) && in_array('array', $rule)) {
                    // Replace array rule with a basic validation for the object
                    $baseRules = array_filter($rule, fn ($r) => $r !== 'array');
                    if (empty($baseRules)) {
                        $baseRules = ['required']; // Default to required if no other rules
                    }
                    $allRules[$fullField] = $this->normalizeRule($baseRules);
                } else {
                    $allRules[$fullField] = $this->normalizeRule($rule);
                }
            } else {
                // Check if this field is part of a nested object by looking for flattened properties
                $isPartOfNestedObject = false;
                if (str_contains($field, '.')) {
                    $baseField = substr($field, 0, strpos($field, '.'));
                    $baseMeta = $metadata[$baseField] ?? $this->findFieldMetadataInParent($baseField, $metadata);

                    if ($baseMeta && $baseMeta->isNestedDataObject()) {
                        $isPartOfNestedObject = true;

                        // Add marker for the base object if not already added
                        $baseFullField = $prefix ? $prefix.'.'.$baseField : $baseField;
                        if (! isset($allRules[$baseFullField.'.__type'])) {
                            $allRules[$baseFullField.'.__type'] = 'nested_object';
                            $allRules[$baseFullField.'.__class'] = $baseMeta->dataClass;
                        }

                        // Add this flattened property to the nested object
                        $allRules[$fullField] = $this->normalizeRule($rule);
                    }
                }

                if (! $isPartOfNestedObject) {
                    // Regular field
                    $allRules[$fullField] = $this->normalizeRule($rule);
                }
            }
        }

        // If this is the root call, resolve the rules
        if ($prefix === '') {
            $validator = $this->dataValidatorResolver->execute($class->getName(), []);
            $this->mergeInheritedMessages($class, $validator);

            // Build a flattened metadata dictionary for all nested fields
            $flattenedMetadata = $this->flattenMetadata($metadata);

            return $this->resolveRulesFromValidatorWithMetadata($validator, $allRules, $flattenedMetadata);
        }

        return $allRules;
    }

    /**
     * Extract properties from the Data class constructor
     *
     * @param  ReflectionClass  $class  The Data class to extract from
     * @param  string  $prefix  The field prefix for nested properties
     * @return array<string, string>|SchemaPropertyData[] Returns flat array of rules when nested, SchemaPropertyData[] at root
     */
    protected function recursivelyExtractProperties(ReflectionClass $class, string $prefix = ''): array
    {
        $rules = collect($this->extractRules($class));
        $parentProperties = app(DataConfig::class)->getDataClass($class->getName())->properties;
        $allRules = [];

        // Detect nested Data objects that aren't handled as NestedRules
        // These will appear as regular fields with dot notation in the flattened rules
        $nestedDataObjects = [];
        foreach ($parentProperties as $property) {
            if ($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataObject) {
                // Use the mapped name if available, otherwise use the property name
                $fieldName = $property->inputMappedName ?? $property->name;
                $nestedDataObjects[$fieldName] = $property;
            }
        }

        // If we're in an array context (has .* prefix) and have nested Data objects,
        // we need to handle them specially to avoid flattening
        if (str_contains($prefix, '.*') && ! empty($nestedDataObjects)) {
            // Group rules by nested objects first
            $groupedByObject = [];
            $regularRules = [];

            foreach ($rules as $field => $rule) {
                $handled = false;
                foreach ($nestedDataObjects as $nestedFieldName => $nestedProperty) {
                    if ($field === $nestedFieldName || str_starts_with($field, $nestedFieldName.'.')) {
                        $groupedByObject[$nestedFieldName][$field] = $rule;
                        $handled = true;
                        break;
                    }
                }
                if (! $handled) {
                    $regularRules[$field] = $rule;
                }
            }

            // Process regular rules first
            foreach ($regularRules as $field => $rule) {
                $fullField = $prefix ? $prefix.'.'.$field : $field;

                if ($rule instanceof NestedRules) {
                    // This is a DataCollection/array with nested Data class
                    $parentProperty = substr($field, 0, -2); // Remove .* suffix
                    $property = $parentProperties->get($parentProperty);

                    if ($property && $property->type->dataClass) {
                        // Add the array rule for the parent field
                        $parentField = $prefix ? $prefix.'.'.$parentProperty : $parentProperty;

                        // Recursively get rules from the nested Data class
                        $nestedPrefix = $parentField.'.*';
                        $reflectedDataChildClass = new ReflectionClass($property->type->dataClass);
                        $nestedRules = $this->recursivelyExtractProperties($reflectedDataChildClass, $nestedPrefix);

                        // Merge nested rules into our collection
                        $allRules = array_merge($allRules, $nestedRules);
                    }
                } else {
                    $allRules[$fullField] = $this->normalizeRule($rule);
                }
            }

            // Now process grouped nested objects
            foreach ($groupedByObject as $nestedFieldName => $objectRules) {
                $nestedProperty = $nestedDataObjects[$nestedFieldName];
                $fullField = $prefix ? $prefix.'.'.$nestedFieldName : $nestedFieldName;

                // Mark this as a nested object structure
                $allRules[$fullField] = 'object';
                $allRules[$fullField.'.__isNestedObject'] = 'true';

                // Process the nested object's properties
                foreach ($objectRules as $field => $rule) {
                    if ($field === $nestedFieldName) {
                        // This is the base rule for the object itself
                        $allRules[$fullField.'.__baseRules'] = $this->normalizeRule($rule);
                    } else {
                        // This is a property of the nested object
                        $propertyName = substr($field, strlen($nestedFieldName) + 1);
                        $allRules[$fullField.'.__nested.'.$propertyName] = $this->normalizeRule($rule);
                    }
                }
            }
        } else {
            // Original logic for non-array contexts or when no nested objects
            foreach ($rules as $field => $rule) {
                $fullField = $prefix ? $prefix.'.'.$field : $field;

                if ($rule instanceof NestedRules) {
                    // This is a DataCollection/array with nested Data class
                    $parentProperty = substr($field, 0, -2); // Remove .* suffix
                    $property = $parentProperties->get($parentProperty);

                    if ($property && $property->type->dataClass) {
                        // Add the array rule for the parent field
                        $parentField = $prefix ? $prefix.'.'.$parentProperty : $parentProperty;

                        // Recursively get rules from the nested Data class
                        $nestedPrefix = $parentField.'.*';
                        $reflectedDataChildClass = new ReflectionClass($property->type->dataClass);
                        $nestedRules = $this->recursivelyExtractProperties($reflectedDataChildClass, $nestedPrefix);

                        // Merge nested rules into our collection
                        $allRules = array_merge($allRules, $nestedRules);
                    }
                } else {
                    // Check if this field is part of a nested Data object (only at root level)
                    $isNestedObjectField = false;
                    if ($prefix === '') {
                        foreach ($nestedDataObjects as $nestedFieldName => $nestedProperty) {
                            if (str_starts_with($field, $nestedFieldName.'.')) {
                                $isNestedObjectField = true;
                                break;
                            }
                        }
                    }

                    // Always add the rule
                    $allRules[$fullField] = $this->normalizeRule($rule);
                }
            }
        }

        // If this is the root call (no prefix), resolve the rules using validator
        if ($prefix === '') {
            $validator = $this->dataValidatorResolver->execute($class->getName(), []);

            // Merge inherited validation messages
            $this->mergeInheritedMessages($class, $validator);

            return $this->resolveRulesFromValidatorWithNestedObjects($validator, $allRules, $nestedDataObjects);
        }

        // For nested calls, return the raw rules array
        return $allRules;
    }

    /**
     * Merge inherited validation messages from InheritValidationFrom attributes
     */
    protected function mergeInheritedMessages(ReflectionClass $class, \Illuminate\Validation\Validator $validator): void
    {
        $constructor = $class->getConstructor();
        if (! $constructor) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $inheritAttributes = $parameter->getAttributes(InheritValidationFrom::class);

            if (empty($inheritAttributes)) {
                continue;
            }

            foreach ($inheritAttributes as $inheritAttribute) {
                $inheritInstance = $inheritAttribute->newInstance();
                $sourceClass = $inheritInstance->class;
                $sourceProperty = $inheritInstance->property ?? $parameter->getName();

                // Get messages from the source class
                if (method_exists($sourceClass, 'messages')) {
                    $sourceMessages = $sourceClass::messages();

                    // Map the source property messages to the current property
                    $currentPropertyName = $parameter->getName();
                    foreach ($sourceMessages as $key => $message) {
                        // Check if this message is for the source property
                        if (str_starts_with($key, $sourceProperty.'.')) {
                            // Replace the source property name with the current property name
                            $ruleType = substr($key, strlen($sourceProperty) + 1);
                            $newKey = $currentPropertyName.'.'.$ruleType;

                            // Only add if not already defined in current class
                            if (! isset($validator->customMessages[$newKey])) {
                                $validator->customMessages[$newKey] = $message;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Resolve rules from validator using field metadata
     */
    protected function resolveRulesFromValidatorWithMetadata($validator, array $rules, array $metadata): array
    {
        // Group rules considering metadata
        $groupedRules = $this->groupRulesByBaseFieldWithMetadata($rules, $metadata);

        $properties = [];
        foreach ($groupedRules as $baseField => $fieldRules) {
            if (isset($fieldRules['nested'])) {
                if (isset($fieldRules['isNestedObject']) && $fieldRules['isNestedObject']) {
                    // This is a nested Data object - resolve as an object
                    $resolvedValidationSet = $this->resolveNestedObjectRules(
                        $baseField,
                        $fieldRules,
                        $validator
                    );
                } else {
                    // This is an array field with nested rules
                    $resolvedValidationSet = $this->resolveArrayFieldWithNestedRules(
                        $baseField,
                        $fieldRules,
                        $validator
                    );
                }
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
     * Group rules by base field using metadata to identify nested objects
     */
    protected function groupRulesByBaseFieldWithMetadata(array $rules, array $metadata): array
    {
        $grouped = [];
        $nestedObjectFields = [];

        // First pass: identify nested objects from metadata markers AND metadata
        foreach ($rules as $field => $ruleSet) {
            if (str_ends_with($field, '.__type') && $ruleSet === 'nested_object') {
                $baseField = substr($field, 0, -7); // Remove .__type (7 characters)
                $nestedObjectFields[$baseField] = true;
                // Found nested object marker
            }
        }

        // Also check metadata for nested objects (for array contexts where we don't use markers)
        // But only add them if they're NOT within array contexts - array nested objects
        // should be handled as part of the parent array's grouping
        foreach ($metadata as $key => $meta) {
            if ($meta instanceof FieldMetadata && $meta->isNestedDataObject()) {
                // Only add as nested object field if it's not within an array context
                if (! str_contains($meta->fieldName, '.*')) {
                    $nestedObjectFields[$meta->fieldName] = true;
                    if ($meta->mappedName) {
                        // Also check if the rule exists for the mapped name
                        $mappedFieldName = str_replace($meta->propertyName, $meta->mappedName, $meta->fieldName);
                        if (isset($rules[$mappedFieldName])) {
                            $nestedObjectFields[$mappedFieldName] = true;
                        }
                    }
                }
            }
        }

        // Nested object fields identified

        // Second pass: group rules
        foreach ($rules as $field => $ruleSet) {
            // Skip metadata markers
            if (str_contains($field, '.__type') || str_contains($field, '.__class')) {
                continue;
            }

            $handled = false;

            // Check if this is part of a nested object
            // BUT: Skip nested objects within arrays - they should be handled by addNestedRule
            foreach ($nestedObjectFields as $objectField => $v) {
                // Skip nested objects that are within arrays - these will be handled by addNestedRule
                if (str_contains($objectField, '.*')) {
                    continue;
                }

                if (str_starts_with($field, $objectField.'.')) {
                    // Processing nested property
                    // Initialize the nested object structure if needed
                    if (! isset($grouped[$objectField])) {
                        $grouped[$objectField] = [
                            'rules' => null,
                            'nested' => [],
                            'isNestedObject' => true,
                        ];
                    }

                    // Extract property name
                    $propertyName = substr($field, strlen($objectField) + 1);

                    // Handle nested arrays within the object
                    if (str_contains($propertyName, '.*')) {
                        $this->addNestedRuleRecursively($grouped[$objectField]['nested'], $propertyName, $ruleSet);
                    } else {
                        $grouped[$objectField]['nested'][$propertyName] = $ruleSet;
                    }

                    $handled = true;
                    break;
                } elseif ($field === $objectField) {
                    // Processing base object field
                    // Base rules for the nested object
                    if (! isset($grouped[$objectField])) {
                        $grouped[$objectField] = [
                            'rules' => $ruleSet,
                            'nested' => [],
                            'isNestedObject' => true,
                        ];
                    } else {
                        $grouped[$objectField]['rules'] = $ruleSet;
                    }
                    $handled = true;
                    break;
                }
            }

            if (! $handled) {
                // Use standard grouping for non-nested-object fields
                if (str_contains($field, '.*')) {
                    // Check if this field is a nested object within an array using metadata
                    $isNestedObjectInArray = false;
                    foreach ($metadata as $meta) {
                        if ($meta instanceof FieldMetadata &&
                            $meta->isNestedDataObject() &&
                            str_contains($meta->fieldName, '.*') &&
                            ($meta->fieldName === $field ||
                             ($meta->mappedName && str_replace($meta->propertyName, $meta->mappedName, $meta->fieldName) === $field))) {
                            $isNestedObjectInArray = true;
                            // Marking field as nestedObjectInArray from metadata
                            break;
                        }
                    }

                    // Calling addNestedRule
                    $this->addNestedRule($grouped, $field, $ruleSet, $isNestedObjectInArray, $metadata);
                } else {
                    // Adding regular field
                    if (! isset($grouped[$field])) {
                        $grouped[$field] = [
                            'rules' => $ruleSet,
                            'nested' => [],
                        ];
                    } else {
                        $grouped[$field]['rules'] = $ruleSet;
                    }
                }
            }
        }

        // Clean up empty nested arrays
        $this->cleanupGroupedRules($grouped);

        return $grouped;
    }

    /**
     * Override addNestedRule to handle nested objects within arrays
     */
    public function addNestedRule(array &$grouped, string $field, string $ruleSet, bool $isNestedObjectInArray = false, array $metadata = []): void
    {
        // Processing nested rule

        // Split on the first .* occurrence only
        $parts = explode('.*', $field, 2);
        $baseField = $parts[0];
        $remainingPath = $parts[1] ?? '';

        // Base field and remaining path identified

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
            // After trimming remaining path

            if (str_contains($remainingPath, '.*')) {
                // Still has wildcards - need to handle nested arrays recursively
                $this->addNestedRuleRecursively($grouped[$baseField]['nested'], $remainingPath, $ruleSet);
            } else {
                // No more wildcards - this is a simple nested property
                // Check if this field is part of a nested object (like song_meta_data_custom_name.lengthInSeconds)
                if (str_contains($remainingPath, '.')) {
                    $pathParts = explode('.', $remainingPath, 2);
                    $potentialNestedObject = $pathParts[0];
                    $nestedProperty = $pathParts[1];

                    // Checking if field is nested object

                    // Check if the potential nested object is actually a nested object from metadata
                    $isNestedObjectProperty = false;
                    foreach ($metadata as $meta) {
                        if ($meta instanceof FieldMetadata &&
                            $meta->isNestedDataObject() &&
                            str_contains($meta->fieldName, '.*') &&
                            (str_ends_with($meta->fieldName, $potentialNestedObject) ||
                             ($meta->mappedName && $meta->mappedName === $potentialNestedObject))) {
                            $isNestedObjectProperty = true;
                            // Found metadata match
                            break;
                        }
                    }

                    if ($isNestedObjectProperty) {
                        // Treating as nested object

                        // Initialize the nested object structure
                        if (! isset($grouped[$baseField]['nested'][$potentialNestedObject])) {
                            $grouped[$baseField]['nested'][$potentialNestedObject] = [
                                'rules' => null,
                                'nested' => [],
                                'isNestedObject' => true,
                            ];
                        }
                        // Add the property to the nested object
                        $grouped[$baseField]['nested'][$potentialNestedObject]['nested'][$nestedProperty] = $ruleSet;

                        return;
                    }
                }

                // Check if this should be marked as a nested object
                if ($isNestedObjectInArray) {
                    // Marking as nested object
                    $grouped[$baseField]['nested'][$remainingPath] = [
                        'rules' => $ruleSet,
                        'nested' => [],
                        'isNestedObject' => true,
                    ];
                } else {
                    // Adding as regular property
                    if (! isset($grouped[$baseField]['nested'][$remainingPath])) {
                        $grouped[$baseField]['nested'][$remainingPath] = $ruleSet;
                    } elseif (is_string($grouped[$baseField]['nested'][$remainingPath])) {
                        // Convert to structured format if it was just a string
                        $grouped[$baseField]['nested'][$remainingPath] = [
                            'rules' => $grouped[$baseField]['nested'][$remainingPath],
                            'nested' => [],
                        ];
                    }
                }
            }
        }
    }

    /**
     * Resolve rules from validator with special handling for nested Data objects
     *
     * @param  Validator  $validator
     * @param  array  $nestedDataObjects  Array of nested Data object properties keyed by mapped field name
     * @return SchemaPropertyData[]
     */
    protected function resolveRulesFromValidatorWithNestedObjects($validator, array $rules, array $nestedDataObjects): array
    {
        // First, normalize all rules to string format
        $normalizedRules = [];
        foreach ($rules as $field => $rule) {
            // Skip special marker fields
            if (str_contains($field, '.__isNestedObject') ||
                str_contains($field, '.__baseRules') ||
                str_contains($field, '.__nested.')) {
                continue;
            }
            $normalizedRules[$field] = $this->normalizeRule($rule);
        }

        // Group rules by base field for nested array handling
        // Special handling for rules that come from array contexts with nested objects
        $groupedRules = $this->groupRulesByBaseFieldWithNestedObjectHandling($normalizedRules);

        // Now handle nested Data objects that were flattened (only at root level)
        foreach ($nestedDataObjects as $fieldName => $property) {
            // Find all rules that belong to this nested object
            $nestedObjectRules = [];
            $keysToRemove = [];

            foreach ($groupedRules as $field => $fieldRules) {
                if (str_starts_with($field, $fieldName.'.')) {
                    // This is a property of the nested object
                    $nestedPropertyName = substr($field, strlen($fieldName) + 1);
                    $nestedObjectRules[$nestedPropertyName] = $fieldRules;
                    $keysToRemove[] = $field;
                }
            }

            // Remove the individual nested properties from grouped rules
            foreach ($keysToRemove as $key) {
                unset($groupedRules[$key]);
            }

            // Add the nested object as a single property with nested structure
            if (! empty($nestedObjectRules)) {
                // Mark that this is a nested object, not an array
                $groupedRules[$fieldName]['isNestedObject'] = true;
                $groupedRules[$fieldName]['nested'] = [];
                foreach ($nestedObjectRules as $nestedProp => $nestedRule) {
                    $groupedRules[$fieldName]['nested'][$nestedProp] = $nestedRule['rules'] ?? '';
                }
            }
        }

        $properties = [];

        foreach ($groupedRules as $baseField => $fieldRules) {
            if (isset($fieldRules['nested'])) {
                if (isset($fieldRules['isNestedObject']) && $fieldRules['isNestedObject']) {
                    // This is a nested Data object - resolve as an object, not an array
                    $resolvedValidationSet = $this->resolveNestedObjectRules(
                        $baseField,
                        $fieldRules,
                        $validator
                    );
                } else {
                    // This is an array field with nested rules
                    $resolvedValidationSet = $this->resolveArrayFieldWithNestedRules(
                        $baseField,
                        $fieldRules,
                        $validator
                    );
                }
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
     * Resolve nested object rules (not an array of objects, just a single nested object)
     */
    protected function resolveNestedObjectRules(string $baseField, array $fieldRules, $validator): \RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet
    {
        // Resolve base object rules if they exist
        $baseRules = $fieldRules['rules'] ?? '';
        $baseValidationSet = $this->validationResolver->resolve($baseField, $baseRules, $validator);

        // Create nested validation structure for the object properties
        $objectProperties = [];
        if (! empty($fieldRules['nested'])) {
            foreach ($fieldRules['nested'] as $property => $rules) {
                $propertyValidationSet = $this->validationResolver->resolve(
                    $baseField.'.'.$property,
                    $rules,
                    $validator
                );
                $objectProperties[$property] = $propertyValidationSet;
            }
        }

        // Create a validation set that represents an object with nested properties
        return \RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet::make(
            fieldName: $baseField,
            validations: $baseValidationSet->validations->all(),
            inferredType: 'object', // Mark as object, not array
            nestedValidations: null,
            objectProperties: $objectProperties
        );
    }

    /**
     * Extract dependencies from properties
     *
     * @param  SchemaPropertyData[]  $properties
     */
    protected function extractDependencies(array $properties): array
    {
        $dependencies = [];

        foreach ($properties as $property) {
            $type = $property->validations?->inferredType ?? 'string';

            if (str_starts_with($type, 'DataCollection:')) {
                $dataClass = substr($type, 15);
                if (! in_array($dataClass, $dependencies)) {
                    $dependencies[] = $dataClass;
                }
            } elseif (str_ends_with($type, 'Data')) {
                if (! in_array($type, $dependencies)) {
                    $dependencies[] = $type;
                }
            }
        }

        return $dependencies;
    }
}
