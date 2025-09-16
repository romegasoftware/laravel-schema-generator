<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

use Illuminate\Validation\Validator;
use ReflectionClass;

/**
 * Service for resolving and managing validation messages
 *
 * Handles custom message resolution, merging, and nested value operations
 * for validation message handling across the application.
 */
class MessageResolutionService
{
    protected string $field;

    protected string $ruleName;

    protected Validator $validator;

    protected array $parameters = [];

    protected bool $isNumericField = false;

    protected array $rules = [];

    /**
     * Resolve a custom message for a field and rule combination
     *
     * @param  string  $field  The field name
     * @param  string  $ruleName  The validation rule name
     * @param  Validator  $validator  The validator instance
     * @param  array  $parameters  The rule parameters
     * @param  bool  $isNumericField  Whether the field is numeric
     * @param  array  $rules  An array of all the rules on the object, to determine numeric or file types
     */
    public function with(
        string $field,
        string $ruleName,
        Validator $validator,
        array $parameters = [],
        bool $isNumericField = false,
        array $rules = []
    ): self {
        $this->field = $field;
        $this->ruleName = $ruleName;
        $this->validator = $validator;
        $this->parameters = $parameters;
        $this->isNumericField = $isNumericField;
        $this->rules = $rules;

        return $this;
    }

    /**
     * Resolve a custom message for a field and rule combination
     *
     * @return string The resolved message
     */
    public function resolveCustomMessage(): string
    {
        if (empty($this->field) || empty($this->ruleName) || empty($this->validator)) {
            throw new \InvalidArgumentException('The field, rule name, and validator must be set using with() prior to calling resolveCustomMessage.');
        }

        // Check for custom message first
        $customMessageKey = $this->field.'.'.lcfirst($this->ruleName);

        if (isset($this->validator->customMessages[$customMessageKey])) {
            return $this->validator->customMessages[$customMessageKey];
        }

        // Fall back to default Laravel message
        return $this->getDefaultMessage();
    }

    /**
     * Get the default Laravel validation message
     *
     * @return string The default message
     */
    private function getDefaultMessage(): string
    {
        if (empty($this->field) || empty($this->ruleName) || empty($this->validator)) {
            throw new \InvalidArgumentException('The field, rule name, and validator must be set using with() prior to calling resolveCustomMessage.');
        }

        // For numeric fields with min/max rules, we need to help Laravel understand the type
        if ($this->isNumericField && in_array(strtolower($this->ruleName), ['min', 'max'])) {
            // Temporarily set numeric data for this field to get proper message
            $originalData = $this->validator->getData();
            $tempData = $originalData;
            $this->setNestedValue($tempData, $this->field, 1); // Set a numeric value
            $this->validator->setData($tempData);
        }

        // Use reflection to access the protected getMessage method
        $validatorReflection = new ReflectionClass($this->validator);
        $this->validator->setRules([$this->field => $this->rules]);
        $getMessage = $validatorReflection->getMethod('getMessage');

        $rawMessage = $getMessage->invoke($this->validator, $this->field, $this->ruleName);

        // Handle password rules that may return arrays of messages
        if (is_array($rawMessage)) {
            // For password rules, try to find the specific constraint message
            if (isset($rawMessage[$this->ruleName])) {
                $message = $rawMessage[$this->ruleName];
            } else {
                // Fallback to first message or generic message
                $message = reset($rawMessage) ?: "The {$this->field} field validation failed.";
            }
        } else {
            $message = $rawMessage;
        }

        $message = $this->validator->makeReplacements(
            $message,
            $this->field,
            $this->ruleName,
            $this->parameters
        );

        // Restore original data if we modified it
        if ($this->isNumericField && in_array(strtolower($this->ruleName), ['min', 'max']) && isset($originalData)) {
            $this->validator->setData($originalData);
        }

        return $message;
    }

    /**
     * Merge nested custom messages into a validator
     *
     * @param  array  $nestedMessages  The nested messages to merge
     * @param  Validator  $validator  The validator instance
     */
    public function mergeNestedMessages(array $nestedMessages, Validator $validator): void
    {
        foreach ($nestedMessages as $key => $message) {
            if (! isset($validator->customMessages[$key])) {
                $validator->customMessages[$key] = $message;
            }
        }
    }

    /**
     * Set a nested value in an array using dot notation
     *
     * @param  array  $array  The array to modify (passed by reference)
     * @param  string  $key  The dot notation key
     * @param  mixed  $value  The value to set
     */
    public function setNestedValue(array &$array, string $key, $value): void
    {
        // Handle wildcard fields (e.g., songs.*.field)
        if (str_contains($key, '.*')) {
            // Split by .* and process
            $parts = explode('.*', $key);
            $baseKey = $parts[0];
            $remainingKey = isset($parts[1]) ? ltrim($parts[1], '.') : '';

            // Ensure the base array exists
            if (! isset($array[$baseKey])) {
                $array[$baseKey] = [[]]; // Create array with one empty item
            } elseif (! is_array($array[$baseKey])) {
                $array[$baseKey] = [[]];
            } elseif (empty($array[$baseKey])) {
                $array[$baseKey] = [[]];
            }

            // Set the value in the first array item
            if ($remainingKey) {
                $this->setNestedValue($array[$baseKey][0], $remainingKey, $value);
            } else {
                $array[$baseKey][0] = $value;
            }

            return;
        }

        // Handle regular dot notation
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }
}
