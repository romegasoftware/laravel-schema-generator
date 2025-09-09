<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Resolvers\DataValidatorResolver;

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
        $properties = $this->extractProperties($class);
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
     *
     * @return SchemaPropertyData[]
     */
    protected function extractProperties(ReflectionClass $class): array
    {
        $validator = $this->dataValidatorResolver->execute($class->getName(), []);

        $fields = $validator->getRules();
        $properties = [];

        // Check constructor for InheritValidationFrom attributes
        $inheritanceMap = $this->extractInheritanceMap($class);

        foreach ($fields as $field => $rules) {
            // Check if this field has inheritance
            if (isset($inheritanceMap[$field])) {
                $inheritedData = $inheritanceMap[$field];
                // Get validation from the inherited source
                $inheritedValidations = $this->getInheritedValidations(
                    $inheritedData['class'],
                    $inheritedData['property'] ?? $field,
                    $validator
                );

                if ($inheritedValidations) {
                    $properties[] = new SchemaPropertyData(
                        name: $field,
                        validator: $validator,
                        isOptional: ! $inheritedValidations->isFieldRequired(),
                        validations: $inheritedValidations,
                    );

                    continue;
                }
            }

            // TODO: what about $attributes with a wildcard & arrays? ie tags.* or tags.*.name
            // Convert array rules to pipe-separated string if needed
            $rulesString = is_array($rules) ? implode('|', $rules) : $rules;
            $resolvedValidationSet = $this->validationResolver->resolve($field, $rulesString, $validator);

            $properties[] = new SchemaPropertyData(
                name: $field,
                validator: $validator,
                isOptional: ! $resolvedValidationSet->isFieldRequired(),
                validations: $resolvedValidationSet,
            );
        }

        return $properties;
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

    /**
     * Extract InheritValidationFrom attributes from constructor parameters
     */
    protected function extractInheritanceMap(ReflectionClass $class): array
    {
        $inheritanceMap = [];
        
        $constructor = $class->getConstructor();
        if (!$constructor) {
            return $inheritanceMap;
        }
        
        foreach ($constructor->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes(InheritValidationFrom::class);
            if (!empty($attributes)) {
                $attribute = $attributes[0]->newInstance();
                $inheritanceMap[$parameter->getName()] = [
                    'class' => $attribute->class,
                    'property' => $attribute->property ?? $parameter->getName(),
                ];
            }
        }
        
        return $inheritanceMap;
    }
    
    /**
     * Get inherited validations from a source class and property
     */
    protected function getInheritedValidations(string $sourceClass, string $sourceProperty, $currentValidator)
    {
        try {
            $sourceReflection = new ReflectionClass($sourceClass);
            
            // Get validator for the source class
            $sourceValidator = $this->dataValidatorResolver->execute($sourceClass, []);
            $sourceRules = $sourceValidator->getRules();
            
            if (!isset($sourceRules[$sourceProperty])) {
                return null;
            }
            
            // Get the rules for the specific property
            $rules = $sourceRules[$sourceProperty];
            $rulesString = is_array($rules) ? implode('|', $rules) : $rules;
            
            // Resolve validations with the source validator to get custom messages
            $resolvedValidations = $this->validationResolver->resolve($sourceProperty, $rulesString, $sourceValidator);
            
            return $resolvedValidations;
        } catch (\Exception $e) {
            // If we can't get inherited validations, return null
            return null;
        }
    }
}
