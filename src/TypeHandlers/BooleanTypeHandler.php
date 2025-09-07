<?php

namespace RomegaSoftware\LaravelZodGenerator\TypeHandlers;

use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBooleanBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;

class BooleanTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return in_array($type, ['boolean', 'bool']);
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return $this->canHandle($property->type);
    }

    public function handle(SchemaPropertyData $property): ZodBuilder
    {
        $builder = new ZodBooleanBuilder;
        $validations = $property->validations;

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
        return 100;
    }
}
