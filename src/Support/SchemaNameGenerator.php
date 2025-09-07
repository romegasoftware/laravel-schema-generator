<?php

namespace RomegaSoftware\LaravelZodGenerator\Support;

class SchemaNameGenerator
{
    /**
     * Generate schema name from class name
     */
    public static function generate(string $className): string
    {
        $shortName = class_basename($className);

        if (str_ends_with($shortName, 'Data')) {
            return substr($shortName, 0, -4).'Schema';
        }

        if (str_ends_with($shortName, 'Request')) {
            return substr($shortName, 0, -7).'Schema';
        }

        return $shortName.'Schema';
    }
}
