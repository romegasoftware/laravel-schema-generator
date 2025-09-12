<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator;
use ReflectionClass;

/**
 * Service for inferring types from Laravel validation rules
 *
 * Dynamically extracts and uses Laravel's built-in validation rule
 * categorizations via reflection to stay in sync with the framework.
 */
class TypeInferenceService
{
    /**
     * Cached Laravel rule categories extracted via reflection
     */
    private static ?array $laravelRuleCategories = null;

    /**
     * Extract all Laravel rule categories via reflection
     * This is done once and cached for performance
     */
    private function extractLaravelRuleCategories(): array
    {
        if (self::$laravelRuleCategories === null) {
            $reflection = new ReflectionClass(Validator::class);

            // Create a dummy validator instance to access the properties
            $validator = $reflection->newInstanceWithoutConstructor();

            // Extract all rule categories at once
            $categories = [];

            // Extract numeric rules
            $property = $reflection->getProperty('numericRules');
            $property->setAccessible(true);
            $categories['numeric'] = $property->getValue($validator);

            // Extract file rules
            $property = $reflection->getProperty('fileRules');
            $property->setAccessible(true);
            $categories['file'] = $property->getValue($validator);

            // Extract size rules
            $property = $reflection->getProperty('sizeRules');
            $property->setAccessible(true);
            $categories['size'] = $property->getValue($validator);

            // Extract implicit rules if needed in future
            $property = $reflection->getProperty('implicitRules');
            $property->setAccessible(true);
            $categories['implicit'] = $property->getValue($validator);

            self::$laravelRuleCategories = $categories;
        }

        return self::$laravelRuleCategories;
    }

    /**
     * Get Laravel's numeric rules via reflection
     */
    private function getNumericRules(): array
    {
        return $this->extractLaravelRuleCategories()['numeric'];
    }

    /**
     * Get Laravel's file rules via reflection
     */
    private function getFileRules(): array
    {
        return $this->extractLaravelRuleCategories()['file'];
    }

    /**
     * Infer type from validation rules
     */
    public function inferType(array $validations): string
    {
        // First, normalize the rule names to match Laravel's internal format
        $normalizedRules = $this->normalizeRuleNames($validations);

        // Check for boolean type
        if (isset($normalizedRules['Boolean']) || isset($normalizedRules['Bool'])) {
            return 'boolean';
        }

        // Check for numeric types using Laravel's actual categorization
        $numericRules = $this->getNumericRules();
        foreach ($numericRules as $numericRule) {
            if (isset($normalizedRules[$numericRule])) {
                return 'number';
            }
        }

        // Additional numeric rules that Laravel treats as numeric
        if (isset($normalizedRules['Digits']) ||
            isset($normalizedRules['DigitsBetween'])) {
            return 'number';
        }

        // Check for array type
        if (isset($normalizedRules['Array']) || isset($normalizedRules['List'])) {
            return 'array';
        }

        // Check for specific string subtypes
        if (isset($normalizedRules['Email'])) {
            return 'email';
        }

        if (isset($normalizedRules['Url'])) {
            return 'url';
        }

        if (isset($normalizedRules['Uuid'])) {
            return 'uuid';
        }

        if (isset($normalizedRules['Json'])) {
            return 'string'; // JSON as string
        }

        // Check for date types (Laravel treats these as strings in validation)
        if (isset($normalizedRules['Date']) ||
            isset($normalizedRules['DateFormat']) ||
            isset($normalizedRules['DateEquals']) ||
            isset($normalizedRules['Before']) ||
            isset($normalizedRules['After'])) {
            return 'string'; // Dates as strings in TypeScript
        }

        // Check for file types - both from normalized rules and Laravel's file rules
        if (isset($normalizedRules['File']) || isset($normalizedRules['Image'])) {
            return 'file';
        }

        // Also check using Laravel's actual file rules
        $fileRules = $this->getFileRules();
        foreach ($fileRules as $fileRule) {
            if (isset($normalizedRules[$fileRule])) {
                // File and Image rules indicate a file type
                if ($fileRule === 'File' || $fileRule === 'Image') {
                    return 'file';
                }
                // Size rules like Min, Max, etc. don't determine type by themselves
                break;
            }
        }

        // Also check for other common file-related rules
        if (isset($normalizedRules['Mimes']) ||
            isset($normalizedRules['Mimetypes']) ||
            isset($normalizedRules['Extensions']) ||
            isset($normalizedRules['Dimensions'])) {
            return 'file';
        }

        // Check for enum (in rule with values)
        if (isset($normalizedRules['In'])) {
            // Check if we have the parameters from the original validations
            if (isset($validations['in']) && is_array($validations['in'])) {
                // Even a single value can be an enum
                if (count($validations['in']) >= 1) {
                    return 'enum:'.implode(',', $validations['in']);
                }
            }
            // Also check the normalized rule value in case it's stored there
            elseif (is_array($normalizedRules['In']) && count($normalizedRules['In']) >= 1) {
                return 'enum:'.implode(',', $normalizedRules['In']);
            }
        }

        // Check if field name indicates array (for edge cases)
        if (isset($validations['fieldName']) && str_ends_with($validations['fieldName'], '*')) {
            return 'array';
        }

        // Default to string if no specific type detected
        return 'string';
    }

    /**
     * Normalize rule names to Laravel's internal studly case format
     * This matches how Laravel's ValidationRuleParser::normalizeRule works
     */
    private function normalizeRuleNames(array $validations): array
    {
        $normalized = [];

        foreach ($validations as $rule => $value) {
            // Convert to studly case as Laravel does
            $studlyRule = Str::studly($rule);

            // Apply Laravel's normalization rules
            $studlyRule = match ($studlyRule) {
                'Int' => 'Integer',
                'Bool' => 'Boolean',
                default => $studlyRule,
            };

            $normalized[$studlyRule] = $value;
        }

        return $normalized;
    }

    /**
     * Check if a field is numeric based on its validation rules
     * Dynamically uses Laravel's numeric rules categorization
     */
    public function isNumericField(array $rules): bool
    {
        $numericRules = $this->getNumericRules();

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                [$ruleName] = ValidationRuleParser::parse($rule);

                // Normalize the rule name as Laravel does
                $normalizedRule = match ($ruleName) {
                    'Int' => 'Integer',
                    'Bool' => 'Boolean',
                    default => $ruleName,
                };

                // Check against Laravel's actual numeric rules
                if (in_array($normalizedRule, $numericRules)) {
                    return true;
                }

                // Also check for digits rules which are numeric
                if (in_array($normalizedRule, ['Digits', 'DigitsBetween'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if validation rules indicate the field should be nullable/optional
     */
    public function inferNullability(array $validations): array
    {
        $isOptional = false;
        $isNullable = false;

        // Check for nullable indicators
        if (isset($validations['nullable']) && $validations['nullable'] === true) {
            $isNullable = true;
        }

        // Check for optional indicators (absence of required rule means optional)
        if (! isset($validations['required']) || $validations['required'] !== true) {
            $isOptional = true;
        }

        // Sometimes fields might not be required
        if (isset($validations['sometimes']) && $validations['sometimes'] === true) {
            $isOptional = true;
        }

        return [
            'optional' => $isOptional,
            'nullable' => $isNullable,
        ];
    }
}
