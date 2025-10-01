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
            $superRefineBlocks = array_merge($superRefineBlocks, $this->buildRequiredIfRefinementsForProperty($property));

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

        if (! is_string($dependentField) || $dependentField === '' || str_contains($dependentField, '*')) {
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
        if (count($valueExpressions) === 1) {
            return sprintf('%s === %s', $dependentAccessor, $valueExpressions[0]);
        }

        $values = '['.implode(', ', $valueExpressions).']';

        return sprintf('%s.includes(%s)', $values, $dependentAccessor);
    }

    protected function convertParameterToJsValue(string $value): string
    {
        $trimmed = trim($value);

        $lower = strtolower($trimmed);

        if ($lower === 'true' || $lower === 'false') {
            return $lower;
        }

        if ($lower === 'null') {
            return 'null';
        }

        if ($trimmed !== '' && is_numeric($trimmed) && ! ($trimmed[0] === '0' && strlen($trimmed) > 1 && ! str_contains($trimmed, '.'))) {
            return $trimmed;
        }

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
