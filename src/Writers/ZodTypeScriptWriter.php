<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Writers;

use Illuminate\Support\Facades\File;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;

class ZodTypeScriptWriter extends BaseScriptWriter
{
    public function __construct(protected ValidationSchemaGenerator $generator) {}

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
     * Write schemas to TypeScript file(s)
     *
     * @param  ExtractedSchemaData[]  $schemas
     */
    public function write(array $schemas): void
    {
        if (! config('laravel-schema-generator.zod.output.separate_files', false)) {
            parent::write($schemas);

            return;
        }

        $this->writeSeparateFiles($schemas);
    }

    /**
     * Write schemas to separate files
     *
     * @param  ExtractedSchemaData[]  $schemas
     */
    protected function writeSeparateFiles(array $schemas): void
    {
        $baseDirectory = config('laravel-schema-generator.zod.output.directory');

        if (! $baseDirectory) {
            $basePath = config('laravel-schema-generator.zod.output.path', resource_path('js/types/schemas.ts'));
            $baseDirectory = dirname($basePath);
        }

        if (! File::exists($baseDirectory)) {
            File::makeDirectory($baseDirectory, 0755, true);
        }

        // Process all schemas first (needed across all files for dependency reference)
        $generatedSchemas = [];
        foreach ($schemas as $schema) {
            $generatedSchemas[$schema->name] = $this->generator->generate($schema);
        }

        $dependencies = $this->generator->getSchemaDependencies();

        foreach ($schemas as $schema) {
            $content = $this->generator->generateHeader([$schema]);

            // Add imports for dependencies
            if (isset($dependencies[$schema->name])) {
                $imports = [];
                foreach ($dependencies[$schema->name] as $dependencyClass) {
                    $depSchemaName = $this->generator->generateSchemaName($dependencyClass);
                    // Don't import self
                    if ($depSchemaName === $schema->name) {
                        continue;
                    }
                    $imports[] = sprintf("import { %s } from './%s';", $depSchemaName, $depSchemaName);
                }

                if (! empty($imports)) {
                    $content .= implode("\n", array_unique($imports))."\n\n";
                }
            }

            // Generate content for this specific schema
            // We use the already generated schema body from the loop above
            $schemaVarName = $schema->name;
            $typeVarName = str_replace('Schema', 'SchemaType', $schema->name);
            $originalClassName = $this->getOriginalClassName($schema);
            $isDataTypeClass = $this->isDataClass($schema);
            $schemaDefinition = $generatedSchemas[$schemaVarName] ?? 'z.object({})';

            if ($this->generator->needsAppTypesImport([$schema]) && $originalClassName && $isDataTypeClass) {
                $content .= sprintf(
                    "export const %s: z.ZodType<%s.%s> = %s;\n",
                    $schemaVarName,
                    config('laravel-schema-generator.app_prefix', 'App'),
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

            $content .= sprintf("export type %s = z.infer<typeof %s>;\n", $typeVarName, $schemaVarName);

            $filePath = $baseDirectory.DIRECTORY_SEPARATOR.$schema->name.'.ts';
            File::put($filePath, $content);
        }
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

            $isDataTypeClass = $this->isDataClass($schema);

            // Get the cached schema definition
            $schemaDefinition = $generatedSchemas[$schemaVarName] ?? 'z.object({})';

            // Add TypeScript type annotation if we have App types
            if ($this->generator->needsAppTypesImport($schemas) && $originalClassName && $isDataTypeClass) {
                $content .= sprintf(
                    "export const %s: z.ZodType<%s.%s> = %s;\n",
                    $schemaVarName,
                    config('laravel-schema-generator.app_prefix', 'App'),
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
