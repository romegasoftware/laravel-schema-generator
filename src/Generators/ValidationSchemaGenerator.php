<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Generators;

use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyCollection;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerRegistry;

class ValidationSchemaGenerator extends BaseGenerator
{
    /**
     * Get the type handler registry (for customization)
     */
    public function getTypeHandlerRegistry(): TypeHandlerRegistry
    {
        return $this->typeHandlerRegistry;
    }

    public function generateHeader(array $schemas): string
    {
        $content = "import { z } from 'zod';\n\n";

        // Add import for App types if needed
        if ($this->needsAppTypesImport($schemas)) {
            $appImportPrefix = config('laravel-schema-generator.app_prefix', 'App');
            $appImportPath = config('laravel-schema-generator.app_types_import_path', '.');
            $content .= "import { {$appImportPrefix} } from '{$appImportPath}';\n\n";
        }

        return $content;
    }

    /**
     * Generate Zod schema from extracted data
     */
    public function generate(ExtractedSchemaData $extractedSchema): string
    {
        $this->processedSchemas[$extractedSchema->name] = $extractedSchema;

        // Track dependencies
        $this->schemaDependencies[$extractedSchema->name] = $extractedSchema->dependencies;

        return $this->buildValidationSchema($extractedSchema->properties);
    }

    /**
     * Build a Zod schema string from properties
     */
    protected function buildValidationSchema(?SchemaPropertyCollection $properties): string
    {
        if ($properties === null || $properties->isEmpty()) {
            return 'z.object({})';
        }

        $zodProperties = [];
        $superRefineBlocks = [];

        foreach ($properties as $property) {
            $superRefineBlocks = array_merge($superRefineBlocks, $this->buildSuperRefinementsForProperty($property));

            // Skip properties with dots in their names for cleaner TypeScript schemas
            if (str_contains($property->name, '.')) {
                continue;
            }

            $zodType = $this->buildZodType($property);
            $zodProperties[] = sprintf('    %s: %s', $property->name, $zodType);
        }

        $schema = sprintf("z.object({\n%s,\n})", implode(",\n", $zodProperties));

        if (! empty($superRefineBlocks)) {
            $indentedBlocks = array_map(fn (string $block) => $this->indentBlock($block), $superRefineBlocks);
            $schema .= ".superRefine((data, ctx) => {\n";
            $schema .= implode("\n\n", $indentedBlocks);
            $schema .= "\n})";
        }

        return $schema;
    }

    /**
     * Build a Zod type from property information
     */
    protected function buildZodType(SchemaPropertyData $property): string
    {
        // Get the appropriate handler for this property
        $handler = $this->typeHandlerRegistry->getHandlerForProperty($property);

        if (! $handler) {
            // This shouldn't happen with UniversalTypeHandler, but just in case
            $type = $property->validations->inferredType ?? 'unknown';
            throw new \RuntimeException("No handler found for property: {$property->name} with type: {$type}");
        }

        // Use the handler to build the Zod type
        $builder = $handler->handle($property);

        return $builder->build();
    }

    /**
     * Aggregate all superRefine blocks for the given property.
     *
     * @return array<int, string>
     */
    protected function buildSuperRefinementsForProperty(SchemaPropertyData $property): array
    {
        $blocks = [];

        $blocks = array_merge($blocks, $this->buildRequiredIfRefinementsForProperty($property));
        $blocks = array_merge($blocks, $this->buildConditionalAcceptanceRefinementsForProperty($property));
        $blocks = array_merge($blocks, $this->buildEqualityRefinementsForProperty($property));
        $blocks = array_merge($blocks, $this->buildDateComparisonRefinementsForProperty($property));

        return array_filter($blocks);
    }

