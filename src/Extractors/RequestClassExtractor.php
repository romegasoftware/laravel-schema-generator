<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaNameGenerator;
use Spatie\LaravelData\DataCollection;

class RequestClassExtractor extends BaseExtractor
{
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
        $schemaName = SchemaNameGenerator::fromClass($class);
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
     * Transform Laravel validation rules to properties array
     *
     * @return SchemaPropertyData[]
     */
    protected function transformRulesToProperties(ReflectionClass $class): array
    {
        $validator = $this->getValidatorInstance($class);
        $rules = $this->getValidationRules($class);

        return $this->resolveRulesFromValidator($validator, $rules);
    }

    /**
     * Get validator instance from class
     */
    protected function getValidatorInstance(ReflectionClass $class): \Illuminate\Validation\Validator
    {
        $instance = $class->newInstance();
        $instance->setContainer(app());
        $method = $class->getMethod('getValidatorInstance');

        return $method->isStatic() 
            ? $method->invoke(null) 
            : $method->invoke($instance);
    }

    /**
     * Get validation rules from class
     */
    protected function getValidationRules(ReflectionClass $class): array
    {
        $instance = $class->newInstance();
        $instance->setContainer(app());
        $method = $class->getMethod('validationRules');

        return $method->isStatic() 
            ? $method->invoke(null) 
            : $method->invoke($instance);
    }
}
