<?php

namespace RomegaSoftware\LaravelZodGenerator\Generators;

use RomegaSoftware\LaravelZodGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\Support\SchemaNameGenerator;
use RomegaSoftware\LaravelZodGenerator\TypeHandlers\TypeHandlerRegistry;
use Spatie\LaravelData\DataCollection;

class ZodSchemaGenerator
{
    protected array $processedSchemas = [];

    protected array $schemaDependencies = [];

    protected TypeHandlerRegistry $typeHandlerRegistry;

    public function __construct(TypeHandlerRegistry $typeHandlerRegistry)
    {
        $this->typeHandlerRegistry = $typeHandlerRegistry;
    }

    /**
     * Get the type handler registry (for customization)
     */
    public function getTypeHandlerRegistry(): TypeHandlerRegistry
    {
        return $this->typeHandlerRegistry;
    }

    /**
     * Generate Zod schema from extracted data
     */
    public function generate(ExtractedSchemaData $extractedSchema): string
    {
        $this->processedSchemas[$extractedSchema->name] = $extractedSchema;

        // Track dependencies
        $this->schemaDependencies[$extractedSchema->name] = $extractedSchema->dependencies;

        return $this->buildZodSchema($extractedSchema->properties);
    }

    /**
     * Build a Zod schema string from properties
     *
     * @param  SchemaPropertyData[]  $properties
     */
    protected function buildZodSchema(?DataCollection $properties): string
    {
        if (empty($properties)) {
            return 'z.object({})';
        }

        $zodProperties = [];

        foreach ($properties as $property) {
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
            // This shouldn't happen with FallbackTypeHandler, but just in case
            throw new \RuntimeException("No handler found for property: {$property->name} with type: {$property->type}");
        }

        // Use the handler to build the Zod type
        $builder = $handler->handle($property);

        return $builder->build();
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
