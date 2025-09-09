<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Support;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Validator;

class SmartTypeInferrer
{
    /**
     * Infer type by testing actual Laravel validation behavior
     * This is the most reliable way to determine what Laravel thinks the type should be
     */
    public static function inferTypeByBehavior($rules): string
    {
        $translator = new Translator(new ArrayLoader, 'en');

        // Test values that should pass for different types - ordered by specificity
        $typeTests = [
            'boolean' => [true, false, 1, 0, '1', '0'],
            'email' => ['test@example.com', 'user@domain.org'],
            'url' => ['https://example.com', 'http://test.org'],
            'number' => [123, 0, -1, 3.14, '42', '3.14'],
            'array' => [['a'], [], ['key' => 'value']],
            'json' => ['{"key":"value"}', '[]', '{}'],
            'date' => ['2023-01-01', '2023-12-31 23:59:59'],
            'string' => ['test', 'hello world', '', 'not-an-email', 'simple-text'],
        ];

        $typeScores = [];

        foreach ($typeTests as $type => $testValues) {
            $passed = 0;
            $total = count($testValues);

            foreach ($testValues as $value) {
                $validator = new Validator($translator, ['field' => $value], ['field' => $rules]);
                if ($validator->passes()) {
                    $passed++;
                }
            }

            // Score based on pass rate
            if ($total > 0) {
                $typeScores[$type] = $passed / $total;
            }
        }

        // Return the type with the highest pass rate, but prefer more specific types
        if (! empty($typeScores)) {
            arsort($typeScores);

            // Get the best score
            $bestScore = reset($typeScores);

            // If we have perfect or near-perfect matches, prefer the most specific one
            if ($bestScore >= 0.8) {
                $perfectMatches = array_filter($typeScores, fn ($score) => $score >= 0.8);

                // Prefer specific types over general ones
                $typePreference = ['boolean', 'email', 'url', 'number', 'array', 'json', 'date', 'string'];
                foreach ($typePreference as $preferredType) {
                    if (isset($perfectMatches[$preferredType])) {
                        return $preferredType;
                    }
                }
            }

            $bestType = array_key_first($typeScores);

            // Only return if we have a good confidence (> 50% pass rate)
            if ($typeScores[$bestType] > 0.5) {
                return $bestType;
            }
        }

        // Fallback to string if behavioral detection is inconclusive
        return 'string';
    }

    /**
     * Enhanced type inference that combines rule analysis with behavioral testing
     */
    public static function inferTypeEnhanced($rules, array $validations = []): string
    {
        // First, try rule-based detection (fast)
        $ruleBasedType = MagicValidationExtractor::determineType($validations);

        // If we got a specific type from rules, trust it completely
        // Rule-based detection is more reliable than behavioral testing
        if ($ruleBasedType !== 'string') {
            return $ruleBasedType;
        }

        // If rule-based detection found 'string' explicitly, trust that
        if (isset($validations['string']) && $validations['string'] === true) {
            return 'string';
        }

        // For ambiguous cases where no explicit type was found, use behavioral testing
        return self::inferTypeByBehavior($rules);
    }

    /**
     * Check if validation rules indicate the field should be nullable/optional
     */
    public static function inferNullability(array $validations): array
    {
        return [
            'nullable' => isset($validations['nullable']) && $validations['nullable'] === true,
            'required' => isset($validations['required']) && $validations['required'] === true,
            'optional' => ! isset($validations['required']) || $validations['required'] !== true,
        ];
    }

    /**
     * Detect if this is an enum-like rule (in rule with multiple values)
     */
    public static function isEnumType(array $validations): bool
    {
        return isset($validations['in'])
            && is_array($validations['in'])
            && count($validations['in']) > 1;
    }

    /**
     * Get enum values if this is an enum type
     */
    public static function getEnumValues(array $validations): array
    {
        if (self::isEnumType($validations)) {
            return $validations['in'];
        }

        return [];
    }
}
