<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Contracts;

interface SchemaNameGeneratorInterface
{
    /**
     * Generate schema name from class name
     */
    public static function generate(string $className): string;
}
