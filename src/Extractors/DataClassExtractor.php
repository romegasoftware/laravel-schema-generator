<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Validation\NestedRules;
use ReflectionClass;
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

        foreach ($rules as $field => $rule) {
            $fullField = $prefix ? $prefix.'.'.$field : $field;

            if ($rule instanceof NestedRules) {
                // This is a nested Data class or DataCollection
                $parentProperty = substr($field, 0, -2); // Remove .* suffix
                $property = $parentProperties->get($parentProperty);

                if ($property && $property->type->dataClass) {
                    // Add the array rule for the parent field
                    $parentField = $prefix ? $prefix.'.'.$parentProperty : $parentProperty;
                    $allRules[$parentField] = 'array';

                    // Recursively get rules from the nested Data class
                    $nestedPrefix = $parentField.'.*';
                    $reflectedDataChildClass = new ReflectionClass($property->type->dataClass);
                    $nestedRules = $this->recursivelyExtractProperties($reflectedDataChildClass, $nestedPrefix);

                    // Merge nested rules into our collection
                    $allRules = array_merge($allRules, $nestedRules);
                }
            } else {
                // Regular field - add it with the full path
                // Use the parent's normalizeRule method to handle all rule types
                $allRules[$fullField] = $this->normalizeRule($rule);
            }
        }

        // If this is the root call (no prefix), resolve the rules using validator
        if ($prefix === '') {
            $validator = $this->dataValidatorResolver->execute($class->getName(), []);

            return $this->resolveRulesFromValidator($validator, $allRules);
        }

        // For nested calls, just return the raw rules array
        return $allRules;
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
