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
     *
     * @param  array  $rules  The validation rules to group
     * @param  array  $metadata  Optional metadata for enhanced nested object detection
     */
    public function groupRulesByBaseField(array $rules, array $metadata = []): array
    {
        $grouped = [];

        // First pass: handle special nested object markers
        $nestedObjectFields = $this->identifyNestedObjectFields($rules, $metadata);

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
                $this->addNestedRule($grouped, $field, $ruleSet, false, $metadata);
            } else {
                $this->processRegularField($grouped, $field, $ruleSet);
            }
        }

        // Clean up - remove nested array if empty, mark as having nested rules if not
        $this->cleanupGroupedRules($grouped);

        return $grouped;
    }

    /**
     * Identify nested object fields from special markers and metadata
     */
    private function identifyNestedObjectFields(array $rules, array $metadata = []): array
    {
        $nestedObjectFields = [];

        // From special markers in rules
        foreach ($rules as $field => $ruleSet) {
            if (str_ends_with($field, '.__isNestedObject') && $ruleSet === 'true') {
                $baseField = substr($field, 0, -17); // Remove .__isNestedObject
                $nestedObjectFields[$baseField] = true;
            } elseif (str_ends_with($field, '.__type') && $ruleSet === 'nested_object') {
                $baseField = substr($field, 0, -7); // Remove .__type
                $nestedObjectFields[$baseField] = true;
            }
        }

        // From metadata (for non-array contexts)
        if (! empty($metadata)) {
            foreach ($metadata as $meta) {
                // Check if metadata is a FieldMetadata object with isNestedDataObject method
                if (is_object($meta) && method_exists($meta, 'isNestedDataObject') && $meta->isNestedDataObject()) {
                    // Only add as nested object field if it's not within an array context
                    if (! str_contains($meta->fieldName, '.*')) {
                        $nestedObjectFields[$meta->fieldName] = true;
                        if (property_exists($meta, 'mappedName') && $meta->mappedName) {
                            // Also check if the rule exists for the mapped name
                            $mappedFieldName = str_replace($meta->propertyName, $meta->mappedName, $meta->fieldName);
                            if (isset($rules[$mappedFieldName])) {
                                $nestedObjectFields[$mappedFieldName] = true;
                            }
                        }
                    }
                }
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
               str_contains($field, '.__nested.') ||
               str_contains($field, '.__type') ||
               str_contains($field, '.__class');
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
     *
     * @param  array  &$grouped  The grouped rules array to modify
     * @param  string  $field  The field path with wildcards
     * @param  string  $ruleSet  The validation rules for this field
     * @param  bool  $isNestedObjectInArray  Whether this field is a nested object within an array
     * @param  array  $metadata  Optional metadata for enhanced nested object detection
     */
    public function addNestedRule(array &$grouped, string $field, string $ruleSet, bool $isNestedObjectInArray = false, array $metadata = []): void
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
                // No more wildcards - check if this is part of a nested object
                if (! empty($metadata) && str_contains($remainingPath, '.')) {
                    $pathParts = explode('.', $remainingPath, 2);
                    $potentialNestedObject = $pathParts[0];
                    $nestedProperty = $pathParts[1];

                    // Check metadata for nested object information
                    $isNestedObjectProperty = false;
                    foreach ($metadata as $meta) {
                        if (is_object($meta) &&
                            method_exists($meta, 'isNestedDataObject') &&
                            $meta->isNestedDataObject() &&
                            property_exists($meta, 'fieldName') &&
                            str_contains($meta->fieldName, '.*') &&
                            (str_ends_with($meta->fieldName, $potentialNestedObject) ||
                             (property_exists($meta, 'mappedName') && $meta->mappedName && $meta->mappedName === $potentialNestedObject))) {
                            $isNestedObjectProperty = true;
                            break;
                        }
                    }

                    if ($isNestedObjectProperty) {
                        // Initialize the nested object structure
                        if (! isset($grouped[$baseField]['nested'][$potentialNestedObject])) {
                            $grouped[$baseField]['nested'][$potentialNestedObject] = [
                                'rules' => null,
                                'nested' => [],
                                'isNestedObject' => true,
                            ];
                        }
                        // Add the property to the nested object
                        $grouped[$baseField]['nested'][$potentialNestedObject]['nested'][$nestedProperty] = $ruleSet;

                        return;
                    }
                }

                // Check if this should be marked as a nested object
                if ($isNestedObjectInArray) {
                    $grouped[$baseField]['nested'][$remainingPath] = [
                        'rules' => $ruleSet,
                        'nested' => [],
                        'isNestedObject' => true,
                    ];
                } else {
                    // Simple nested property
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

    /**
     * Group rules by base field with metadata awareness
     * This is a specialized version for DataClassExtractor that handles metadata
     *
     * @param  array  $rules  The validation rules to group
     * @param  array  $metadata  Metadata for enhanced nested object detection
     * @return array The grouped rules
     */
    public function groupRulesByBaseFieldWithMetadata(array $rules, array $metadata): array
    {
        $grouped = [];
        $nestedObjectFields = $this->identifyNestedObjectFields($rules, $metadata);

        // Process rules
        foreach ($rules as $field => $ruleSet) {
            // Skip metadata markers
            if ($this->isSpecialMarkerField($field)) {
                continue;
            }

            $handled = false;

            // Check if this is part of a nested object (not within arrays)
            foreach ($nestedObjectFields as $objectField => $v) {
                // Skip nested objects that are within arrays - these will be handled by addNestedRule
                if (str_contains($objectField, '.*')) {
                    continue;
                }

                if (str_starts_with($field, $objectField.'.')) {
                    // Initialize the nested object structure if needed
                    if (! isset($grouped[$objectField])) {
                        $grouped[$objectField] = [
                            'rules' => null,
                            'nested' => [],
                            'isNestedObject' => true,
                        ];
                    }

                    // Extract property name
                    $propertyName = substr($field, strlen($objectField) + 1);

                    // Handle nested arrays within the object
                    if (str_contains($propertyName, '.*')) {
                        $this->addNestedRuleRecursively($grouped[$objectField]['nested'], $propertyName, $ruleSet);
                    } else {
                        $grouped[$objectField]['nested'][$propertyName] = $ruleSet;
                    }

                    $handled = true;
                    break;
                } elseif ($field === $objectField) {
                    // Base rules for the nested object
                    if (! isset($grouped[$objectField])) {
                        $grouped[$objectField] = [
                            'rules' => $ruleSet,
                            'nested' => [],
                            'isNestedObject' => true,
                        ];
                    } else {
                        $grouped[$objectField]['rules'] = $ruleSet;
                    }
                    $handled = true;
                    break;
                }
            }

            if (! $handled) {
                // Use standard grouping for non-nested-object fields
                if (str_contains($field, '.*')) {
                    // Check if this field is a nested object within an array using metadata
                    $isNestedObjectInArray = false;
                    foreach ($metadata as $meta) {
                        if (is_object($meta) &&
                            method_exists($meta, 'isNestedDataObject') &&
                            $meta->isNestedDataObject() &&
                            property_exists($meta, 'fieldName') &&
                            str_contains($meta->fieldName, '.*') &&
                            ($meta->fieldName === $field ||
                             (property_exists($meta, 'mappedName') && $meta->mappedName &&
                              str_replace($meta->propertyName, $meta->mappedName, $meta->fieldName) === $field))) {
                            $isNestedObjectInArray = true;
                            break;
                        }
                    }

                    $this->addNestedRule($grouped, $field, $ruleSet, $isNestedObjectInArray, $metadata);
                } else {
                    // Regular field
                    if (! isset($grouped[$field])) {
                        $grouped[$field] = [
                            'rules' => $ruleSet,
                            'nested' => [],
                        ];
                    } else {
                        $grouped[$field]['rules'] = $ruleSet;
                    }
                }
            }
        }

        // Clean up empty nested arrays
        $this->cleanupGroupedRules($grouped);

        return $grouped;
    }
}
