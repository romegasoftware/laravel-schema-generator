<?php

namespace RomegaSoftware\LaravelZodGenerator\TypeHandlers;

use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodNumberBuilder;

class NumberTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return in_array($type, ['number', 'integer', 'int', 'float', 'double']);
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return $this->canHandle($property->type);
    }

    public function handle(SchemaPropertyData $property): ZodBuilder
    {
        $builder = new ZodNumberBuilder;
        $validations = $property->validations;
        $type = $property->type;

        // Handle optional
        $isOptional = $property->isOptional ?? false;
        if ($isOptional && (! $validations || ! $validations->isRequired())) {
            $builder->optional();
        }

        if (! $validations) {
            return $builder;
        }

        // Add min validation
        if ($validations->hasValidation('min')) {
            $message = $validations->getCustomMessage('min');
            $builder->min($validations->getValidation('min'), $message);
        }

        // Add max validation
        if ($validations->hasValidation('max')) {
            $message = $validations->getCustomMessage('max');
            $builder->max($validations->getValidation('max'), $message);
        }

        // Add integer validation for integer types
        if ($type === 'integer' || $type === 'int') {
            $message = $validations->getCustomMessage('integer');
            $builder->int($message);
        }

        // Handle positive numbers
        if ($validations->hasValidation('positive')) {
            $message = $validations->getCustomMessage('positive');
            $builder->positive($message);
        }

        // Handle non-negative numbers (>= 0)
        if ($validations->hasValidation('gte') && $validations->getValidation('gte') === 0) {
            $message = $validations->getCustomMessage('gte');
            $builder->nonNegative($message);
        }

        // Handle negative numbers
        if ($validations->hasValidation('negative')) {
            $message = $validations->getCustomMessage('negative');
            $builder->negative($message);
        }

        // Handle non-positive numbers (<= 0)
        if ($validations->hasValidation('lte') && $validations->getValidation('lte') === 0) {
            $message = $validations->getCustomMessage('lte');
            $builder->nonPositive($message);
        }

        // Handle finite numbers
        if ($validations->hasValidation('finite')) {
            $message = $validations->getCustomMessage('finite');
            $builder->finite($message);
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
