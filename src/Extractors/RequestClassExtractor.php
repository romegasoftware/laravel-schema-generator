<?php

namespace RomegaSoftware\LaravelZodGenerator\Extractors;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use RomegaSoftware\LaravelZodGenerator\Attributes\ZodSchema;
use RomegaSoftware\LaravelZodGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\ValidationRulesFactory;
use Spatie\LaravelData\DataCollection;

class RequestClassExtractor implements ExtractorInterface
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
        $schemaName = $this->getSchemaName($class);
        $rules = $this->extractRules($class);
        $messages = $this->extractMessages($class);
        $properties = $this->transformRulesToProperties($rules, $messages);

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

        if (str_ends_with($className, 'Request')) {
            return substr($className, 0, -7).'Schema';
        }

        return $className.'Schema';
    }

    /**
     * Extract rules from the class
     */
    protected function extractRules(ReflectionClass $class): array
    {
        // Try to instantiate the class to get rules
        try {
            if ($class->isInstantiable()) {
                $instance = $class->newInstanceWithoutConstructor();

                if (method_exists($instance, 'rules')) {
                    $rulesMethod = $class->getMethod('rules');
                    $rulesMethod->setAccessible(true);

                    // Check if method is static
                    if ($rulesMethod->isStatic()) {
                        return $rulesMethod->invoke(null);
                    } else {
                        return $rulesMethod->invoke($instance);
                    }
                }
            }
        } catch (\Exception $e) {
            // If we can't instantiate, try static invocation
            if ($class->hasMethod('rules')) {
                $rulesMethod = $class->getMethod('rules');
                if ($rulesMethod->isStatic()) {
                    return $rulesMethod->invoke(null);
                }
            }
        }

        return [];
    }

    /**
     * Extract custom messages from the class
     */
    protected function extractMessages(ReflectionClass $class): array
    {
        try {
            if ($class->isInstantiable() && $class->hasMethod('messages')) {
                $instance = $class->newInstanceWithoutConstructor();
                $messagesMethod = $class->getMethod('messages');
                $messagesMethod->setAccessible(true);

                if ($messagesMethod->isStatic()) {
                    return $messagesMethod->invoke(null);
                } else {
                    return $messagesMethod->invoke($instance);
                }
            }
        } catch (\Exception $e) {
            // Silently fail and return empty messages
        }

        return [];
    }

    /**
     * Transform Laravel validation rules to properties array
     *
     * @return SchemaPropertyData[]
     */
    protected function transformRulesToProperties(array $rules, array $messages): array
    {
        $properties = [];
        $nestedRules = ValidationRulesFactory::parseNestedRulesMagically($rules);

        foreach ($rules as $field => $fieldRules) {
            // Skip nested rules (handled separately)
            if (str_contains($field, '.')) {
                continue;
            }

            // Use magical parsing for zero-maintenance validation handling
            $validationRules = ValidationRulesFactory::createMagically($fieldRules);

            // Extract type from the validation rules object
            $type = $this->extractTypeFromValidationRules($validationRules, $fieldRules);

            // Add custom messages to the validation rules
            $customMessages = $this->extractFieldMessages($field, $messages);
            if (! empty($customMessages)) {
                $validationRules = $this->addCustomMessagesToValidationRules($validationRules, $customMessages);
            }

            // Check for array item validations
            if (isset($nestedRules[$field]['*'])) {
                $validationRules = $this->addArrayItemValidations($validationRules, $nestedRules[$field]['*']);
            }

            $properties[] = new SchemaPropertyData(
                name: $field,
                type: $type,
                isOptional: ! $validationRules->isRequired(),
                validations: $validationRules,
            );
        }

        return $properties;
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
            }
        }

        return $fieldMessages;
    }

    /**
     * Extract type from validation rules object
     */
    protected function extractTypeFromValidationRules($validationRules, $originalRules): string
    {
        // Try to get type from validation rules object methods
        if (method_exists($validationRules, 'getType')) {
            return $validationRules->getType();
        }

        // Fallback to checking specific validation rule types
        if (method_exists($validationRules, 'hasValidation')) {
            if ($validationRules->hasValidation('in')) {
                return 'enum';
            }
            if ($validationRules->hasValidation('email')) {
                return 'email';
            }
            if ($validationRules->hasValidation('boolean')) {
                return 'boolean';
            }
            if ($validationRules->hasValidation('array')) {
                return 'array';
            }
            if ($validationRules->hasValidation('numeric') || $validationRules->hasValidation('integer')) {
                return 'number';
            }
        }

        return 'string';
    }

    /**
     * Add custom messages to validation rules (if the rules object supports it)
     */
    protected function addCustomMessagesToValidationRules($validationRules, array $customMessages)
    {
        if (method_exists($validationRules, 'withCustomMessages')) {
            return $validationRules->withCustomMessages($customMessages);
        }

        // If the validation rules don't support custom messages directly,
        // we'll need to recreate with the messages included
        // This is a fallback approach
        return $validationRules;
    }

    /**
     * Add array item validations to validation rules
     */
    protected function addArrayItemValidations($validationRules, array $itemValidations)
    {
        if (method_exists($validationRules, 'withArrayItemValidations')) {
            return $validationRules->withArrayItemValidations($itemValidations);
        }

        // Fallback approach
        return $validationRules;
    }
}