    /**
     * Build refinement snippets for required_if validations on a property.
     *
     * @return array<int, string>
     */
    protected function buildRequiredIfRefinementsForProperty(SchemaPropertyData $property): array
    {
        if ($property->validations === null) {
            return [];
        }

        if (str_contains($property->name, '*')) {
            return [];
        }

        $validations = $property->validations->getValidations('RequiredIf');

        if ($validations->isEmpty()) {
            return [];
        }

        $blocks = [];

        foreach ($validations as $validation) {
            if (! $validation instanceof ResolvedValidation) {
                continue;
            }

            $block = $this->buildRequiredIfBlock($validation, $property);

            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    protected function buildRequiredIfBlock(ResolvedValidation $validation, SchemaPropertyData $property): ?string
    {
        $parameters = $validation->getParameters();

        if (count($parameters) < 2) {
            return null;
        }

        $dependentField = array_shift($parameters);

        if (! is_string($dependentField)) {
            return null;
        }

        $dependentField = $this->normalizeDependentField($dependentField, $property);

        if ($dependentField === '' || str_contains($dependentField, '*')) {
            return null;
        }

        $valueExpressions = [];

        foreach ($parameters as $value) {
            if (! is_string($value)) {
                continue;
            }

            $valueExpressions[] = $this->convertParameterToJsValue($value);
        }

        if (empty($valueExpressions)) {
            return null;
        }

        $dependentAccessor = $this->buildDataAccessor($dependentField);
        $targetAccessor = $this->buildDataAccessor($property->name);
        $emptyCheck = $this->buildEmptyCheckExpression($targetAccessor, $property->validations->inferredType ?? 'string');
        $pathLiteral = $this->buildPathLiteral($property->name);
        $valueCondition = $this->buildValueConditionExpression($dependentAccessor, $valueExpressions);

        $message = $validation->message ?? 'This field is required.';
        $escapedMessage = $this->escapeForJs($message);

        return implode("\n", [
            sprintf('if (%s && (%s)) {', $valueCondition, $emptyCheck),
            '    ctx.addIssue({',
            "        code: 'custom',",
            sprintf("        message: '%s',", $escapedMessage),
            sprintf('        path: %s,', $pathLiteral),
            '    });',
            '}',
        ]);
    }

    protected function buildAcceptedCheckExpression(string $targetAccessor): string
    {
        return sprintf('((val) => {'
            .' if (val === undefined || val === null) { return false; }'
            .' if (typeof val === "string") {'
            .' const normalized = val.toLowerCase();'
            .' if (normalized === "yes" || normalized === "on" || normalized === "true" || normalized === "1") { return true; }'
            .' }'
            .' return val === true || val === 1;'
            .' })(%s)', $targetAccessor);
    }

    protected function buildDeclinedCheckExpression(string $targetAccessor): string
    {
        return sprintf('((val) => {'
            .' if (val === undefined || val === null) { return false; }'
            .' if (typeof val === "string") {'
            .' const normalized = val.toLowerCase();'
            .' if (normalized === "no" || normalized === "off" || normalized === "false" || normalized === "0") { return true; }'
            .' }'
            .' return val === false || val === 0;'
            .' })(%s)', $targetAccessor);
    }

    protected function normalizeDependentField(string $dependentField, SchemaPropertyData $property): string
    {
        $dependentField = trim($dependentField);

        if ($dependentField === '') {
            return '';
        }

        $propertySegments = array_values(array_filter(
            explode('.', $property->name),
            static fn (string $segment): bool => $segment !== ''
        ));

        $dependentSegments = array_values(array_filter(
            explode('.', $dependentField),
            static fn (string $segment): bool => $segment !== ''
        ));

        if (empty($propertySegments) || empty($dependentSegments)) {
            return $dependentField;
        }

        $propertySegmentCount = count($propertySegments);

        if (count($dependentSegments) <= $propertySegmentCount) {
            return $dependentField;
        }

        for ($index = 0; $index < $propertySegmentCount; $index++) {
            if (! isset($dependentSegments[$index]) || $dependentSegments[$index] !== $propertySegments[$index]) {
                return $dependentField;
            }
        }

        $parentSegments = array_slice($propertySegments, 0, -1);
        $remainingSegments = array_slice($dependentSegments, $propertySegmentCount);

        if (empty($remainingSegments)) {
            return $dependentField;
        }

        $normalizedSegments = array_merge($parentSegments, $remainingSegments);

        if (empty($normalizedSegments)) {
            return '';
        }

        return implode('.', $normalizedSegments);
    }

    /**
     * Build conditional acceptance/decline refinements (accepted_if / declined_if).
     */
    protected function buildConditionalAcceptanceRefinementsForProperty(SchemaPropertyData $property): array
    {
        if ($property->validations === null || str_contains($property->name, '*')) {
            return [];
        }

        $blocks = [];

        foreach ($property->validations->getValidations('AcceptedIf') as $validation) {
            if ($validation instanceof ResolvedValidation) {
                $block = $this->buildConditionalAcceptanceBlock($validation, $property, true);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            }
        }

        foreach ($property->validations->getValidations('DeclinedIf') as $validation) {
            if ($validation instanceof ResolvedValidation) {
                $block = $this->buildConditionalAcceptanceBlock($validation, $property, false);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            }
        }

        return $blocks;
    }

    protected function buildConditionalAcceptanceBlock(ResolvedValidation $validation, SchemaPropertyData $property, bool $shouldBeAccepted): ?string
    {
        $parameters = $validation->getParameters();

        if (count($parameters) < 2) {
            return null;
        }

        $dependentField = array_shift($parameters);

        if (! is_string($dependentField)) {
            return null;
        }

        $dependentField = $this->normalizeDependentField($dependentField, $property);

        if ($dependentField === '' || str_contains($dependentField, '*')) {
            return null;
        }

        $valueExpressions = [];
        foreach ($parameters as $value) {
            if (! is_string($value)) {
                continue;
            }

            $valueExpressions[] = $this->convertParameterToJsValue($value);
        }

        if (empty($valueExpressions)) {
            return null;
        }

        $dependentAccessor = $this->buildDataAccessor($dependentField);
        $targetAccessor = $this->buildDataAccessor($property->name);
        $pathLiteral = $this->buildPathLiteral($property->name);
        $valueCondition = $this->buildValueConditionExpression($dependentAccessor, $valueExpressions);

        $message = $validation->message ?? ($shouldBeAccepted
            ? 'This field must be accepted.'
            : 'This field must be declined.');
        $escapedMessage = $this->escapeForJs($message);

        $acceptanceCheck = $shouldBeAccepted
            ? $this->buildAcceptedCheckExpression($targetAccessor)
            : $this->buildDeclinedCheckExpression($targetAccessor);

        return implode("\n", [
            sprintf('if (%s && !(%s)) {', $valueCondition, $acceptanceCheck),
            '    ctx.addIssue({',
            "        code: 'custom',",
            sprintf("        message: '%s',", $escapedMessage),
            sprintf('        path: %s,', $pathLiteral),
            '    });',
            '}',
        ]);
    }

    /**
     * Build equality-based refinements (confirmed, same, different).
     */
    protected function buildEqualityRefinementsForProperty(SchemaPropertyData $property): array
    {
        if ($property->validations === null || str_contains($property->name, '*')) {
            return [];
        }

        $blocks = [];
        $validations = $property->validations;

        $confirmed = $validations->getValidation('Confirmed');
        if ($confirmed instanceof ResolvedValidation) {
            $block = $this->buildConfirmedBlock($confirmed, $property);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        foreach ($validations->getValidations('Same') as $validation) {
            if ($validation instanceof ResolvedValidation) {
                $block = $this->buildSameBlock($validation, $property);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            }
        }

        foreach ($validations->getValidations('Different') as $validation) {
            if ($validation instanceof ResolvedValidation) {
                $block = $this->buildDifferentBlock($validation, $property);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            }
        }

        return $blocks;
    }

    protected function buildConfirmedBlock(ResolvedValidation $validation, SchemaPropertyData $property): ?string
    {
        $confirmationField = $property->name.'_confirmation';

        $targetAccessor = $this->buildDataAccessor($property->name);
        $confirmationAccessor = $this->buildDataAccessor($confirmationField);
        $pathLiteral = $this->buildPathLiteral($property->name);

        $message = $validation->message ?? 'The confirmation does not match.';
        $escapedMessage = $this->escapeForJs($message);

        return implode("\n", [
            '{',
            sprintf('    const confirmationValue = %s;', $confirmationAccessor),
            sprintf('    const currentValue = %s;', $targetAccessor),
            '    if (confirmationValue === undefined || confirmationValue === null) {',
            '        ctx.addIssue({',
            "            code: 'custom',",
            sprintf("            message: '%s',", $escapedMessage),
            sprintf('            path: %s,', $pathLiteral),
            '        });',
            '    } else if (String(currentValue ?? \'\') !== String(confirmationValue ?? \'\')) {',
            '        ctx.addIssue({',
            "            code: 'custom',",
            sprintf("            message: '%s',", $escapedMessage),
            sprintf('            path: %s,', $pathLiteral),
            '        });',
            '    }',
            '}',
        ]);
    }

    protected function buildSameBlock(ResolvedValidation $validation, SchemaPropertyData $property): ?string
    {
        $parameters = $validation->getParameters();
        if (empty($parameters) || ! is_string($parameters[0]) || $parameters[0] === '' || str_contains($parameters[0], '*')) {
            return null;
        }

        $otherField = $parameters[0];
        $otherAccessor = $this->buildDataAccessor($otherField);
        $targetAccessor = $this->buildDataAccessor($property->name);
        $pathLiteral = $this->buildPathLiteral($property->name);

        $message = $validation->message ?? 'The fields must match.';
        $escapedMessage = $this->escapeForJs($message);

        return implode("\n", [
            '{',
            sprintf('    const currentValue = %s;', $targetAccessor),
            sprintf('    const otherValue = %s;', $otherAccessor),
            '    if (String(currentValue ?? \'\') !== String(otherValue ?? \'\')) {',
            '        ctx.addIssue({',
            "            code: 'custom',",
            sprintf("            message: '%s',", $escapedMessage),
            sprintf('            path: %s,', $pathLiteral),
            '        });',
            '    }',
            '}',
        ]);
    }

    protected function buildDifferentBlock(ResolvedValidation $validation, SchemaPropertyData $property): ?string
    {
        $parameters = $validation->getParameters();
        if (empty($parameters) || ! is_string($parameters[0]) || $parameters[0] === '' || str_contains($parameters[0], '*')) {
            return null;
        }

        $otherField = $parameters[0];
        $otherAccessor = $this->buildDataAccessor($otherField);
        $targetAccessor = $this->buildDataAccessor($property->name);
        $pathLiteral = $this->buildPathLiteral($property->name);

        $message = $validation->message ?? 'The fields must be different.';
        $escapedMessage = $this->escapeForJs($message);

        return implode("\n", [
            '{',
            sprintf('    const currentValue = %s;', $targetAccessor),
            sprintf('    const otherValue = %s;', $otherAccessor),
            '    if (String(currentValue ?? \'\') === String(otherValue ?? \'\')) {',
            '        ctx.addIssue({',
            "            code: 'custom',",
            sprintf("            message: '%s',", $escapedMessage),
            sprintf('            path: %s,', $pathLiteral),
            '        });',
            '    }',
            '}',
        ]);
    }

    /**
     * Build date comparison refinements (after/before/date_equals).
     */
    protected function buildDateComparisonRefinementsForProperty(SchemaPropertyData $property): array
    {
        if ($property->validations === null || str_contains($property->name, '*')) {
            return [];
        }

        $blocks = [];
        $validations = $property->validations;

        foreach (['After', 'AfterOrEqual', 'Before', 'BeforeOrEqual', 'DateEquals'] as $ruleName) {
            foreach ($validations->getValidations($ruleName) as $validation) {
                if (! $validation instanceof ResolvedValidation) {
                    continue;
                }

                $block = $this->buildDateComparisonBlock($validation, $property, $ruleName);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            }
        }

        return $blocks;
    }

    protected function buildDateComparisonBlock(ResolvedValidation $validation, SchemaPropertyData $property, string $ruleName): ?string
    {
        $parameters = $validation->getParameters();
        $reference = $parameters[0] ?? null;

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $targetAccessor = $this->buildDataAccessor($property->name);
        $pathLiteral = $this->buildPathLiteral($property->name);

        $comparison = $this->buildDateReferenceExpression($reference, $property);
        if ($comparison === null) {
            return null;
        }

        $messageKey = match ($ruleName) {
            'After' => 'after',
            'AfterOrEqual' => 'after_or_equal',
            'Before' => 'before',
            'BeforeOrEqual' => 'before_or_equal',
            'DateEquals' => 'date_equals',
            default => 'date',
        };

        $message = $validation->message ?? 'Invalid date comparison.';
        $escapedMessage = $this->escapeForJs($message);

        $comparisonOperator = match ($ruleName) {
            'After' => '<=',
            'AfterOrEqual' => '<',
            'Before' => '>=',
            'BeforeOrEqual' => '>',
            'DateEquals' => '!==',
            default => '!==',
        };

        $comparisonCheck = sprintf('(valueTimestamp %s referenceTimestamp)', $comparisonOperator);

        return implode("\n", [
            '{',
            sprintf('    const currentRaw = %s;', $targetAccessor),
            '    if (currentRaw === undefined || currentRaw === null || currentRaw === \'\') {',
            '        return;',
            '    }',
            '    const valueTimestamp = Date.parse(String(currentRaw));',
            '    if (Number.isNaN(valueTimestamp)) {',
            '        ctx.addIssue({',
            "            code: 'custom',",
            sprintf("            message: '%s',", $escapedMessage),
            sprintf('            path: %s,', $pathLiteral),
            '        });',
            '        return;',
            '    }',
            sprintf('    const referenceTimestamp = %s;', $comparison),
            '    if (Number.isNaN(referenceTimestamp)) {',
            '        return;',
            '    }',
            sprintf('    if (%s) {', $comparisonCheck),
            '        ctx.addIssue({',
            "            code: 'custom',",
            sprintf("            message: '%s',", $escapedMessage),
            sprintf('            path: %s,', $pathLiteral),
            '        });',
            '    }',
            '}',
        ]);
    }

    protected function buildDateReferenceExpression(string $reference, SchemaPropertyData $property): ?string
    {
        $reference = trim($reference);

        if ($reference === 'now') {
            return 'Date.now()';
        }

        if ($reference === 'today') {
            return '(() => { const d = new Date(); d.setHours(0, 0, 0, 0); return d.getTime(); })()';
        }

        if ($reference === 'tomorrow') {
            return '(() => { const d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() + 1); return d.getTime(); })()';
        }

        if ($reference === 'yesterday') {
            return '(() => { const d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() - 1); return d.getTime(); })()';
        }

        if ($this->looksLikeDateString($reference)) {
            $escaped = $this->escapeForJs($reference);
            return "(() => { const ts = Date.parse('{$escaped}'); return Number.isNaN(ts) ? NaN : ts; })()";
        }

        if (str_contains($reference, '*')) {
            return null;
        }

        $accessor = $this->buildDataAccessor($reference);

        return sprintf('(() => { const raw = %s; if (raw === undefined || raw === null || raw === \'\') { return NaN; } const ts = Date.parse(String(raw)); return Number.isNaN(ts) ? NaN : ts; })()', $accessor);
    }

    protected function looksLikeDateString(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (in_array(strtolower($value), ['now', 'today', 'tomorrow', 'yesterday'], true)) {
            return true;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false;
    }

    protected function buildDataAccessor(string $field): string
    {
        $segments = array_filter(explode('.', $field), fn ($segment) => $segment !== '');
        $accessor = 'data';

        foreach ($segments as $index => $segment) {
            $isIdentifier = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) === 1;

            if ($index === 0) {
                if ($isIdentifier) {
                    $accessor .= '.'.$segment;
                } else {
                    $escaped = $this->escapeForJs($segment);
                    $accessor .= "['{$escaped}']";
                }

                continue;
            }

            if ($isIdentifier) {
                $accessor .= '?.'.$segment;
            } else {
                $escaped = $this->escapeForJs($segment);
                $accessor .= "?.['{$escaped}']";
            }
        }

        return $accessor;
    }

    protected function buildEmptyCheckExpression(string $targetAccessor, string $inferredType): string
    {
        $type = strtolower($inferredType);

        if (str_starts_with($type, 'enum:')) {
            $type = 'string';
        }

        return match ($type) {
            'array' => "!Array.isArray({$targetAccessor}) || {$targetAccessor}.length === 0",
            'number', 'boolean' => "{$targetAccessor} === undefined || {$targetAccessor} === null",
            'object', 'file' => "{$targetAccessor} === undefined || {$targetAccessor} === null",
            default => "{$targetAccessor} === undefined || {$targetAccessor} === null || String({$targetAccessor}).trim() === ''",
        };
    }

    protected function buildPathLiteral(string $field): string
    {
        $segments = array_filter(explode('.', $field), fn ($segment) => $segment !== '');

        $parts = array_map(function (string $segment): string {
            return "'".$this->escapeForJs($segment)."'";
        }, $segments);

        if (empty($parts)) {
            return '[]';
        }

        return '['.implode(', ', $parts).']';
    }

    protected function buildValueConditionExpression(string $dependentAccessor, array $valueExpressions): string
    {
        $stringAccessor = sprintf('String(%s)', $dependentAccessor);

        if (count($valueExpressions) === 1) {
            return sprintf('%s === %s', $stringAccessor, $valueExpressions[0]);
        }

        $values = '['.implode(', ', $valueExpressions).']';

        return sprintf('%s.includes(%s)', $values, $stringAccessor);
    }

    protected function convertParameterToJsValue(string $value): string
    {
        $trimmed = trim($value);

        return "'".$this->escapeForJs($trimmed)."'";
    }

    protected function escapeForJs(string $value): string
    {
        return str_replace(
            ['\\', "'", '"', "\n", "\r", "\t"],
            ['\\\\', "\\'", '\\"', '\\n', '\\r', '\\t'],
            $value
        );
    }

    protected function indentBlock(string $block, int $level = 1): string
    {
        $indent = str_repeat('    ', $level);
        $lines = explode("\n", $block);

        foreach ($lines as $index => $line) {
            if ($line === '') {
                continue;
            }

            $lines[$index] = $indent.$line;
        }

        return implode("\n", $lines);
    }
}
