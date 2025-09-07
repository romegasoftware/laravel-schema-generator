<?php

namespace RomegaSoftware\LaravelZodGenerator\Support;

use Illuminate\Support\Str;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator;
use ReflectionClass;

class MagicValidationExtractor
{
    private static ?array $discoveredRules = null;

    /**
     * Auto-discover all Laravel validation methods via reflection
     * This gives us 109+ validation rules automatically!
     */
    public static function discoverLaravelRules(): array
    {
        if (self::$discoveredRules !== null) {
            return self::$discoveredRules;
        }

        $rules = [];
        $reflection = new ReflectionClass(Validator::class);

        foreach ($reflection->getMethods() as $method) {
            $name = $method->getName();
            if (str_starts_with($name, 'validate') && strlen($name) > 8) {
                $ruleName = lcfirst(substr($name, 8));
                $params = array_slice($method->getParameters(), 2); // Skip $attribute, $value

                $rules[$ruleName] = [
                    'hasParams' => count($params) > 1, // Exclude $validator param
                    'isBoolean' => count($params) <= 1,
                ];
            }
        }

        self::$discoveredRules = $rules;

        return $rules;
    }

    /**
     * Use Laravel's actual Validator to parse rules - zero maintenance!
     */
    public static function extractViaLaravel($rules): array
    {
        $translator = new Translator(new ArrayLoader, 'en');
        $validator = new Validator($translator, ['field' => ''], ['field' => $rules]);

        // Access parsed rules via reflection
        $rulesProperty = (new ReflectionClass($validator))->getProperty('rules');
        $rulesProperty->setAccessible(true);
        $parsed = $rulesProperty->getValue($validator)['field'] ?? [];

        return self::transformParsedRules($parsed);
    }

    /**
     * Parse Laravel rules and extract nested/wildcard rules
     */
    public static function extractNestedRules(array $allRules): array
    {
        $parser = new ValidationRuleParser([]);

        // Use Laravel's parser to explode all rules including wildcards
        $exploded = $parser->explode($allRules);
        $nested = [];

        foreach ($exploded->rules as $field => $rules) {
            if (str_contains($field, '.')) {
                $parts = explode('.', $field);
                self::setNestedValue($nested, $parts, self::transformParsedRules($rules));
            }
        }

        return $nested;
    }

    /**
     * Transform Laravel's parsed rules into our format
     */
    private static function transformParsedRules(array $parsed): array
    {
        $discovered = self::discoverLaravelRules();
        $result = [];

        foreach ($parsed as $rule) {
            [$ruleName, $parameters] = ValidationRuleParser::parse($rule);
            $ruleName = Str::snake($ruleName);

            // Auto-categorize based on discovered metadata
            if (isset($discovered[$ruleName])) {
                if ($discovered[$ruleName]['isBoolean']) {
                    $result[$ruleName] = true;
                } elseif (count($parameters) === 1) {
                    $result[$ruleName] = is_numeric($parameters[0]) ? (int) $parameters[0] : $parameters[0];
                } else {
                    $result[$ruleName] = $parameters;
                }
            } else {
                // Handle unknown rules gracefully
                if (empty($parameters)) {
                    $result[$ruleName] = true;
                } elseif (count($parameters) === 1) {
                    $result[$ruleName] = is_numeric($parameters[0]) ? (int) $parameters[0] : $parameters[0];
                } else {
                    $result[$ruleName] = $parameters;
                }
            }
        }

        return $result;
    }

    /**
     * Determine the base type from validation rules using magical inference
     */
    public static function determineType(array $validations): string
    {
        // Direct type rules - Laravel tells us exactly what type it is
        $typeMap = [
            'boolean' => 'boolean',
            'bool' => 'boolean',
            'integer' => 'number',
            'numeric' => 'number',
            'array' => 'array',
            'json' => 'json',
            'date' => 'date',
            'email' => 'email',
            'url' => 'url',
            'uuid' => 'uuid',
            'ulid' => 'ulid',
            'ip' => 'ip',
            'ipv4' => 'ip',
            'ipv6' => 'ip',
            'file' => 'file',
            'image' => 'image',
        ];

        // Check for direct type indicators
        foreach ($typeMap as $rule => $type) {
            if (isset($validations[$rule])) {
                return $type;
            }
        }

        // Check for enum rules (in rule with multiple values)
        if (isset($validations['in']) && is_array($validations['in']) && count($validations['in']) > 1) {
            return 'enum:'.implode(',', $validations['in']);
        }

        // Default to string if no specific type detected
        return 'string';
    }

    /**
     * Set a value in a nested array using dot notation
     */
    private static function setNestedValue(array &$array, array $keys, $value): void
    {
        $key = array_shift($keys);

        if (empty($keys)) {
            $array[$key] = $value;
        } else {
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }
            self::setNestedValue($array[$key], $keys, $value);
        }
    }
}
