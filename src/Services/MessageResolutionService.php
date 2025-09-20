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

    protected array $numericRules = ['size', 'min', 'max', 'between', 'gt', 'lt', 'gte', 'lte'];

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

        $lowerRule = strtolower($this->ruleName);

        $messageParameters = $this->parameters;
        $messageParameters['attribute'] = $this->field;

        try {
            $translator = $this->validator->getTranslator();
            $messagePath = "validation.{$lowerRule}";
            $translation = $translator->get($messagePath, $messageParameters);

            if ($translation !== $messagePath) {
                return $this->validator->makeReplacements(
                    $translation,
                    $this->field,
                    $this->ruleName,
                    $messageParameters
                );
            }
        } catch (\Throwable) {
            // ignore translation issues and fall back to validator resolution
        }

        if ($this->isNumericField && in_array($lowerRule, $this->numericRules, true)) {
            $translator = $this->validator->getTranslator();
            $numericKey = "validation.{$lowerRule}.numeric";
            $numericMessage = $translator->get($numericKey, $messageParameters);

            if ($numericMessage !== $numericKey) {
                return $this->validator->makeReplacements(
                    $numericMessage,
                    $this->field,
                    $this->ruleName,
                    $messageParameters
                );
            }
        }

        // For numeric fields with min/max rules, we need to help Laravel understand the type
        if ($this->isNumericField && in_array(strtolower($this->ruleName), $this->numericRules)) {
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
            $messageParameters
        );

        // Restore original data if we modified it
        if ($this->isNumericField && in_array(strtolower($this->ruleName), $this->numericRules) && isset($originalData)) {
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
        if ($key === '') {
            return;
        }

        $segments = explode('.', $key);
        $this->assignValueToSegments($array, $segments, $value);
    }

    /**
     * Recursively assign a value using dot/wildcard notation segments
     */
    private function assignValueToSegments(&$target, array $segments, $value): void
    {
        if (empty($segments)) {
            $target = $value;

            return;
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            if (! is_array($target)) {
                $target = [];
            }

            if (empty($target)) {
                $target[0] = [];
            } elseif (! isset($target[0]) || ! is_array($target[0])) {
                $target[0] = [];
            }

            $this->assignValueToSegments($target[0], $segments, $value);

            return;
        }

        if (! is_array($target)) {
            $target = [];
        }

        if (! isset($target[$segment]) || ! is_array($target[$segment])) {
            $target[$segment] = [];
        }

        if (empty($segments)) {
            $target[$segment] = $value;

            return;
        }

        $this->assignValueToSegments($target[$segment], $segments, $value);
    }
}
