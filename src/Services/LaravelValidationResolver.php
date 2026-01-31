<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Traits\Makeable;

/**
 * Simplified Laravel validation resolver with single responsibility:
 * Transform Laravel rules to structured ResolvedValidation format
 */
class LaravelValidationResolver
{
    use Makeable;

    public function __construct(
        private MessageResolutionService $messageService = new MessageResolutionService,
        private TypeInferenceService $typeInferenceService = new TypeInferenceService
    ) {}

    /**
     * Resolve Laravel validation rules into structured format
     */
    public function resolve(string $field, string $rules, Validator $validator): ResolvedValidationSet
    {
        // Handle empty rules
        if (empty($rules)) {
            return ResolvedValidationSet::make($field, [], 'string', null);
        }

        $originalData = $validator->getData();
        $preparedData = $this->prepareDataForConditionalRules($rules, $originalData);
        $dataWasModified = $preparedData !== $originalData;

        if ($dataWasModified) {
            $validator->setData($preparedData);
        }

        $currentData = $validator->getData();

        // Pre-split rules respecting regex patterns to avoid breaking on pipe characters
        // inside regex alternation patterns like (0[1-9]|1[0-2])
        $preSplitRules = $this->splitRulesRespectingRegex($rules);

        // Pass pre-split rules as an array to avoid Laravel's naive pipe splitting
        $explodedRules = (new ValidationRuleParser($currentData))
            ->explode(ValidationRuleParser::filterConditionalRules([$preSplitRules], $currentData));

        // Convert rules to ResolvedValidation objects
        $resolvedValidations = $this->resolveValidationRules($explodedRules->rules[0], $field, $validator);

        // Infer type from validation rules - use the first rule set
        // Convert array of rule strings to associative array for inferType
        $rulesForInference = [];
        foreach ($explodedRules->rules[0] as $rule) {
            [$ruleName, $parameters] = ValidationRuleParser::parse($rule);
            $rulesForInference[strtolower($ruleName)] = $parameters ?: true;
        }
        $inferredType = $this->typeInferenceService->inferType($rulesForInference);

        // Check if this is a wildcard field and resolve nested validations if needed
        $nestedValidations = null;
        if ($this->isWildcardField($field)) {
            $nestedValidations = $this->resolveWildcardField($field, $explodedRules->rules[0], $validator);
        }

        $resolvedSet = ResolvedValidationSet::make($field, $resolvedValidations, $inferredType, $nestedValidations);

        if ($dataWasModified) {
            $validator->setData($originalData);
        }

        return $resolvedSet;
    }

    /**
     * Check if a field is a wildcard field (contains .*)
     */
    private function isWildcardField(string $field): bool
    {
        return str_contains($field, '.*');
    }

    /**
     * Resolve wildcard field rules into nested validation structure
     */
    private function resolveWildcardField(string $field, array $rules, Validator $validator): ResolvedValidationSet
    {
        // Keep the original wildcard notation so downstream consumers maintain context
        $resolvedValidations = $this->resolveValidationRules($rules, $field, $validator);

        // Infer type for the nested item
        $rulesForInference = [];
        foreach ($rules as $rule) {
            [$ruleName, $parameters] = ValidationRuleParser::parse($rule);
            $rulesForInference[strtolower($ruleName)] = $parameters ?: true;
        }
        $inferredType = $this->typeInferenceService->inferType($rulesForInference);

        // Check for further nesting and handle recursively
        $nestedValidations = null;
        if ($this->hasDeepernesting($field)) {
            $nestedValidations = $this->resolveDeepNestedField($field, $rules, $validator);
        }

        return ResolvedValidationSet::make($field, $resolvedValidations, $inferredType, $nestedValidations);
    }

    /**
     * Check if the field has deeper nesting beyond one level
     */
    private function hasDeepernesting(string $field): bool
    {
        // Count the number of .* occurrences
        return substr_count($field, '.*') > 1;
    }

