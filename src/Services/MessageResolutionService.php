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
    /**
     * Resolve a custom message for a field and rule combination
     *
     * @param  string  $field  The field name
     * @param  string  $ruleName  The validation rule name
     * @param  Validator  $validator  The validator instance
     * @param  array  $parameters  The rule parameters
     * @param  bool  $isNumericField  Whether the field is numeric
     * @return string The resolved message
     */
    public function resolveCustomMessage(
        string $field,
        string $ruleName,
        Validator $validator,
        array $parameters = [],
        bool $isNumericField = false
    ): string {
        // Check for custom message first
        $customMessageKey = $field.'.'.lcfirst($ruleName);

        if (isset($validator->customMessages[$customMessageKey])) {
            return $validator->customMessages[$customMessageKey];
        }

        // Fall back to default Laravel message
        return $this->getDefaultMessage($field, $ruleName, $validator, $parameters, $isNumericField);
    }

    /**
     * Get the default Laravel validation message
     *
     * @param  string  $field  The field name
     * @param  string  $ruleName  The validation rule name
     * @param  Validator  $validator  The validator instance
     * @param  array  $parameters  The rule parameters
     * @param  bool  $isNumericField  Whether the field is numeric
     * @return string The default message
     */
    private function getDefaultMessage(
        string $field,
        string $ruleName,
        Validator $validator,
        array $parameters = [],
        bool $isNumericField = false
    ): string {
        // For numeric fields with min/max rules, we need to help Laravel understand the type
        if ($isNumericField && in_array(strtolower($ruleName), ['min', 'max'])) {
            // Temporarily set numeric data for this field to get proper message
            $originalData = $validator->getData();
            $tempData = $originalData;
            $this->setNestedValue($tempData, $field, 1); // Set a numeric value
            $validator->setData($tempData);
        }

        // Use reflection to access the protected getMessage method
        $validatorReflection = new ReflectionClass($validator);
        $getMessage = $validatorReflection->getMethod('getMessage');

        $message = $validator->makeReplacements(
            $getMessage->invoke($validator, $field, $ruleName),
            $field,
            $ruleName,
            $parameters
        );

        // Restore original data if we modified it
        if ($isNumericField && in_array(strtolower($ruleName), ['min', 'max']) && isset($originalData)) {
            $validator->setData($originalData);
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
