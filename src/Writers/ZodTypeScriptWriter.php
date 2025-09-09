<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Writers;

use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;

class ZodTypeScriptWriter extends BaseScriptWriter
{
    public function __construct(protected ValidationSchemaGenerator $generator)
    {
    }

    public function getOutputPath(): string
    {
        return config('laravel-schema-generator.zod.output.path', resource_path('js/types/schemas.ts'));
    }

    /**
     * Generate TypeScript content
     *
     * @param  ExtractedSchemaData[]  $schemas
     */
    public function generateContent(array $schemas): string
    {
        $format = config('laravel-schema-generator.zod.output.format', 'module');

        if ($format === 'namespace') {
            return $this->generateNamespaceContent($schemas);
        }

        return $this->generateModuleContent($schemas);
    }

    /**
     * Generate module format content
     *
     * @param  ExtractedSchemaData[]  $schemas
     */
    protected function generateModuleContent(array $schemas): string
    {
        $content = $this->generator->generateHeader($schemas);

        // Process all schemas first to enable dependency sorting and cache results
        $generatedSchemas = [];
        foreach ($schemas as $schema) {
            $generatedSchemas[$schema->name] = $this->generator->generate($schema);
        }

        // Sort schemas by dependencies
        $sortedSchemas = $this->generator->sortSchemasByDependencies();

        // Generate each schema using cached results
        foreach ($sortedSchemas as $schema) {
            $schemaVarName = $schema->name;
            $typeVarName = str_replace('Schema', 'SchemaType', $schema->name);
            $originalClassName = $this->getOriginalClassName($schema);

            // Get the cached schema definition
            $schemaDefinition = $generatedSchemas[$schemaVarName] ?? 'z.object({})';

            // Add TypeScript type annotation if we have App types
            if ($this->generator->needsAppTypesImport($schemas) && $originalClassName) {
                $content .= sprintf(
                    "export const %s: z.ZodType<App.%s> = %s;\n",
                    $schemaVarName,
                    $originalClassName,
                    $schemaDefinition
                );
            } else {
                $content .= sprintf(
                    "export const %s = %s;\n",
                    $schemaVarName,
                    $schemaDefinition
                );
            }

            $content .= sprintf("export type %s = z.infer<typeof %s>;\n\n", $typeVarName, $schemaVarName);
        }

        return $content;
    }

    /**
     * Generate namespace format content
     *
     * @param  ExtractedSchemaData[]  $schemas
     */
    protected function generateNamespaceContent(array $schemas): string
    {
        $content = "import { z } from 'zod';\n\n";

        $namespace = config('laravel-schema-generator.zod.output.namespace', 'Schemas');
        $content .= "export namespace {$namespace} {\n";

        // Process all schemas first to enable dependency sorting and cache results
        $generatedSchemas = [];
        foreach ($schemas as $schema) {
            $generatedSchemas[$schema->name] = $this->generator->generate($schema);
        }

        // Sort schemas by dependencies
        $sortedSchemas = $this->generator->sortSchemasByDependencies();

        // Generate each schema using cached results
        foreach ($sortedSchemas as $schema) {
            $schemaVarName = $schema->name;
            $typeVarName = str_replace('Schema', 'SchemaType', $schema->name);

            // Get the cached schema definition
            $schemaDefinition = $generatedSchemas[$schemaVarName] ?? 'z.object({})';

            // Indent for namespace
            $indentedDefinition = $this->indentString($schemaDefinition, 2);

            $content .= sprintf("  export const %s = %s;\n", $schemaVarName, $indentedDefinition);
            $content .= sprintf("  export type %s = z.infer<typeof %s>;\n\n", $typeVarName, $schemaVarName);
        }

        $content .= "}\n";

        return $content;
    }
}
