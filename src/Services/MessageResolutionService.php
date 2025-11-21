<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use ReflectionClass;
use Throwable;

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

        $customMessage = $this->findCustomMessageForRule();

        if ($customMessage !== null) {
            return $customMessage;
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
        $snakeRule = Str::snake($this->ruleName);

        $originalData = $this->validator->getData();
        $tempData = $originalData;
        $dataModified = false;

        if ($this->isNumericField && in_array($lowerRule, $this->numericRules, true)) {
            $this->setNestedValue($tempData, $this->field, 1);
            $dataModified = true;
        }

        if ($snakeRule === 'required_if') {
            if ($this->applyRequiredIfValue($tempData)) {
                $dataModified = true;
            } else {
                $this->normalizeRequiredIfParameters();
            }
        }

        $messageParameters = $this->parameters;
        $messageParameters['attribute'] = $this->field;

        if ($dataModified) {
            $this->validator->setData($tempData);
        }

        $message = null;

        try {
            $translator = $this->validator->getTranslator();
            $messagePath = "validation.{$snakeRule}";
            $translation = $translator->get($messagePath, $messageParameters);

            if ($translation !== $messagePath) {
                $normalized = $this->normalizeMessage($translation);

                if ($normalized !== null) {
                    $message = $this->applyMessageReplacements($normalized, $messageParameters);
                }
            }
        } catch (\Throwable) {
            // ignore translation issues and fall back to validator resolution
        }

        if ($message === null && $this->isNumericField && in_array($lowerRule, $this->numericRules, true)) {
            $translator = $this->validator->getTranslator();
            $numericKey = "validation.{$snakeRule}.numeric";
            $numericMessage = $translator->get($numericKey, $messageParameters);

            if ($numericMessage !== $numericKey) {
                $normalized = $this->normalizeMessage($numericMessage);

                if ($normalized !== null) {
                    $message = $this->applyMessageReplacements($normalized, $messageParameters);
                }
            }
        }

        if ($message === null) {
            $validatorReflection = new ReflectionClass($this->validator);
            $this->validator->setRules([$this->field => $this->rules]);
            $getMessage = $validatorReflection->getMethod('getMessage');

            $rawMessage = $getMessage->invoke($this->validator, $this->field, $this->ruleName);

            $normalized = $this->normalizeMessage($rawMessage) ?? "The {$this->field} field validation failed.";

            $message = $this->applyMessageReplacements($normalized, $messageParameters);
        }

        if ($dataModified) {
            $this->validator->setData($originalData);
        }

        /** @var string $message */
        return $message;
    }

    private function applyRequiredIfValue(array &$data): bool
    {
        $otherField = $this->parameters[0] ?? null;

        if (! is_string($otherField) || $otherField === '') {
            return false;
        }

        $values = array_slice($this->parameters, 1);

        if (empty($values)) {
            return false;
        }

        $value = $values[0] ?? null;

        $normalizedField = $this->normalizeDependentField($otherField);

        if ($normalizedField === '') {
            $normalizedField = $otherField;
        }

        $this->parameters[0] = $normalizedField;

        $this->setNestedValue($data, $normalizedField, $value);

        return true;
    }

    private function normalizeMessage(mixed $message): ?string
    {
        if (is_string($message)) {
            return $message;
        }

        if (is_array($message)) {
            $preferredKey = $this->determinePreferredMessageKey($message);

            if ($preferredKey !== null && isset($message[$preferredKey]) && is_string($message[$preferredKey])) {
                return $message[$preferredKey];
            }

            if (isset($message[$this->ruleName]) && is_string($message[$this->ruleName])) {
                return $message[$this->ruleName];
            }

            foreach ($message as $value) {
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }

            $first = reset($message);

            return is_string($first) && $first !== '' ? $first : null;
        }

        if (is_object($message) && method_exists($message, '__toString')) {
            return (string) $message;
        }

        if (is_scalar($message)) {
            $stringMessage = (string) $message;

            return $stringMessage !== '' ? $stringMessage : null;
        }

        return null;
    }

    private function applyMessageReplacements(string $message, array $parameters): string
    {
        /** @var string $replaced */
        $replaced = $this->validator->makeReplacements(
            $message,
            $this->field,
            $this->ruleName,
            $parameters
        );

        return $this->ensureDisplayableAttribute($replaced);
    }

    /**
     * Locate a custom message for the current rule, including known aliases.
     */
    private function findCustomMessageForRule(): ?string
    {
        foreach ($this->possibleCustomMessageKeys() as $customMessageKey) {
            if (isset($this->validator->customMessages[$customMessageKey])) {
                return $this->validator->customMessages[$customMessageKey];
            }
        }

        return null;
    }

    /**
     * Build the list of possible custom message keys to check.
     */
    private function possibleCustomMessageKeys(): array
    {
        $baseRule = lcfirst($this->ruleName);
        $keys = [$this->field.'.'.$baseRule];

        foreach ($this->getRuleMessageAliases($baseRule) as $alias) {
            $keys[] = $this->field.'.'.$alias;
        }

        return array_values(array_unique($keys));
    }

    /**
     * Return aliases for a validation rule where Laravel normalizes the rule name.
     */
    private function getRuleMessageAliases(string $ruleName): array
    {
        return match (strtolower($ruleName)) {
            'in' => ['enum'],
            'enum' => ['in'],
            default => [],
        };
    }

    private function ensureDisplayableAttribute(string $message): string
    {
        $displayable = $this->getDisplayableAttribute();

        if ($displayable !== null && $displayable !== $this->field && str_contains($message, $this->field)) {
            $pattern = '/\b'.preg_quote($this->field, '/').'\b/u';
            $message = preg_replace($pattern, $displayable, $message) ?? $message;
        }

        return $message;
    }

    private function getDisplayableAttribute(): ?string
    {
        try {
            $validatorReflection = new ReflectionClass($this->validator);

            if ($validatorReflection->hasMethod('getDisplayableAttribute')) {
                $method = $validatorReflection->getMethod('getDisplayableAttribute');
                $method->setAccessible(true);

                $displayable = $method->invoke($this->validator, $this->field);

                if (is_string($displayable) && $displayable !== '') {
                    return $displayable;
                }
            }
        } catch (Throwable) {
            // ignore reflection issues and fall back to basic formatting
        }

        $formatted = str_replace('_', ' ', Str::snake($this->field));

        return $formatted !== '' ? $formatted : null;
    }

    private function determinePreferredMessageKey(array $messages): ?string
    {
        $context = $this->determineMessageContextKey();

        if (isset($messages[$context]) && is_string($messages[$context])) {
            return $context;
        }

        $normalizedMessages = array_change_key_case($messages, CASE_LOWER);

        if (isset($normalizedMessages[$context]) && is_string($normalizedMessages[$context])) {
            return $context;
        }

        return null;
    }

    private function determineMessageContextKey(): string
    {
        if ($this->isNumericField) {
            return 'numeric';
        }

        if ($this->hasRule('array')) {
            return 'array';
        }

        if ($this->hasFileRule()) {
            return 'file';
        }

        return 'string';
    }

    private function hasRule(string $rule): bool
    {
        foreach ($this->rules as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $name = strtolower(strtok($candidate, ':'));

            if ($name === $rule) {
                return true;
            }
        }

        return false;
    }

    private function hasFileRule(): bool
    {
        $fileRules = ['file', 'image', 'mimes', 'mimetypes'];

        foreach ($fileRules as $fileRule) {
            if ($this->hasRule($fileRule)) {
                return true;
            }
        }

        return false;
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

    private function normalizeRequiredIfParameters(): void
    {
        if (! isset($this->parameters[0]) || ! is_string($this->parameters[0])) {
            return;
        }

        $normalized = $this->normalizeDependentField($this->parameters[0]);

        if ($normalized !== '' && $normalized !== $this->parameters[0]) {
            $this->parameters[0] = $normalized;
        }
    }

    private function normalizeDependentField(string $dependentField): string
    {
        $dependentField = trim($dependentField);

        if ($dependentField === '') {
            return '';
        }

        $fieldSegments = array_values(array_filter(
            explode('.', $this->field),
            static fn (string $segment): bool => $segment !== ''
        ));

        $dependentSegments = array_values(array_filter(
            explode('.', $dependentField),
            static fn (string $segment): bool => $segment !== ''
        ));

        if (empty($fieldSegments) || empty($dependentSegments)) {
            return $dependentField;
        }

        $fieldSegmentCount = count($fieldSegments);

        if (count($dependentSegments) <= $fieldSegmentCount) {
            return $dependentField;
        }

        for ($index = 0; $index < $fieldSegmentCount; $index++) {
            if (! isset($dependentSegments[$index]) || $dependentSegments[$index] !== $fieldSegments[$index]) {
                return $dependentField;
            }
        }

        $parentSegments = array_slice($fieldSegments, 0, -1);
        $remainingSegments = array_slice($dependentSegments, $fieldSegmentCount);

        if (empty($remainingSegments)) {
            return $dependentField;
        }

        $normalizedSegments = array_merge($parentSegments, $remainingSegments);

        return implode('.', $normalizedSegments);
    }
}
