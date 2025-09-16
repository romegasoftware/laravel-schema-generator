<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Factories;

use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

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
     */
    public function normalizeRule(mixed $rule): string
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
                    $resolved = $this->resolveRuleObject($singleRule);

                    // Special handling for Password rules that may return arrays
                    if ($singleRule instanceof \Illuminate\Validation\Rules\Password) {
                        $expandedRules = $this->expandPasswordRule($singleRule);
                        if (! empty($expandedRules)) {
                            $normalizedRules = array_merge($normalizedRules, $expandedRules);

                            continue;
                        }
                    }

                    $normalizedRules[] = $resolved;
                } else {
                    // Skip non-string, non-object rules
                    continue;
                }
            }

            return implode('|', $normalizedRules);
        }

        if (is_object($rule)) {
            // Special handling for Password rules
            if ($rule instanceof Password) {
                $expandedRules = $this->expandPasswordRule($rule);
                if (! empty($expandedRules)) {
                    return implode('|', $expandedRules);
                }
            }

            return $this->resolveRuleObject($rule);
        }

        // Default to empty string for unhandled types
        return '';
    }

    /**
     * Resolve a Laravel rule object to its string representation
     */
    public function resolveRuleObject(object $rule): string
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
    public function extractEnumValues(Enum $enumRule): array
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

    /**
     * Expand a Password rule object into individual validation rules
     */
    public function expandPasswordRule(Password $passwordRule): array
    {
        $rules = [];

        try {
            // Use reflection to access protected properties
            $reflection = new \ReflectionClass($passwordRule);

            // Add base password rule
            $rules[] = 'password';

            // Check for min length
            if ($reflection->hasProperty('min')) {
                $minProperty = $reflection->getProperty('min');
                $minProperty->setAccessible(true);
                $minValue = $minProperty->getValue($passwordRule);
                if ($minValue !== null) {
                    $rules[] = "min:$minValue";
                }
            }

            // Check for max length
            if ($reflection->hasProperty('max')) {
                $maxProperty = $reflection->getProperty('max');
                $maxProperty->setAccessible(true);
                $maxValue = $maxProperty->getValue($passwordRule);
                if ($maxValue !== null) {
                    $rules[] = "max:$maxValue";
                }
            }

            // Check for letters requirement
            if ($reflection->hasProperty('letters')) {
                $lettersProperty = $reflection->getProperty('letters');
                $lettersProperty->setAccessible(true);
                $lettersValue = $lettersProperty->getValue($passwordRule);
                if ($lettersValue) {
                    $rules[] = "password.letters:$lettersValue";
                }
            }

            if ($reflection->hasProperty('mixedCase')) {
                $mixedCaseProperty = $reflection->getProperty('mixedCase');
                $mixedCaseProperty->setAccessible(true);
                $mixedCaseValue = $mixedCaseProperty->getValue($passwordRule);
                if ($mixedCaseValue) {
                    $rules[] = "password.mixed:$mixedCaseValue";
                }
            }

            if ($reflection->hasProperty('numbers')) {
                $numbersProperty = $reflection->getProperty('numbers');
                $numbersProperty->setAccessible(true);
                $numbersValue = $numbersProperty->getValue($passwordRule);
                if ($numbersValue) {
                    $rules[] = "password.numbers:$numbersValue";
                }
            }

            if ($reflection->hasProperty('symbols')) {
                $symbolsProperty = $reflection->getProperty('symbols');
                $symbolsProperty->setAccessible(true);
                $symbolsValue = $symbolsProperty->getValue($passwordRule);
                if ($symbolsValue) {
                    $rules[] = "password.symbols:$symbolsValue";
                }
            }

            if ($reflection->hasProperty('uncompromised')) {
                $uncompromisedProperty = $reflection->getProperty('uncompromised');
                $uncompromisedProperty->setAccessible(true);
                $uncompromisedValue = $uncompromisedProperty->getValue($passwordRule);
                if ($uncompromisedValue) {
                    $rules[] = "password.uncompromised:$uncompromisedValue";
                }
            }

        } catch (\Exception $e) {
            // If we can't extract the rules, return the basic password rule
            return ['password'];
        }

        return ! empty($rules) ? $rules : ['password'];
    }
}
