<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Validation\NestedRules;
use Illuminate\Validation\Validator;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\FieldMetadataFactory;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use RomegaSoftware\LaravelSchemaGenerator\Services\MessageResolutionService;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaNameGenerator;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Resolvers\DataValidatorResolver;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\Validation\DataRules;
use Spatie\LaravelData\Support\Validation\PropertyRules;
use Spatie\LaravelData\Support\Validation\RuleDenormalizer;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Support\Validation\ValidationPath;

/**
 * Extractor for Spatie Laravel Data classes
 * This class is only loaded when spatie/laravel-data is installed
 */
class DataClassExtractor extends BaseExtractor
{
    public function __construct(
        protected LaravelValidationResolver $validationResolver,
        protected DataValidatorResolver $dataValidatorResolver,
        protected MessageResolutionService $messageService = new MessageResolutionService,
        protected FieldMetadataFactory $metadataFactory = new FieldMetadataFactory
    ) {
        parent::__construct($validationResolver);
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
        $schemaName = SchemaNameGenerator::fromClass($class);

        // Build metadata for all fields
        $metadata = $this->metadataFactory->buildFieldMetadata($class);

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
     * Extract properties from the Data class constructor
     */
    protected function extractRules(ReflectionClass $class): array
    {
        // Directly extract validation rules from the DataClass properties,
        // bypassing the shouldSkipPropertyValidation check that excludes properties with default values

        $dataConfig = app(DataConfig::class);
        $dataClass = $dataConfig->getDataClass($class->getName());
        $dataRules = DataRules::create();
        $fullPayload = []; // Empty payload for context
        $path = ValidationPath::create();
        $ruleDenormalizer = app(RuleDenormalizer::class);

        // Process each property to extract validation rules
        foreach ($dataClass->properties as $dataProperty) {
            $propertyPath = $path->property($dataProperty->inputMappedName ?? $dataProperty->name);

            // Skip if validation is explicitly disabled for this property
            if ($dataProperty->validate === false) {
                continue;
            }

            // For Data objects and collections, we need to recursively extract their rules
            if ($dataProperty->type->kind->isDataObject()) {
                // Add basic object validation rules
                $propertyRules = PropertyRules::create();
                $context = new ValidationContext(
                    $fullPayload,
                    $fullPayload,
                    $path
                );

                // Apply rule inferrers for the data object property itself
                foreach ($dataConfig->ruleInferrers as $inferrer) {
                    $inferrer->handle($dataProperty, $propertyRules, $context);
                }

                // Add the rules for this property
                $rules = $ruleDenormalizer->execute(
                    $propertyRules->all(),
                    $propertyPath
                );
                $dataRules->add($propertyPath, $rules);

                // Recursively extract rules for the nested data object
                $nestedClass = new ReflectionClass($dataProperty->type->dataClass);
                $nestedRules = $this->extractRules($nestedClass);

                // Add nested rules with proper path
                foreach ($nestedRules as $nestedKey => $nestedRule) {
                    $fullPath = $propertyPath->property($nestedKey);
                    $dataRules->add($fullPath, $nestedRule);
                }

                continue;
            }

            if ($dataProperty->type->kind->isDataCollectable()) {
                // Add array validation rules
                $propertyRules = PropertyRules::create();
                $propertyRules->add(new \Spatie\LaravelData\Attributes\Validation\Present);
                $propertyRules->add(new \Spatie\LaravelData\Attributes\Validation\ArrayType);

                $context = new ValidationContext(
                    $fullPayload,
                    $fullPayload,
                    $path
                );

                // Apply rule inferrers
                foreach ($dataConfig->ruleInferrers as $inferrer) {
                    $inferrer->handle($dataProperty, $propertyRules, $context);
                }

                // Add the rules for this property
                $rules = $ruleDenormalizer->execute(
                    $propertyRules->all(),
                    $propertyPath
                );
                $dataRules->add($propertyPath, $rules);

                // If it's a collection of Data objects, add nested validation
                if ($dataProperty->type->dataClass) {
                    $nestedClass = new ReflectionClass($dataProperty->type->dataClass);
                    $nestedRules = $this->extractRules($nestedClass);

                    // Add nested rules for array items
                    foreach ($nestedRules as $nestedKey => $nestedRule) {
                        $fullPath = ValidationPath::create($propertyPath->get().'.*.'.$nestedKey);
                        $dataRules->add($fullPath, $nestedRule);
                    }
                }

                continue;
            }

            // Build rules for this property using the rule inferrers
            $propertyRules = PropertyRules::create();
            $context = new ValidationContext(
                $fullPayload,
                $fullPayload,
                $path
            );

            // Apply all rule inferrers to build the complete rule set
            foreach ($dataConfig->ruleInferrers as $inferrer) {
                $inferrer->handle($dataProperty, $propertyRules, $context);
            }

            // Denormalize the rules to the format expected by Laravel validator
            $rules = $ruleDenormalizer->execute(
                $propertyRules->all(),
                $propertyPath
            );

            $dataRules->add($propertyPath, $rules);
        }

        // Handle custom rules() method if it exists
        if (method_exists($class->getName(), 'rules')) {
            $validationContext = new ValidationContext(
                $fullPayload,
                $fullPayload,
                $path
            );

            $overwrittenRules = app()->call([$class->getName(), 'rules'], ['context' => $validationContext]);
            $shouldMergeRules = $dataClass->attributes->has(\Spatie\LaravelData\Attributes\MergeValidationRules::class);

            foreach ($overwrittenRules as $key => $rules) {
                $rules = collect(\Illuminate\Support\Arr::wrap($rules))
                    ->map(fn (mixed $rule) => $ruleDenormalizer->execute($rule, $path))
                    ->flatten()
                    ->all();

                $shouldMergeRules
                    ? $dataRules->merge($path->property($key), $rules)
                    : $dataRules->add($path->property($key), $rules);
            }
        }

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

                        // Recursively extract rules from the source class
                        $sourceRules = $this->extractRules($sourceClass);

                        // Find the source property rules
                        if (isset($sourceRules[$sourceProperty])) {
                            // Override the current property's rules with inherited ones
                            $currentPropertyName = $parameter->getName();
                            $dataRules->add(
                                $path->property($currentPropertyName),
                                $sourceRules[$sourceProperty]
                            );
                        }
                    }
                }
            }
        }

        return $dataRules->rules;
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
                $fieldMeta = $this->metadataFactory->findFieldMetadataInParent($field, $metadata);
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
                        $allRules[$fullF] = $this->ruleFactory->normalizeRule($baseRules);
                    } else {
                        $allRules[$fullF] = $this->ruleFactory->normalizeRule($r);
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
                    $allRules[$fullField] = $this->ruleFactory->normalizeRule($baseRules);
                } else {
                    $allRules[$fullField] = $this->ruleFactory->normalizeRule($rule);
                }
            } else {
                // Check if this field is part of a nested object by looking for flattened properties
                $isPartOfNestedObject = false;
                if (str_contains($field, '.')) {
                    $baseField = substr($field, 0, strpos($field, '.'));
                    $baseMeta = $metadata[$baseField] ?? $this->metadataFactory->findFieldMetadataInParent($baseField, $metadata);

                    if ($baseMeta && $baseMeta->isNestedDataObject()) {
                        $isPartOfNestedObject = true;

                        // Add marker for the base object if not already added
                        $baseFullField = $prefix ? $prefix.'.'.$baseField : $baseField;
                        if (! isset($allRules[$baseFullField.'.__type'])) {
                            $allRules[$baseFullField.'.__type'] = 'nested_object';
                            $allRules[$baseFullField.'.__class'] = $baseMeta->dataClass;
                        }

                        // Add this flattened property to the nested object
                        $allRules[$fullField] = $this->ruleFactory->normalizeRule($rule);
                    }
                }

                if (! $isPartOfNestedObject) {
                    // Regular field
                    $allRules[$fullField] = $this->ruleFactory->normalizeRule($rule);
                }
            }
        }

        // If this is the root call, resolve the rules
        if ($prefix === '') {
            $validator = $this->dataValidatorResolver->execute($class->getName(), []);
            $this->mergeInheritedMessages($class, $validator);

            // Collect and merge nested custom messages
            $nestedMessages = $this->collectNestedMessages($class);
            $this->messageService->mergeNestedMessages($nestedMessages, $validator);

            // Build a flattened metadata dictionary for all nested fields
            $flattenedMetadata = $this->metadataFactory->flattenMetadata($metadata);

            return $this->resolveRulesFromValidatorWithMetadata($validator, $allRules, $flattenedMetadata);
        }

        return $allRules;
    }

    /**
     * Collect custom messages from nested Data classes
     */
    protected function collectNestedMessages(ReflectionClass $class, string $prefix = ''): array
    {
        $messages = [];

        // Get messages from the current class
        if (method_exists($class->getName(), 'messages')) {
            $classMessages = $class->getName()::messages();
            foreach ($classMessages as $key => $message) {
                // Add prefix to the message key
                $fullKey = $prefix ? $prefix.'.'.$key : $key;
                $messages[$fullKey] = $message;
            }
        }

        // Get DataConfig to access properties
        $dataConfig = app(DataConfig::class)->getDataClass($class->getName());

        // Process nested Data objects
        foreach ($dataConfig->properties as $property) {
            // Use the mapped name if available for the field path, but keep using property name for processing
            $fieldName = $property->inputMappedName ?? $property->name;

            if ($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataObject && $property->type->dataClass) {
                // Nested Data object
                $nestedClass = new ReflectionClass($property->type->dataClass);
                // If we're already in an array context (prefix contains .*), nested objects get an extra .*
                // because Laravel Data treats nested objects within arrays as potentially having array-like validation paths
                if (str_contains($prefix, '.*')) {
                    $nestedPrefix = $prefix.'.'.$fieldName.'.*';
                } else {
                    $nestedPrefix = $prefix ? $prefix.'.'.$fieldName : $fieldName;
                }
                $nestedMessages = $this->collectNestedMessages($nestedClass, $nestedPrefix);
                $messages = array_merge($messages, $nestedMessages);
            } elseif (($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataCollection ||
                       $property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataArray) &&
                      $property->type->dataClass) {
                // Collection of Data objects
                $nestedClass = new ReflectionClass($property->type->dataClass);
                $nestedPrefix = $prefix ? $prefix.'.'.$fieldName.'.*' : $fieldName.'.*';
                $nestedMessages = $this->collectNestedMessages($nestedClass, $nestedPrefix);
                $messages = array_merge($messages, $nestedMessages);
            }
        }

        return $messages;
    }

    /**
     * Merge inherited validation messages from InheritValidationFrom attributes
     */
    protected function mergeInheritedMessages(ReflectionClass $class, Validator $validator): void
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
        // Delegate to NestedRuleGrouper's metadata-aware method
        return $this->ruleGrouper->groupRulesByBaseFieldWithMetadata($rules, $metadata);
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
            $type = $property->validations->inferredType ?? 'string';

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