    /**
     * Handle deep nested field resolution recursively
     * Creates a chain of nested validations for multi-level wildcards like "items.*.variants.*"
     */
    private function resolveDeepNestedField(string $field, array $rules, Validator $validator): ?ResolvedValidationSet
    {
        // Find the position of the second .* occurrence
        $firstWildcard = strpos($field, '.*');
        if ($firstWildcard === false) {
            return null;
        }

        $secondWildcard = strpos($field, '.*', $firstWildcard + 2);
        if ($secondWildcard === false) {
            return null; // No second wildcard found
        }

        // Extract the nested field (everything after the first .*)
        $nestedField = substr($field, $firstWildcard + 2);

        // If the nested field still contains wildcards, process it recursively
        if ($this->isWildcardField($nestedField)) {
            return $this->resolveWildcardField($nestedField, $rules, $validator);
        }

        // If no more wildcards, this shouldn't happen with proper deep nesting detection
        return null;
    }

    /**
     * Convert individual validation rules to ResolvedValidation objects
     */
    private function resolveValidationRules(array $rules, string $field, Validator $validator): array
    {
        $resolvedValidations = [];

        // Determine if this field is numeric based on validation rules
        $isNumericField = $this->typeInferenceService->isNumericField($rules);

        foreach ($rules as $rule) {
            [$ruleName, $parameters] = ValidationRuleParser::parse($rule);

            // Resolve the message using the message service
            $message = $this->messageService->with(
                field: $field,
                ruleName: $ruleName,
                validator: $validator,
                parameters: $parameters,
                isNumericField: $isNumericField,
                rules: $rules,
            )->resolveCustomMessage();

            $resolvedValidation = new ResolvedValidation(
                rule: $ruleName,
                parameters: $parameters,
                message: $message,
                isRequired: false,
                isNullable: false
            );

            $normalizedRuleName = strtolower($ruleName);

            switch ($normalizedRuleName) {
                case 'required':
                case 'present':
                    $resolvedValidation->isRequired = true;
                    break;
                case 'nullable':
                    $resolvedValidation->isNullable = true;
                    break;
            }
            $resolvedValidations[] = $resolvedValidation;
        }

        return $resolvedValidations;
    }

    private function prepareDataForConditionalRules(string $rules, array $data): array
    {
        if ($rules === '') {
            return $data;
        }

        $segments = $this->splitRulesRespectingRegex($rules);

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            [$ruleName, $parameters] = ValidationRuleParser::parse($segment);

            if (strcasecmp($ruleName, 'RequiredIf') === 0 && isset($parameters[0], $parameters[1])) {
                $dependentField = $parameters[0];

                if ($dependentField === '' || Arr::has($data, $dependentField)) {
                    continue;
                }

                Arr::set($data, $dependentField, $parameters[1]);
            }
        }

        return $data;
    }

    /**
     * Split rules by pipe character while respecting regex patterns.
     * Regex patterns may contain pipe characters for alternation (e.g., (0[1-9]|1[0-2]))
     * which should not be treated as rule separators.
     */
    private function splitRulesRespectingRegex(string $rules): array
    {
        $result = [];
        $current = '';
        $inRegex = false;
        $regexDelimiter = null;
        $escaped = false;

        $length = strlen($rules);
        $i = 0;

        while ($i < $length) {
            $char = $rules[$i];

            // Check if we're starting a regex rule
            if (! $inRegex) {
                $isRegex = substr($rules, $i, 6) === 'regex:';
                $isNotRegex = substr($rules, $i, 10) === 'not_regex:';

                if ($isRegex || $isNotRegex) {
                    $colonPos = strpos($rules, ':', $i);
                    $current .= substr($rules, $i, $colonPos - $i + 1);
                    $i = $colonPos + 1;

                    if ($i < $length) {
                        $regexDelimiter = $rules[$i];
                        $current .= $regexDelimiter;
                        $inRegex = true;
                        $i++;
                    }

                    continue;
                }
            }

            if ($inRegex) {
                if ($escaped) {
                    $current .= $char;
                    $escaped = false;
                } elseif ($char === '\\') {
                    $current .= $char;
                    $escaped = true;
                } elseif ($char === $regexDelimiter) {
                    $current .= $char;
                    $inRegex = false;
                    $regexDelimiter = null;
                } else {
                    $current .= $char;
                }
            } else {
                if ($char === '|') {
                    $result[] = $current;
                    $current = '';
                } else {
                    $current .= $char;
                }
            }

            $i++;
        }

        if ($current !== '') {
            $result[] = $current;
        }

        return $result;
    }
}
