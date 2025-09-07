<?php

namespace RomegaSoftware\LaravelZodGenerator\Support;

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

        // Test values that should pass for different types
        $typeTests = [
            'boolean' => [true, false],
            'number' => [123, 0, -1, 3.14],
            'array' => [['a'], [], ['key' => 'value']],
            'string' => ['test', 'hello world', ''],
            'email' => ['test@example.com', 'user@domain.org'],
            'url' => ['https://example.com', 'http://test.org'],
            'json' => ['{"key":"value"}', '[]', '{}'],
            'date' => ['2023-01-01', '2023-12-31 23:59:59'],
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

        // Return the type with the highest pass rate
        if (! empty($typeScores)) {
            arsort($typeScores);
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

        // If we got a specific type from rules, trust it
        if ($ruleBasedType !== 'string') {
            return $ruleBasedType;
        }

        // For ambiguous cases, use behavioral testing (slower but more accurate)
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
