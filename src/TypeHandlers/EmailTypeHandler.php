<?php

namespace RomegaSoftware\LaravelZodGenerator\TypeHandlers;

use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodEmailBuilder;

class EmailTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        // This handler checks for email validation rule, not type
        return false;
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        $validations = $property->validations;

        return $validations && $validations->hasValidation('email') && $validations->getValidation('email') === true;
    }

    public function handle(SchemaPropertyData $property): ZodBuilder
    {
        $builder = new ZodEmailBuilder;
        $validations = $property->validations;
        $propertyName = $property->name;

        // Handle optional
        if ($property->isOptional && (! $validations || ! $validations->isRequired())) {
            $builder->optional();
        }

        if (! $validations) {
            return $builder;
        }

        // Handle required validation for email
        if ($validations->isRequired()) {
            $message = $validations->getCustomMessage('required')
                ?? ucfirst(str_replace('_', ' ', $propertyName)).' is required';

            // For required emails, we need to add trim and min validation
            $builder->trim()->min(1, $message);
        }

        // Add max validation for email
        if ($validations->hasValidation('max')) {
            $message = $validations->getCustomMessage('max');
            $builder->max($validations->getValidation('max'), $message);
        }

        // Handle custom email validation message
        if ($validations->getCustomMessage('email') !== null) {
            $builder->emailMessage($validations->getCustomMessage('email'));
        }

        // Handle nullable
        if ($validations->isNullable()) {
            $builder->nullable();
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 250; // Higher priority than string handler but lower than email type handler
    }
}
