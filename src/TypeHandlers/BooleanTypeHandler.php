<?php

namespace RomegaSoftware\LaravelSchemaGenerator\TypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;

class BooleanTypeHandler extends BaseTypeHandler
{
    public function __construct(ZodBuilderFactory $factory)
    {
        parent::__construct($factory);
    }

    public function canHandle(string $type): bool
    {
        return in_array($type, ['boolean', 'bool']);
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return $property->validations && $this->canHandle($property->validations->inferredType);
    }

    public function handle(SchemaPropertyData $property): BuilderInterface
    {
        $builder = $this->factory->createBooleanBuilder();
        $validations = $property->validations;

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
        return 100;
    }
}
