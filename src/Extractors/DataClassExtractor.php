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
use RomegaSoftware\LaravelSchemaGenerator\Services\DataClassRuleProcessor;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use RomegaSoftware\LaravelSchemaGenerator\Services\MessageResolutionService;
use RomegaSoftware\LaravelSchemaGenerator\Services\NestedMessageHandler;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaNameGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Traits\Makeable;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Resolvers\DataValidatorResolver;

/**
 * Extractor for Spatie Laravel Data classes
 * This class is only loaded when spatie/laravel-data is installed
 */
class DataClassExtractor extends BaseExtractor
{
    use Makeable;

    protected NestedMessageHandler $messageHandler;

    public function __construct(
        protected LaravelValidationResolver $validationResolver,
        protected DataValidatorResolver $dataValidatorResolver,
        protected MessageResolutionService $messageService,
        protected FieldMetadataFactory $metadataFactory,
        protected DataClassRuleProcessor $ruleProcessor
    ) {
        parent::__construct($validationResolver);
        $this->messageHandler = new NestedMessageHandler($messageService);
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
        // Delegate to the specialized processor service
        return $this->ruleProcessor->processDataClass($class);
    }

    /**
     * Extract properties using field metadata
     */
    protected function recursivelyExtractPropertiesWithMetadata(ReflectionClass $class, string $prefix, array $metadata): array
    {
        $rules = collect($this->extractRules($class));
        $allRules = [];

        // Process each rule
        foreach ($rules as $field => $rule) {
            $this->processRuleWithMetadata($field, $rule, $prefix, $metadata, $rules, $allRules);
        }

        // If this is the root call, finalize the extraction
        if ($prefix === '') {
            return $this->finalizeRootExtraction($class, $allRules, $metadata);
        }

        return $allRules;
    }

    /**
     * Process a single rule with metadata guidance
     */
    protected function processRuleWithMetadata(
        string $field,
        $rule,
        string $prefix,
        array $metadata,
        $rules,
        array &$allRules
    ): void {
        $fieldMeta = $this->findFieldMetadata($field, $prefix, $metadata);

        if ($rule instanceof NestedRules) {
            $this->processNestedRules($field, $prefix, $metadata, $allRules);
        } elseif ($fieldMeta && $fieldMeta->isNestedDataObject()) {
            // Pass the actual field name we're processing (which might be the mapped name)
            $this->processNestedDataObject($field, $fieldMeta, $prefix, $rules, $allRules);
        } else {
            $this->processRegularRule($field, $rule, $prefix, $metadata, $allRules);
        }
    }

    /**
     * Find metadata for a field
     */
    protected function findFieldMetadata(string $field, string $prefix, array $metadata)
    {
        $fieldMeta = $metadata[$field] ?? null;

        // If not found and we're in an array context, try without the array prefix
        if (! $fieldMeta && str_contains($prefix, '.*')) {
            $fieldMeta = $this->metadataFactory->findFieldMetadataInParent($field, $metadata);
        }

        return $fieldMeta;
    }

    /**
     * Process nested rules (DataCollection/array)
     */
    protected function processNestedRules(string $field, string $prefix, array $metadata, array &$allRules): void
    {
        $parentProperty = substr($field, 0, -2); // Remove .* suffix
        $parentMeta = $metadata[$parentProperty] ?? null;

        if ($parentMeta && $parentMeta->dataClass) {
            $parentField = $prefix ? $prefix.'.'.$parentProperty : $parentProperty;
            $nestedPrefix = $parentField.'.*';
            $reflectedDataChildClass = new ReflectionClass($parentMeta->dataClass);

            // Build child metadata
            $childMetadata = $this->buildChildMetadata($parentMeta);

            $nestedRules = $this->recursivelyExtractPropertiesWithMetadata(
                $reflectedDataChildClass,
                $nestedPrefix,
                $childMetadata
            );

            $allRules = array_merge($allRules, $nestedRules);
        }
    }

    /**
     * Build metadata for child properties
     */
    protected function buildChildMetadata($parentMeta): array
    {
        $childMetadata = [];
        foreach ($parentMeta->children as $child) {
            $childMetadata[$child->propertyName] = $child;
            if ($child->mappedName && $child->mappedName !== $child->propertyName) {
                $childMetadata[$child->mappedName] = $child;
            }
        }

        return $childMetadata;
    }

    /**
     * Process nested Data object
     */
    protected function processNestedDataObject(
        string $field,
        $fieldMeta,
        string $prefix,
        $rules,
        array &$allRules
    ): void {
        $fullField = $prefix ? $prefix.'.'.$field : $field;
        $inArrayContext = str_contains($prefix, '.*');

        if (! $inArrayContext) {
            // At root level - group properties
            // The field passed in is already the correct name to search for (might be mapped name)
            $this->processRootNestedObject($field, $fieldMeta, $prefix, $rules, $allRules);
        } else {
            // Within array context
            $this->processArrayNestedObject($field, $fullField, $rules, $allRules);
        }
    }

