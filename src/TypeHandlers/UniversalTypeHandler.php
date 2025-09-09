<?php

namespace RomegaSoftware\LaravelSchemaGenerator\TypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;

/**
 * Universal type handler that dynamically applies all validations
 * This replaces most specific type handlers by trusting Laravel's validation
 */
class UniversalTypeHandler extends BaseTypeHandler
{
    public function __construct(ZodBuilderFactory $factory)
    {
        parent::__construct($factory);
    }

    public function canHandle(string $type): bool
    {
        return true; // This is the fallback handler for all types
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return $property->validations instanceof ResolvedValidationSet;
    }

    public function handle(SchemaPropertyData $property): BuilderInterface
    {
        if (! $property->validations instanceof ResolvedValidationSet) {
            throw new \InvalidArgumentException('UniversalTypeHandler requires ResolvedValidationSet');
        }

        $this->property = $property;

        // Create appropriate builder based on inferred type
        $this->builder = $this->createBuilderForType();

        // Set field name for auto-message resolution
        $this->builder->setFieldName($this->property->validations->fieldName);

        // Handle optional/required state
        if ($property->isOptional && ! $this->property->validations->isFieldRequired()) {
            $this->builder->optional();
        }

        // Apply nullable if needed
        if ($this->property->validations->isFieldNullable()) {
            $this->builder->nullable();
        }

        // Dynamically apply all validation rules
        $this->applyValidations();

        return $this->builder;
    }

    public function getPriority(): int
    {
        return 1; // Lowest priority - this is the fallback
    }
}
