<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Factories;

/**
 * Factory for normalizing and processing Laravel validation rules
 *
 * Handles rule normalization, rule object resolution, and enum value extraction
 * to provide a consistent interface for working with validation rules across the application.
 */
class ValidationRuleFactory
{
    /**
     * Normalize a rule to string format
     * Handles strings, arrays, and Laravel rule objects
     *
     * @param  mixed  $rule
     */
    public function normalizeRule($rule): string
    {
        if (is_string($rule)) {
            return $rule;
        }

        if (is_array($rule)) {
            $normalizedRules = [];
            foreach ($rule as $singleRule) {
                if (is_string($singleRule)) {
                    $normalizedRules[] = $singleRule;
                } elseif (is_object($singleRule)) {
                    // Handle Laravel rule objects
                    $normalizedRules[] = $this->resolveRuleObject($singleRule);
                } else {
                    // Skip non-string, non-object rules
                    continue;
                }
            }

            return implode('|', $normalizedRules);
        }

        if (is_object($rule)) {
            return $this->resolveRuleObject($rule);
        }

        // Default to empty string for unhandled types
        return '';
    }

    /**
     * Resolve a Laravel rule object to its string representation
     *
     * @param  object  $rule
     */
    public function resolveRuleObject($rule): string
    {
        // Check if it's a Laravel validation rule object that implements __toString
        if (method_exists($rule, '__toString')) {
            return (string) $rule;
        }

        // Special handling for Enum rule which doesn't have __toString
        if ($rule instanceof \Illuminate\Validation\Rules\Enum) {
            // Try to extract the enum values from the Enum rule
            $enumValues = $this->extractEnumValues($rule);
            if (! empty($enumValues)) {
                // Convert to 'in' rule with the enum values
                return 'in:'.implode(',', $enumValues);
            }

            // Fallback to generic enum rule
            return 'enum';
        }

        // For other rule objects, try to get the class name as a fallback
        $className = get_class($rule);
        $shortName = substr($className, strrpos($className, '\\') + 1);

        return strtolower($shortName);
    }

    /**
     * Extract enum values from an Enum rule object
     */
    public function extractEnumValues(\Illuminate\Validation\Rules\Enum $enumRule): array
    {
        try {
            // Use reflection to access the protected type property
            $reflection = new \ReflectionClass($enumRule);
            $typeProperty = $reflection->getProperty('type');
            $typeProperty->setAccessible(true);
            $enumClass = $typeProperty->getValue($enumRule);

            // Check if there's an 'only' property for filtered values
            if ($reflection->hasProperty('only')) {
                $onlyProperty = $reflection->getProperty('only');
                $onlyProperty->setAccessible(true);
                $onlyValues = $onlyProperty->getValue($enumRule);

                if (! empty($onlyValues)) {
                    // Return the filtered enum values
                    $values = [];
                    foreach ($onlyValues as $enumCase) {
                        $values[] = $enumCase->value ?? $enumCase->name;
                    }

                    return $values;
                }
            }

            // Get all enum cases if it's a valid enum class
            if (enum_exists($enumClass)) {
                $values = [];
                foreach ($enumClass::cases() as $case) {
                    // For backed enums, use the value; for pure enums, use the name
                    $values[] = $case->value ?? $case->name;
                }

                return $values;
            }
        } catch (\Exception $e) {
            // If we can't extract values, return empty array
        }

        return [];
    }
}
