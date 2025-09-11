<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Support;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\SchemaNameGeneratorInterface;

class SchemaNameGenerator implements SchemaNameGeneratorInterface
{
    /**
     * Generate schema name from class name
     */
    public static function generate(string $className): string
    {
        $shortName = class_basename($className);

        if (str_ends_with($className, 'Schema')) {
            return $shortName;
        }

        return $shortName.'Schema';
    }

    /**
     * Generate schema name from ReflectionClass with ValidationSchema attribute support
     */
    public static function fromClass(ReflectionClass $class): string
    {
        $attributes = $class->getAttributes(ValidationSchema::class);

        if (! empty($attributes)) {
            $zodAttribute = $attributes[0]->newInstance();
            if ($zodAttribute->name) {
                return $zodAttribute->name;
            }
        }

        $className = $class->getShortName();

        return self::generate($className);
    }
}
