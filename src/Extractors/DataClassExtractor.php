<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Validation\NestedRules;
use Illuminate\Validation\Validator;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
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
        $properties = $this->recursivelyExtractProperties($class);
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

        if (str_ends_with($className, 'Data')) {
            return substr($className, 0, -4).'Schema';
        }

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

        // First, detect nested Data objects that aren't handled as NestedRules
        // These will appear as regular fields with dot notation in the flattened rules
        $nestedDataObjects = [];
        foreach ($parentProperties as $property) {
            if ($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataObject) {
                // Use the mapped name if available, otherwise use the property name
                $fieldName = $property->inputMappedName ?? $property->name;
                $nestedDataObjects[$fieldName] = $property;
            }
        }

        // Process rules
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
                // Check if this field is part of a nested Data object
                $isNestedObjectField = false;
                foreach ($nestedDataObjects as $nestedFieldName => $nestedProperty) {
                    if (str_starts_with($field, $nestedFieldName.'.')) {
                        // This is a property of a nested Data object
                        // We'll handle these separately to group them properly
                        $isNestedObjectField = true;
                        break;
                    }
                }
                
                if (!$isNestedObjectField) {
                    // Regular field - add it with the full path
                    $allRules[$fullField] = $this->normalizeRule($rule);
                } else {
                    // Store for later processing - we'll group these
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

        // For nested calls, just return the raw rules array
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
     * Resolve rules from validator with special handling for nested Data objects
     *
     * @param  Validator  $validator
     * @param  array  $rules
     * @param  array  $nestedDataObjects  Array of nested Data object properties keyed by mapped field name
     * @return SchemaPropertyData[]
     */
    protected function resolveRulesFromValidatorWithNestedObjects($validator, array $rules, array $nestedDataObjects): array
    {
        // First, normalize all rules to string format
        $normalizedRules = [];
        foreach ($rules as $field => $rule) {
            $normalizedRules[$field] = $this->normalizeRule($rule);
        }

        // Group rules by base field for nested array handling
        $groupedRules = $this->groupRulesByBaseField($normalizedRules);
        
        // Now handle nested Data objects that were flattened
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
            if (!empty($nestedObjectRules)) {
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
        if (!empty($fieldRules['nested'])) {
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
