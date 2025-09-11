<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Generators;

use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerRegistry;
use Spatie\LaravelData\DataCollection;

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
     *
     * @param  SchemaPropertyData[]  $properties
     */
    protected function buildValidationSchema(?DataCollection $properties): string
    {
        if (empty($properties)) {
            return 'z.object({})';
        }

        $zodProperties = [];

        foreach ($properties as $property) {
            // Skip properties with dots in their names for cleaner TypeScript schemas
            if (str_contains($property->name, '.')) {
                continue;
            }

            $zodType = $this->buildZodType($property);
            $zodProperties[] = sprintf('    %s: %s', $property->name, $zodType);
        }

        return sprintf("z.object({\n%s,\n})", implode(",\n", $zodProperties));
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
            $type = $property->validations?->inferredType ?? 'unknown';
            throw new \RuntimeException("No handler found for property: {$property->name} with type: {$type}");
        }

        // Use the handler to build the Zod type
        $builder = $handler->handle($property);

        return $builder->build();
    }
}
