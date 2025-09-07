<?php

namespace RomegaSoftware\LaravelZodGenerator\Data\ValidationRules;

use RomegaSoftware\LaravelZodGenerator\Support\MagicValidationExtractor;
use RomegaSoftware\LaravelZodGenerator\Support\SmartTypeInferrer;

class ValidationRulesFactory
{
    /**
     * Create appropriate validation rules based on type and validation array
     */
    public static function create(string $type, array $validations): ValidationRulesInterface
    {
        // Extract common properties
        $required = $validations['required'] ?? false;
        $nullable = $validations['nullable'] ?? false;
        $customMessages = $validations['customMessages'] ?? [];

        return match (true) {
            // Handle enum types
            str_starts_with($type, 'enum:'), ! empty($validations['in']) => new EnumValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
                in: $validations['in'] ?? [],
            ),

            // Handle email type (can be string or dedicated email type)
            $type === 'email', $validations['email'] ?? false => new StringValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
                min: $validations['min'] ?? null,
                max: $validations['max'] ?? null,
                email: true,
            ),

            // Handle number types
            in_array($type, ['number', 'integer', 'int', 'float', 'double']) => new NumberValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
                min: $validations['min'] ?? null,
                max: $validations['max'] ?? null,
                positive: $validations['positive'] ?? false,
                negative: $validations['negative'] ?? false,
                finite: $validations['finite'] ?? false,
                gte: $validations['gte'] ?? null,
                lte: $validations['lte'] ?? null,
            ),

            // Handle boolean types
            in_array($type, ['boolean', 'bool']) => new BooleanValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
            ),

            // Handle array types
            $type === 'array', str_starts_with($type, 'array:') => new ArrayValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
                min: $validations['min'] ?? null,
                max: $validations['max'] ?? null,
                arrayItemValidations: $validations['arrayItemValidations'] ?? null,
            ),

            // Default to string validation for everything else
            default => new StringValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
                min: $validations['min'] ?? null,
                max: $validations['max'] ?? null,
                regex: $validations['regex'] ?? null,
                email: $validations['email'] ?? false,
                url: $validations['url'] ?? false,
                uuid: $validations['uuid'] ?? false,
                confirmed: $validations['confirmed'] ?? false,
                unique: $validations['unique'] ?? false,
                in: $validations['in'] ?? [],
            ),
        };
    }

    /**
     * Create validation rules from any Laravel rules format - zero configuration needed!
     * This method uses Laravel's own validation parser and behavioral type detection.
     */
    public static function createMagically($rules, ?string $type = null): ValidationRulesInterface
    {
        // Let Laravel do all the parsing work
        $extracted = MagicValidationExtractor::extractViaLaravel($rules);

        // Auto-detect type if not provided
        if (! $type) {
            $type = SmartTypeInferrer::inferTypeEnhanced($rules, $extracted);
        }

        // Use existing factory logic but with auto-extracted data
        return self::create($type, $extracted);
    }

    /**
     * Parse nested rules using Laravel's parser and return structured data
     */
    public static function parseNestedRulesMagically(array $allRules): array
    {
        return MagicValidationExtractor::extractNestedRules($allRules);
    }
}
