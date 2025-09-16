<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

/**
 * Service for grouping and organizing nested validation rules
 *
 * Handles the complex logic of grouping validation rules by their base field names
 * and managing nested array structures for wildcard validation rules.
 */
class NestedRuleGrouper
{
    /**
     * Group validation rules by their base field name to handle nested arrays
     *
     * Example:
     * Input: [
     *   'items' => 'array',
     *   'items.*.variations' => 'array',
     *   'items.*.variations.*.type' => 'required|string',
     *   'items.*.variations.*.size' => 'string',
     *   'items.*.pricing' => 'array',
     *   'items.*.pricing.*.component' => 'required|in:base,tax,discount'
     * ]
     * Output: [
     *   'items' => [
     *     'rules' => 'array',
     *     'nested' => [
     *       'variations' => [
     *         'rules' => 'array',
     *         'nested' => [
     *           'type' => 'required|string',
     *           'size' => 'string'
     *         ]
     *       ],
     *       'pricing' => [
     *         'rules' => 'array',
     *         'nested' => [
     *           'component' => 'required|in:base,tax,discount'
     *         ]
     *       ]
     *     ]
     *   ]
     * ]
     */
    public function groupRulesByBaseField(array $rules): array
    {
        $grouped = [];

        // First pass: handle special nested object markers
        $nestedObjectFields = $this->identifyNestedObjectFields($rules);

        foreach ($rules as $field => $ruleSet) {
            // Skip special marker fields
            if ($this->isSpecialMarkerField($field)) {
                continue;
            }

            if (str_contains($field, '.*')) {
                // Check if this is a nested object within an array
                $parts = explode('.*', $field, 2);
                $baseField = $parts[0];
                $remainingPath = $parts[1] ?? '';

                if ($remainingPath && ! str_contains($remainingPath, '.*')) {
                    // Check if this path corresponds to a nested object
                    $nestedObjectPath = $baseField.'.*.'.explode('.', ltrim($remainingPath, '.'))[0];
                    if (isset($nestedObjectFields[$nestedObjectPath])) {
                        // This is part of a nested object, handle it specially
                        $this->addNestedObjectInArray($grouped, $field, $ruleSet, $rules, $nestedObjectFields);
                        continue;
                    }
                }

                // Regular wildcard field
                $this->addNestedRule($grouped, $field, $ruleSet);
            } else {
                $this->processRegularField($grouped, $field, $ruleSet);
            }
        }

        // Clean up - remove nested array if empty, mark as having nested rules if not
        $this->cleanupGroupedRules($grouped);

        return $grouped;
    }

    /**
     * Identify nested object fields from special markers
     */
    private function identifyNestedObjectFields(array $rules): array
    {
        $nestedObjectFields = [];

        foreach ($rules as $field => $ruleSet) {
            if (str_ends_with($field, '.__isNestedObject') && $ruleSet === 'true') {
                $baseField = substr($field, 0, -17); // Remove .__isNestedObject
                $nestedObjectFields[$baseField] = true;
            }
        }

        return $nestedObjectFields;
    }

    /**
     * Check if a field is a special marker field that should be skipped
     */
    private function isSpecialMarkerField(string $field): bool
    {
        return str_contains($field, '.__isNestedObject') ||
               str_contains($field, '.__baseRules') ||
               str_contains($field, '.__nested.');
    }


    /**
     * Process a regular field (no wildcards)
     */
    private function processRegularField(array &$grouped, string $field, string $ruleSet): void
    {
        if (! isset($grouped[$field])) {
            $grouped[$field] = [
                'rules' => $ruleSet,
                'nested' => [],
            ];
        } else {
            // Field already exists from wildcard processing, add base rules
            $grouped[$field]['rules'] = $ruleSet;
        }
    }

    /**
     * Add a nested object that's within an array context
     */
    public function addNestedObjectInArray(
        array &$grouped,
        string $field,
        string $ruleSet,
        array $allRules,
        array $nestedObjectFields
    ): void {
        // Extract the parts: baseArray.*.objectField.property
        if (preg_match('/^(.+?)\.\*\.([^.]+)(?:\.(.+))?$/', $field, $matches)) {
            $baseArray = $matches[1];
            $objectField = $matches[2];
            $propertyPath = $matches[3] ?? null;

            // Initialize structure
            if (! isset($grouped[$baseArray])) {
                $grouped[$baseArray] = [
                    'rules' => null,
                    'nested' => [],
                ];
            }

            $nestedObjectPath = $baseArray.'.*.'.$objectField;
            if (isset($nestedObjectFields[$nestedObjectPath])) {
                // This is a nested object
                if (! isset($grouped[$baseArray]['nested'][$objectField])) {
                    $grouped[$baseArray]['nested'][$objectField] = [
                        'rules' => $allRules[$nestedObjectPath.'.__baseRules'] ?? 'object',
                        'nested' => [],
                        'isNestedObject' => true,
                    ];
                }

                if ($propertyPath) {
                    // Add the property to the nested object
                    $grouped[$baseArray]['nested'][$objectField]['nested'][$propertyPath] =
                        $allRules[$nestedObjectPath.'.__nested.'.$propertyPath] ?? $ruleSet;
                }
            } else {
                // Regular nested field
                $this->addNestedRule($grouped, $field, $ruleSet);
            }
        }
    }

