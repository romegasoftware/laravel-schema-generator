<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaNameGenerator;
use Throwable;

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
            properties: SchemaPropertyData::collect($properties),
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
        if ($class->isSubclassOf(FormRequest::class)) {
            $validator = $this->getValidatorInstance($class);
            $rules = $this->getFormRequestValidationRules($class);

            return $this->resolveRulesFromValidator($validator, $rules);
        }

        [$rules, $instance] = $this->getRulesFromClass($class);
        $validator = $this->createValidatorForRulesClass($class, $rules, $instance);

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
     * Get validation rules from a FormRequest class
     */
    protected function getFormRequestValidationRules(ReflectionClass $class): array
    {
        $instance = $class->newInstance();
        $instance->setContainer(app());
        $method = $class->getMethod('validationRules');

        $rules = $method->isStatic()
            ? $method->invoke(null)
            : $method->invoke($instance);

        return $this->normalizeRulesResult($rules);
    }

    /**
     * Extract rules for a class that exposes a rules() method.
     */
    protected function getRulesFromClass(ReflectionClass $class): array
    {
        $this->ensureRulesMethodExists($class);

        $instance = $this->instantiateRulesClass($class);
        $method = $class->getMethod('rules');

        if ($method->isStatic()) {
            $result = $method->invoke(null);
        } else {
            if (! $instance) {
                throw new \RuntimeException("Unable to instantiate {$class->getName()} to call rules().");
            }

            $result = $method->invoke($instance);
        }

        return [$this->normalizeRulesResult($result), $instance];
    }

    /**
     * Create a validator for a non-FormRequest rules class.
     */
    protected function createValidatorForRulesClass(ReflectionClass $class, array $rules, ?object $instance): Validator
    {
        /** @var \Illuminate\Contracts\Validation\Factory $factory */
        $factory = app('validator');

        $messages = $this->callOptionalValidationMethod($class, 'messages', $instance);
        $attributes = $this->callOptionalValidationMethod($class, 'attributes', $instance);

        /** @var \Illuminate\Validation\Validator $validator */
        $validator = $factory->make([], $rules, $messages, $attributes);

        return $validator;
    }

    /**
     * Ensure the class defines a usable rules() method.
     */
    protected function ensureRulesMethodExists(ReflectionClass $class): void
    {
        if (! $class->hasMethod('rules')) {
            throw new \RuntimeException("Class {$class->getName()} must define a rules() method to be processed.");
        }
    }

    /**
     * Instantiate a class that provides validation rules.
     */
    protected function instantiateRulesClass(ReflectionClass $class): ?object
    {
        $method = $class->getMethod('rules');

        if ($method->isStatic() || ! $class->isInstantiable()) {
            return null;
        }

        try {
            return app()->make($class->getName());
        } catch (Throwable) {
            try {
                return $class->newInstanceWithoutConstructor();
            } catch (Throwable) {
                return null;
            }
        }
    }

    /**
     * Optionally call a validation helper method such as messages() or attributes().
     */
    protected function callOptionalValidationMethod(ReflectionClass $class, string $methodName, ?object $instance): array
    {
        if (! $class->hasMethod($methodName)) {
            return [];
        }

        $method = $class->getMethod($methodName);

        if ($method->isStatic()) {
            $result = $method->invoke(null);
        } else {
            $target = $instance ?? $this->instantiateRulesClass($class);

            $result = $target ? $method->invoke($target) : null;
        }

        return is_array($result) ? $result : ($result instanceof Arrayable ? $result->toArray() : []);
    }

    /**
     * Normalize the output of a rules() call into an array structure.
     */
    protected function normalizeRulesResult(mixed $rules): array
    {
        if (is_array($rules)) {
            return $rules;
        }

        if ($rules instanceof Arrayable) {
            return $rules->toArray();
        }

        if (is_iterable($rules)) {
            return iterator_to_array($rules);
        }

        return [];
    }
}
