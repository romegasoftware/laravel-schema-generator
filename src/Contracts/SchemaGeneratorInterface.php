<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Contracts;

use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;

interface SchemaGeneratorInterface
{
    public array $processedSchemas {get; }

    public array $schemaDependencies {get; }

    /**
     * Check if we need to import App types
     *
     * @param  ExtractedSchemaData[]  $schemas
     */
    public function needsAppTypesImport(array $schemas): bool;

    /*
     * Writes the header of the outputed file, including needed imports.
     * @param ExtractedSchemaData[] $schemas
     */
    public function generateHeader(array $schemas): string;

    /*
     * Generates the primary schema for every Extract Schema.
     */
    public function generate(ExtractedSchemaData $schemas): string;
}
