<?php

namespace RomegaSoftware\LaravelZodGenerator\TypeHandlers;

use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodEnumBuilder;

class EnumTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return str_starts_with($type, 'enum:');
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return $this->canHandle($property->type);
    }

    public function handle(SchemaPropertyData $property): ZodBuilder
    {
        $type = $property->type;
        $validations = $property->validations;

        if (str_starts_with($type, 'enum:')) {
            // Handle enum class reference
            $enumName = substr($type, 5);
            $builder = new ZodEnumBuilder([], "App.{$enumName}");
        } elseif ($validations && $validations->hasValidation('in')) {
            // Handle 'in' rule with explicit values
            $builder = new ZodEnumBuilder($validations->getValidation('in'));
        } else {
            // Fallback - shouldn't happen given canHandle logic
            $builder = new ZodEnumBuilder([]);
        }

        // Handle optional
        $isOptional = $property->isOptional ?? false;
        if ($isOptional && (! $validations || ! $validations->isRequired())) {
            $builder->optional();
        }

        if (! $validations) {
            return $builder;
        }

        // Check for custom enum error messages
        if (str_starts_with($type, 'enum:') && $validations->getCustomMessage('enum')) {
            $builder->message($validations->getCustomMessage('enum'));
        } elseif ($validations->hasValidation('in') && $validations->getCustomMessage('in')) {
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
        return 300; // Highest priority to handle enums before other types
    }
}
