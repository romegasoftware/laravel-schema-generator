<?php

namespace RomegaSoftware\LaravelZodGenerator\Generators;

use Illuminate\Support\Facades\File;
use RomegaSoftware\LaravelZodGenerator\Data\ExtractedSchemaData;

class TypeScriptWriter
{
    protected ZodSchemaGenerator $generator;

    public function __construct(ZodSchemaGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Write schemas to TypeScript file
     */
    public function write(array $schemas, string $outputPath): void
    {
        $content = $this->generateContent($schemas);

        // Ensure directory exists
        $directory = dirname($outputPath);
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($outputPath, $content);
    }

    /**
     * Generate TypeScript content
     */
    protected function generateContent(array $schemas): string
    {
        $format = config('laravel-zod-generator.output.format', 'module');

        if ($format === 'namespace') {
            return $this->generateNamespaceContent($schemas);
        }

        return $this->generateModuleContent($schemas);
    }

    /**
     * Generate module format content
     */
    protected function generateModuleContent(array $schemas): string
    {
        $content = "import { z } from 'zod';\n\n";

        // Add import for App types if needed
        $needsAppImport = $this->needsAppTypesImport($schemas);
        if ($needsAppImport) {
            $appImportPath = config('laravel-zod-generator.app_types_import_path', '.');
            $content .= "import { App } from '{$appImportPath}';\n\n";
        }

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
            if ($needsAppImport && $originalClassName) {
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
     */
    protected function generateNamespaceContent(array $schemas): string
    {
        $content = "import { z } from 'zod';\n\n";

        $namespace = config('laravel-zod-generator.namespace', 'Schemas');
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

    /**
     * Check if we need to import App types
     */
    protected function needsAppTypesImport(array $schemas): bool
    {
        foreach ($schemas as $schema) {
            // Check if it's a Data class or if config says to use App types
            if (isset($schema->type) && $schema->type === 'data') {
                return true;
            }

            // Check for enum types
            foreach ($schema->properties ?? [] as $property) {
                if (str_starts_with($property['type'], 'enum:')) {
                    return true;
                }
            }
        }

        return config('laravel-zod-generator.use_app_types', false);
    }

    /**
     * Get the original class name for TypeScript
     */
    protected function getOriginalClassName(ExtractedSchemaData $schema): ?string
    {
        if (! isset($schema->className)) {
            return null;
        }

        $className = class_basename($schema->className);

        // If it's a Request class and we're using TypeScript transformer
        if (str_ends_with($className, 'Request') &&
            config('laravel-zod-generator.use_app_types', false)) {
            return $className;
        }

        // If it's a Data class
        if (str_ends_with($className, 'Data')) {
            return $className;
        }

        return null;
    }

    /**
     * Indent a string
     */
    protected function indentString(string $string, int $spaces): string
    {
        $lines = explode("\n", $string);
        $indent = str_repeat(' ', $spaces);

        return implode("\n", array_map(function ($line) use ($indent) {
            return $line ? $indent.$line : $line;
        }, $lines));
    }
}
