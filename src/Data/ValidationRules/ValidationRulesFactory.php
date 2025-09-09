<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data\ValidationRules;

use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Support\MagicValidationExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Support\SmartTypeInferrer;

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
            // Handle enum types first
            str_starts_with($type, 'enum:'), ! empty($validations['in']) => new EnumValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
                in: $validations['in'] ?? [],
            ),

            // Handle boolean types early (before other checks)
            in_array($type, ['boolean', 'bool']) => new BooleanValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
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

            // Handle array types
            $type === 'array', str_starts_with($type, 'array:') => new ArrayValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
                min: $validations['min'] ?? null,
                max: $validations['max'] ?? null,
                arrayItemValidations: $validations['arrayItemValidations'] ?? null,
            ),

            // Handle email type (only when explicitly email type or has email validation)
            $type === 'email', ($validations['email'] ?? false) === true => new StringValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
                min: $validations['min'] ?? null,
                max: $validations['max'] ?? null,
                email: true,
            ),

            // Handle string types explicitly (when type is 'string')
            $type === 'string' => new StringValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
                min: $validations['min'] ?? null,
                max: $validations['max'] ?? null,
                regex: $validations['regex'] ?? null,
                email: ($validations['email'] ?? false) === true,
                url: $validations['url'] ?? false,
                uuid: $validations['uuid'] ?? false,
                confirmed: $validations['confirmed'] ?? false,
                unique: $validations['unique'] ?? false,
                in: $validations['in'] ?? [],
            ),

            // Default to string validation for everything else
            default => new StringValidationRules(
                required: $required,
                nullable: $nullable,
                customMessages: $customMessages,
                min: $validations['min'] ?? null,
                max: $validations['max'] ?? null,
                regex: $validations['regex'] ?? null,
                email: ($validations['email'] ?? false) === true,
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
    public static function createMagically($rules, ?string $type = null, array $customMessages = []): ValidationRulesInterface
    {
        // Let Laravel do all the parsing work
        $extracted = MagicValidationExtractor::extractViaLaravel($rules);

        // Add custom messages to the extracted data
        if (! empty($customMessages)) {
            $extracted['customMessages'] = $customMessages;
        }

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

    /**
     * Create validation rules from ResolvedValidationSet (backward compatibility)
     */
    public static function createFromResolvedSet(ResolvedValidationSet $resolvedSet): ValidationRulesInterface
    {
        // Convert the resolved set back to our legacy format for backward compatibility
        $validationArray = $resolvedSet->toValidationArray();

        return self::create($resolvedSet->inferredType, $validationArray);
    }
}