    /**
     * Process nested object at root level
     */
    protected function processRootNestedObject(
        string $searchField,
        $fieldMeta,
        string $prefix,
        $rules,
        array &$allRules
    ): void {
        // The searchField is what we look for in rules (possibly mapped name)
        // But we output using the searchField consistently
        $fullField = $prefix ? $prefix.'.'.$searchField : $searchField;
        $groupedRules = [];

        // Collect all rules for this object
        foreach ($rules as $f => $r) {
            if ($f === $searchField || str_starts_with($f, $searchField.'.')) {
                $groupedRules[$f] = $r;
            }
        }

        // Add markers
        $allRules[$fullField.'.__type'] = 'nested_object';
        $allRules[$fullField.'.__class'] = $fieldMeta->dataClass;

        // Process grouped rules
        foreach ($groupedRules as $f => $r) {
            $fullF = $prefix ? $prefix.'.'.$f : $f;
            $normalized = $this->normalizeObjectRule($f, $searchField, $r);
            $allRules[$fullF] = $normalized;
        }
    }

    /**
     * Process nested object within array context
     */
    protected function processArrayNestedObject(
        string $field,
        string $fullField,
        $rules,
        array &$allRules
    ): void {
        $rule = $rules[$field] ?? null;
        if (is_array($rule) && in_array('array', $rule)) {
            $baseRules = array_filter($rule, fn ($r) => $r !== 'array');
            if (empty($baseRules)) {
                $baseRules = ['required'];
            }
            $allRules[$fullField] = $this->ruleFactory->normalizeRule($baseRules);
        } elseif ($rule !== null) {
            $allRules[$fullField] = $this->ruleFactory->normalizeRule($rule);
        }
    }

    /**
     * Normalize object rule
     */
    protected function normalizeObjectRule(string $f, string $field, $r): string
    {
        if ($f === $field && is_array($r) && in_array('array', $r)) {
            $baseRules = array_filter($r, fn ($rule) => $rule !== 'array');
            if (empty($baseRules)) {
                $baseRules = ['required'];
            }

            return $this->ruleFactory->normalizeRule($baseRules);
        }

        return $this->ruleFactory->normalizeRule($r);
    }

    /**
     * Process regular rule
     */
    protected function processRegularRule(
        string $field,
        $rule,
        string $prefix,
        array $metadata,
        array &$allRules
    ): void {
        $fullField = $prefix ? $prefix.'.'.$field : $field;
        $isPartOfNestedObject = false;

        if (str_contains($field, '.')) {
            $isPartOfNestedObject = $this->checkIfPartOfNestedObject($field, $rule, $prefix, $metadata, $allRules);
        }

        if (! $isPartOfNestedObject) {
            $allRules[$fullField] = $this->ruleFactory->normalizeRule($rule);
        }
    }

    /**
     * Check if field is part of a nested object
     */
    protected function checkIfPartOfNestedObject(
        string $field,
        $rule,
        string $prefix,
        array $metadata,
        array &$allRules
    ): bool {
        $baseField = substr($field, 0, strpos($field, '.'));
        $baseMeta = $metadata[$baseField] ?? $this->metadataFactory->findFieldMetadataInParent($baseField, $metadata);

        if ($baseMeta && $baseMeta->isNestedDataObject()) {
            $baseFullField = $prefix ? $prefix.'.'.$baseField : $baseField;

            if (! isset($allRules[$baseFullField.'.__type'])) {
                $allRules[$baseFullField.'.__type'] = 'nested_object';
                $allRules[$baseFullField.'.__class'] = $baseMeta->dataClass;
            }

            $fullField = $prefix ? $prefix.'.'.$field : $field;
            // Use the actual rule passed in
            $allRules[$fullField] = $this->ruleFactory->normalizeRule($rule);

            return true;
        }

        return false;
    }

    /**
     * Finalize root extraction
     */
    protected function finalizeRootExtraction(ReflectionClass $class, array $allRules, array $metadata): array
    {
        $validator = $this->dataValidatorResolver->execute($class->getName(), []);
        $this->mergeInheritedMessages($class, $validator);

        // Collect and merge nested custom messages
        $nestedMessages = $this->collectNestedMessages($class);
        $this->messageService->mergeNestedMessages($nestedMessages, $validator);

        // Build a flattened metadata dictionary
        $flattenedMetadata = $this->metadataFactory->flattenMetadata($metadata);

        return $this->resolveRulesFromValidatorWithMetadata($validator, $allRules, $flattenedMetadata);
    }

    /**
     * Collect custom messages from nested Data classes
     */
    protected function collectNestedMessages(ReflectionClass $class, string $prefix = ''): array
    {
        // Delegate to the message handler service
        return $this->messageHandler->collectMessages($class, $prefix);
    }

    /**
     * Merge inherited validation messages from InheritValidationFrom attributes
     */
    protected function mergeInheritedMessages(ReflectionClass $class, Validator $validator): void
    {
        // Delegate to the message handler service
        $this->messageHandler->mergeInheritedMessages($class, $validator);
    }

    /**
     * Resolve rules from validator using field metadata
     */
    protected function resolveRulesFromValidatorWithMetadata($validator, array $rules, array $metadata): array
    {
        // Group rules considering metadata
        $groupedRules = $this->groupRulesByBaseFieldWithMetadata($rules, $metadata);

        // Use base class method to create properties
        return $this->createPropertiesFromGroupedRules($groupedRules, $validator, $metadata);
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
