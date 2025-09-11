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

        return $this->resolveRulesFromValidator($validator, $rules);
    }
}
