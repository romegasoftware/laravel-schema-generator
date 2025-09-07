<?php

namespace RomegaSoftware\LaravelZodGenerator\TypeHandlers;

use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\Support\SchemaNameGenerator;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodArrayBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodObjectBuilder;

class DataClassTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return str_ends_with($type, 'Data') || str_starts_with($type, 'DataCollection:');
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return $this->canHandle($property->type);
    }

    public function handle(SchemaPropertyData $property): ZodBuilder
    {
        $type = $property->type;
        $validations = $property->validations;

        // Handle DataCollection references
        if (str_starts_with($type, 'DataCollection:')) {
            $dataClass = substr($type, 15);
            $schemaName = SchemaNameGenerator::generate($dataClass);
            $builder = new ZodArrayBuilder($schemaName);
        } else {
            // Handle Data class references
            $schemaName = SchemaNameGenerator::generate($type);
            $builder = new ZodObjectBuilder($schemaName);
        }

        // Handle optional
        if ($property->isOptional && (! $validations || ! $validations->isRequired())) {
            $builder->optional();
        }

        if (! $validations) {
            return $builder;
        }

        // Handle nullable
        if ($validations->isNullable()) {
            $builder->nullable();
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 200; // Higher priority than generic handlers
    }
}