    /**
     * Recursively add nested rules to the grouped structure
     * Handles multi-level wildcards like items.*.variations.*.type
     */
    public function addNestedRule(array &$grouped, string $field, string $ruleSet): void
    {
        // Split on the first .* occurrence only
        $parts = explode('.*', $field, 2);
        $baseField = $parts[0];
        $remainingPath = $parts[1] ?? '';

        // Initialize base field if not exists
        if (! isset($grouped[$baseField])) {
            $grouped[$baseField] = [
                'rules' => null,
                'nested' => [],
            ];
        }

        if ($remainingPath === '') {
            // Direct array items (e.g., tags.*)
            $grouped[$baseField]['nested']['*'] = $ruleSet;
        } else {
            // Remove leading dot and process remaining path
            $remainingPath = ltrim($remainingPath, '.');

            if (str_contains($remainingPath, '.*')) {
                // Still has wildcards - need to handle nested arrays recursively
                $this->addNestedRuleRecursively($grouped[$baseField]['nested'], $remainingPath, $ruleSet);
            } else {
                // No more wildcards - this is a simple nested property
                // But we need to make sure we don't overwrite existing structured data
                if (! isset($grouped[$baseField]['nested'][$remainingPath])) {
                    $grouped[$baseField]['nested'][$remainingPath] = $ruleSet;
                } elseif (is_array($grouped[$baseField]['nested'][$remainingPath]) &&
                         isset($grouped[$baseField]['nested'][$remainingPath]['rules'])) {
                    // Already a structured field, update its rules
                    $grouped[$baseField]['nested'][$remainingPath]['rules'] = $ruleSet;
                } else {
                    // Simple field, just update it
                    $grouped[$baseField]['nested'][$remainingPath] = $ruleSet;
                }
            }
        }
    }

    /**
     * Recursively handle nested array structures within the nested rules
     */
    public function addNestedRuleRecursively(array &$nested, string $path, string $ruleSet): void
    {
        if (str_contains($path, '.*')) {
            // Split on the first .* occurrence in the remaining path
            $parts = explode('.*', $path, 2);
            $currentField = $parts[0];
            $remainingPath = $parts[1] ?? '';

            // Initialize or convert nested field structure
            if (! isset($nested[$currentField])) {
                $nested[$currentField] = [
                    'rules' => null,
                    'nested' => [],
                ];
            } elseif (is_string($nested[$currentField])) {
                // Convert existing string rule to structured format
                $existingRule = $nested[$currentField];
                $nested[$currentField] = [
                    'rules' => $existingRule,
                    'nested' => [],
                ];
            }

            if ($remainingPath === '') {
                // Direct array items at this level
                $nested[$currentField]['nested']['*'] = $ruleSet;
            } else {
                // Remove leading dot and continue recursively
                $remainingPath = ltrim($remainingPath, '.');

                if (str_contains($remainingPath, '.*')) {
                    // More wildcards ahead - recurse deeper
                    $this->addNestedRuleRecursively($nested[$currentField]['nested'], $remainingPath, $ruleSet);
                } else {
                    // Final property - add it as a simple rule
                    $nested[$currentField]['nested'][$remainingPath] = $ruleSet;
                }
            }
        } else {
            // No wildcards in the remaining path - simple assignment
            $nested[$path] = $ruleSet;
        }
    }

    /**
     * Clean up grouped rules by removing empty nested arrays and marking fields with nested rules
     */
    public function cleanupGroupedRules(array &$grouped): void
    {
        foreach ($grouped as $field => &$fieldData) {
            if (isset($fieldData['nested']) && empty($fieldData['nested'])) {
                // Remove empty nested array
                unset($fieldData['nested']);
            } elseif (isset($fieldData['nested']) && ! empty($fieldData['nested'])) {
                // Mark as having nested rules for further processing
                $fieldData['hasNestedRules'] = true;
            }
        }
    }
}
