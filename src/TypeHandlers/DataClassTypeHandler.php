<?php

namespace RomegaSoftware\LaravelSchemaGenerator\TypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaNameGenerator;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodObjectBuilder;

class DataClassTypeHandler extends BaseTypeHandler
{
    public function __construct(ZodBuilderFactory $factory)
    {
        parent::__construct($factory);
    }

    public function canHandle(string $type): bool
    {
        return str_ends_with($type, 'Data') || str_starts_with($type, 'DataCollection:') || str_ends_with($type, 'Schema');
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return $property->validations && $this->canHandle($property->validations->inferredType);
    }

    public function handle(SchemaPropertyData $property): BuilderInterface
    {
        $type = $property->validations->inferredType;
        $validations = $property->validations;

        // Handle DataCollection references
        if (str_starts_with($type, 'DataCollection:')) {
            $dataClass = substr($type, 15);
            $schemaName = SchemaNameGenerator::generate($dataClass);
            $builder = $this->factory->createArrayBuilder($schemaName);
        } elseif (str_ends_with($type, 'Schema')) {
            // Handle Schema references directly
            $builder = new ZodObjectBuilder($type);
        } else {
            // Handle Data class references
            $schemaName = SchemaNameGenerator::generate($type);
            $builder = new ZodObjectBuilder($schemaName);
        }

        // Handle optional
        if ($property->isOptional && (! $validations || ! $validations->isFieldRequired())) {
            $builder->optional();
        }

        if (! $validations) {
            return $builder;
        }

        // Handle nullable
        if ($validations->isFieldNullable()) {
            $builder->nullable();
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 200; // Higher priority than generic handlers
    }
}
