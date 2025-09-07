<?php

namespace RomegaSoftware\LaravelZodGenerator\TypeHandlers;

use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodEnumBuilder;

class InRuleTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        // This handler specifically looks at validations, not the type
        return false;
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        $validations = $property->validations;

        return $validations && $validations->hasValidation('in');
    }

    public function handle(SchemaPropertyData $property): ZodBuilder
    {
        $validations = $property->validations;

        // Handle 'in' rule with explicit values
        $builder = new ZodEnumBuilder($validations->getValidation('in'));

        // Handle optional
        $isOptional = $property->isOptional ?? false;
        if ($isOptional && ! $validations->isRequired()) {
            $builder->optional();
        }

        // Check for custom enum error message
        if ($validations->getCustomMessage('in')) {
            $builder->message($validations->getCustomMessage('in'));
        }

        // Handle nullable
        if ($validations->isNullable()) {
            $builder->nullable();
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 400; // Highest priority to handle enum validation before other types
    }
}
