<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Generators;

use Illuminate\Support\Traits\Macroable;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\SchemaGeneratorInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaNameGenerator;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerRegistry;

abstract class BaseGenerator implements SchemaGeneratorInterface
{
    use Macroable;

    public array $processedSchemas = [];

    public array $schemaDependencies = [];

    public function __construct(protected TypeHandlerRegistry $typeHandlerRegistry) {}

    /**
     * Check if we need to import App types
     *
     * @param  ExtractedSchemaData[]  $schemas
     */
    public function needsAppTypesImport(array $schemas): bool
    {
        if (! config('laravel-schema-generator.use_app_types', false)) {
            return false;
        }

        foreach ($schemas as $schema) {
            // Check if it's a Data class or if config says to use App types
            if (isset($schema->type) && $schema->type === 'data') {
                return true;
            }

            // Check for enum types
            foreach ($schema->properties ?? [] as $property) {
                $type = $property->validations->inferredType ?? 'string';
                if (str_starts_with($type, 'enum:')) {
                    return true;
                }
            }
        }

        return config('laravel-schema-generator.use_app_types', false);
    }

    /**
     * Generate schema name from class name
     */
    public function generateSchemaName(string $className): string
    {
        return SchemaNameGenerator::generate($className);
    }

    /**
     * Get all processed schemas
     */
    public function getProcessedSchemas(): array
    {
        return $this->processedSchemas;
    }

    /**
     * Get schema dependencies
     */
    public function getSchemaDependencies(): array
    {
        return $this->schemaDependencies;
    }

    /**
     * Sort schemas by dependencies
     *
     * @return ExtractedSchemaData[]
     */
    public function sortSchemasByDependencies(): array
    {
        $sorted = [];
        $visited = [];

        foreach (array_keys($this->schemaDependencies) as $schemaName) {
            $this->visitSchema($schemaName, $sorted, $visited);
        }

        // Add schemas without dependencies
        foreach ($this->processedSchemas as $schema) {
            $schemaName = is_array($schema) ? $schema['name'] : $schema->name;
            if (! isset($visited[$schemaName])) {
                $sorted[] = $schema;
            }
        }

        return $sorted;
    }

    /**
     * Visit schema for dependency sorting
     */
    protected function visitSchema(string $schemaName, array &$sorted, array &$visited): void
    {
        if (isset($visited[$schemaName])) {
            return;
        }

        $visited[$schemaName] = true;

        // Visit dependencies first
        if (isset($this->schemaDependencies[$schemaName])) {
            foreach ($this->schemaDependencies[$schemaName] as $dep) {
                $depSchemaName = $this->generateSchemaName($dep);
                if (isset($this->processedSchemas[$depSchemaName])) {
                    $this->visitSchema($depSchemaName, $sorted, $visited);
                }
            }
        }

        // Add this schema
        if (isset($this->processedSchemas[$schemaName])) {
            $sorted[] = $this->processedSchemas[$schemaName];
        }
    }
}
