<?php

namespace RomegaSoftware\LaravelZodGenerator\TypeHandlers;

use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodAnyBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;

class FallbackTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return true; // Handles any type as fallback
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return true; // Always handles as fallback
    }

    public function handle(SchemaPropertyData $property): ZodBuilder
    {
        $builder = new ZodAnyBuilder;
        $validations = $property->validations;

        // Handle optional
        $isOptional = $property->isOptional ?? false;
        if ($isOptional && (! $validations || ! $validations->isRequired())) {
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
        return 0; // Lowest priority - only used as fallback
    }
}
