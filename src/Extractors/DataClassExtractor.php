<?php

namespace RomegaSoftware\LaravelZodGenerator\Extractors;

use ReflectionClass;
use ReflectionParameter;
use ReflectionUnionType;
use RomegaSoftware\LaravelZodGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelZodGenerator\Attributes\ZodSchema;
use RomegaSoftware\LaravelZodGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\ValidationRulesFactory;
use Spatie\LaravelData\DataCollection;

/**
 * Extractor for Spatie Laravel Data classes
 * This class is only loaded when spatie/laravel-data is installed
 */
class DataClassExtractor implements ExtractorInterface
{
    /**
     * Check if this extractor can handle the given class
     */
    public function canHandle(ReflectionClass $class): bool
    {
        // Check if class has ZodSchema attribute
        if (empty($class->getAttributes(ZodSchema::class))) {
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
        $attributes = $class->getAttributes(ZodSchema::class);

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
        $constructor = $class->getConstructor();
        if (! $constructor) {
            return [];
        }

        $properties = [];
        $messages = $this->extractMessages($class);

        foreach ($constructor->getParameters() as $parameter) {
            $validations = $this->extractValidations($parameter, $class, $messages);

            $parameterType = $this->getParameterType($parameter, $class);

            $property = new SchemaPropertyData(
                name: $parameter->getName(),
                type: $parameterType,
                isOptional: $this->isParameterOptional($parameter),
                validations: ValidationRulesFactory::create($parameterType, $validations),
            );

            $properties[] = $property;
        }

        return $properties;
    }

    /**
     * Extract custom messages from the Data class
     */
    protected function extractMessages(ReflectionClass $class): array
    {
        if ($class->hasMethod('messages')) {
            $messagesMethod = $class->getMethod('messages');
            if ($messagesMethod->isStatic() && $messagesMethod->isPublic()) {
                return $messagesMethod->invoke(null);
            }
        }

        return [];
    }

    /**
     * Check if a parameter is optional
     */
    protected function isParameterOptional(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        // Check for Spatie Optional type
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if (class_exists(\Spatie\LaravelData\Optional::class) &&
                    $unionType->getName() === \Spatie\LaravelData\Optional::class) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the type of a parameter
     */
    protected function getParameterType(ReflectionParameter $parameter, ReflectionClass $class): string
    {
        $paramName = $parameter->getName();

        // Check for DataCollectionOf attribute
        if ($class->hasProperty($paramName)) {
            $property = $class->getProperty($paramName);

            if (class_exists(\Spatie\LaravelData\Attributes\DataCollectionOf::class)) {
                $dataCollectionOfAttributes = $property->getAttributes(\Spatie\LaravelData\Attributes\DataCollectionOf::class);

                if (! empty($dataCollectionOfAttributes)) {
                    $dataCollectionOf = $dataCollectionOfAttributes[0]->newInstance();
                    $dataClass = $dataCollectionOf->class;

                    return 'DataCollection:'.class_basename($dataClass);
                }
            }
        }

        $type = $parameter->getType();

        if (! $type) {
            return 'any';
        }

        if ($type instanceof ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $unionType) {
                $name = $unionType->getName();
                if (class_exists(\Spatie\LaravelData\Optional::class) &&
                    $name !== \Spatie\LaravelData\Optional::class &&
                    $name !== 'null') {
                    $types[] = $name;
                }
            }
            $typeName = implode('|', $types) ?: 'string';
        } else {
            $typeName = $type->getName();
        }

        // Handle class types
        if (class_exists($typeName)) {
            // Check if it's an enum
            if (enum_exists($typeName)) {
                return 'enum:'.class_basename($typeName);
            }

            // Check if it's a DataCollection
            if (class_exists(\Spatie\LaravelData\DataCollection::class) &&
                $typeName === \Spatie\LaravelData\DataCollection::class) {
                return 'any';
            }

            return class_basename($typeName);
        }

        // Handle arrays
        if ($typeName === 'array') {
            return $this->getArrayType($parameter, $class);
        }

        return match ($typeName) {
            'string' => 'string',
            'int', 'integer' => 'number',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            default => 'any',
        };
    }

    /**
     * Get array type from PHPDoc if available
     */
    protected function getArrayType(ReflectionParameter $parameter, ReflectionClass $class): string
    {
        $constructor = $class->getConstructor();
        if ($constructor) {
            $docComment = $constructor->getDocComment();
            $paramName = $parameter->getName();

            if ($docComment && preg_match('/@var\s+(\w+\[\]).*\$'.$paramName.'/m', $docComment, $matches)) {
                $phpDocType = $matches[1];
                if (str_ends_with($phpDocType, '[]')) {
                    $itemType = substr($phpDocType, 0, -2);

                    return 'array:'.$itemType;
                }
            }
        }

        return 'array';
    }

    /**
     * Extract validations from a parameter
     */
    protected function extractValidations(ReflectionParameter $parameter, ReflectionClass $class, array $messages): array
    {
        $validations = [];
        $paramName = $parameter->getName();

        // Check for InheritValidationFrom first
        $inheritAttributes = $parameter->getAttributes(InheritValidationFrom::class);
        if (! empty($inheritAttributes)) {
            $inheritAttribute = $inheritAttributes[0]->newInstance();
            $validations = $this->extractInheritedValidations(
                $inheritAttribute->class,
                $inheritAttribute->property ?? $paramName
            );
        }

        // Extract validations from Spatie validation attributes
        $attributes = $parameter->getAttributes();
        foreach ($attributes as $attribute) {
            $attributeClass = $attribute->getName();

            // Check if it's a Spatie validation attribute
            if (! str_contains($attributeClass, 'Validation')) {
                continue;
            }

            $instance = $attribute->newInstance();
            $this->processValidationAttribute($instance, $validations);
        }

        // Extract validations from rules() method if it exists
        if ($class->hasMethod('rules')) {
            $rulesMethod = $class->getMethod('rules');
            if ($rulesMethod->isStatic() && $rulesMethod->isPublic()) {
                $rules = $rulesMethod->invoke(null);
                if (isset($rules[$paramName])) {
                    $this->processRules($rules[$paramName], $validations);
                }
            }
        }

        // Add custom messages (only if not already set by inheritance)
        if (! isset($validations['customMessages'])) {
            $validations['customMessages'] = $this->extractFieldMessages($paramName, $messages);
        }

        return $validations;
    }

    /**
     * Process a validation attribute instance
     */
    protected function processValidationAttribute($instance, array &$validations): void
    {
        $className = get_class($instance);
        $baseName = class_basename($className);

        switch ($baseName) {
            case 'Required':
                $validations['required'] = true;
                break;
            case 'Nullable':
                $validations['nullable'] = true;
                break;
            case 'StringType':
                $validations['string'] = true;
                break;
            case 'Email':
                $validations['email'] = true;
                break;
            case 'Max':
                $reflection = new \ReflectionObject($instance);
                if ($reflection->hasProperty('value')) {
                    $prop = $reflection->getProperty('value');
                    $prop->setAccessible(true);
                    $validations['max'] = $prop->getValue($instance);
                }
                break;
            case 'Min':
                $reflection = new \ReflectionObject($instance);
                if ($reflection->hasProperty('value')) {
                    $prop = $reflection->getProperty('value');
                    $prop->setAccessible(true);
                    $validations['min'] = $prop->getValue($instance);
                }
                break;
            case 'Regex':
                $reflection = new \ReflectionObject($instance);
                if ($reflection->hasProperty('pattern')) {
                    $prop = $reflection->getProperty('pattern');
                    $prop->setAccessible(true);
                    $validations['regex'] = $prop->getValue($instance);
                }
                break;
            case 'Confirmed':
                $validations['confirmed'] = true;
                break;
            case 'Unique':
                $validations['unique'] = true;
                break;
        }
    }

    /**
     * Process Laravel validation rules
     */
    protected function processRules($rules, array &$validations): void
    {
        $rulesList = is_array($rules) ? $rules : [$rules];

        foreach ($rulesList as $rule) {
            if (is_object($rule)) {
                continue; // Skip object rules
            }

            $parts = explode(':', $rule, 2);
            $ruleName = $parts[0];
            $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

            switch ($ruleName) {
                case 'required':
                    $validations['required'] = true;
                    break;
                case 'string':
                    $validations['string'] = true;
                    break;
                case 'email':
                    $validations['email'] = true;
                    break;
                case 'max':
                    $validations['max'] = (int) ($parameters[0] ?? 0);
                    break;
                case 'min':
                    $validations['min'] = (int) ($parameters[0] ?? 0);
                    break;
                case 'regex':
                    $validations['regex'] = $parameters[0] ?? '';
                    break;
                case 'confirmed':
                    $validations['confirmed'] = true;
                    break;
            }
        }
    }

    /**
     * Extract inherited validations
     */
    protected function extractInheritedValidations(string $className, string $propertyName): array
    {
        if (! class_exists($className)) {
            return [];
        }

        $reflectionClass = new ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();

        if (! $constructor) {
            return [];
        }

        // Extract messages from the inherited class
        $inheritedMessages = $this->extractMessages($reflectionClass);

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getName() === $propertyName) {
                // Extract validations using the original method
                $validations = $this->extractValidations($parameter, $reflectionClass, $inheritedMessages);

                // Override the customMessages with the properly extracted field messages
                if (isset($inheritedMessages) && ! empty($inheritedMessages)) {
                    $validations['customMessages'] = $this->extractFieldMessages($propertyName, $inheritedMessages);
                }

                return $validations;
            }
        }

        return [];
    }

    /**
     * Extract custom messages for a specific field
     */
    protected function extractFieldMessages(string $field, array $messages): array
    {
        $fieldMessages = [];

        foreach ($messages as $key => $message) {
            if (str_starts_with($key, $field.'.')) {
                $validationType = substr($key, strlen($field) + 1);
                $fieldMessages[$validationType] = $message;

                // Handle array item validations
                if (str_starts_with($validationType, '*.')) {
                    if (! isset($fieldMessages['arrayItemValidations'])) {
                        $fieldMessages['arrayItemValidations'] = [];
                    }
                    $fieldMessages['arrayItemValidations']['customMessages'][$validationType] = $message;
                }
            }
        }

        return $fieldMessages;
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
            $type = $property->type;

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
