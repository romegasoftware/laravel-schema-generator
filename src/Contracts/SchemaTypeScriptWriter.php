<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Contracts;

interface SchemaTypeScriptWriter
{
    public function generateContent(array $schemas): string;

    public function getOutputPath(): string;

    /**
     * @param  ExtractedSchemaData[]  $schemas
     */
    public function write(array $schemas): void;
}
