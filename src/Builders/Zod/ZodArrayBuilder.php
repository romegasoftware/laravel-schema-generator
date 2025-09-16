<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

use Override;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;

class ZodArrayBuilder extends ZodBuilder
{
    protected string $itemType;

    protected ?BuilderInterface $itemBuilder = null;

    public function __construct(
        string $itemType,
        private ZodBuilderFactory $factory,
        private UniversalTypeHandler $universalTypeHandler
    ) {
        $this->itemType = $itemType;
    }

    /**
     * Logic to setup the builder. (i.e.: The nesting logic for an array)
     */
    #[Override]
    public function setup(): self
    {
        $arrayBuilder = $this->factory->createArrayBuilder();
        $nestedValidations = $this->property->validations->getNestedValidations();

        // If we have nested validations, create a builder for the array items
        if ($nestedValidations !== null) {
            if ($nestedValidations->hasObjectProperties()) {
                // Create an object builder for array items with multiple properties
                $itemBuilder = $this->createObjectBuilderFromProperties($nestedValidations->getObjectProperties());
            } else {
                // Regular item builder for simple array items
                $typeHandler = clone $this->universalTypeHandler;
                $propertyData = new SchemaPropertyData(
                    name: $nestedValidations->fieldName,
                    validator: null,
                    isOptional: ! $nestedValidations->isFieldRequired(),
                    validations: $nestedValidations
                );
                $itemBuilder = $typeHandler->setProperty($propertyData)->createBuilderForType();
                $itemBuilder->setFieldName($nestedValidations->fieldName);

                // Apply validations to the item builder
                $typeHandler->applyValidations();
            }

            $arrayBuilder->ofBuilder($itemBuilder);
        }

        return $arrayBuilder;
    }

    /**
     * Create an object builder from resolved validation sets for object properties
     */
    protected function createObjectBuilderFromProperties(array $objectProperties): ZodInlineObjectBuilder
    {
        $objectBuilder = $this->factory->createInlineObjectBuilder();

        foreach ($objectProperties as $propertyName => $validationSet) {
            /** @var ResolvedValidationSet $validationSet */
            $typeHandler = clone $this->universalTypeHandler;
            $propertyData = new SchemaPropertyData(
                name: $validationSet->fieldName,
                validator: null,
                isOptional: ! $validationSet->isFieldRequired(),
                validations: $validationSet
            );
            $propertyBuilder = $typeHandler->setProperty($propertyData)->createBuilderForType();
            $propertyBuilder->setFieldName($validationSet->fieldName);

            // Apply validations to the property builder
            $typeHandler->applyValidations();

            // Handle optional properties
            if (! $validationSet->isFieldRequired()) {
                $propertyBuilder->optional();
            }

            // Handle nullable properties
            if ($validationSet->isFieldNullable()) {
                $propertyBuilder->nullable();
            }

            $objectBuilder->property($propertyName, $propertyBuilder);
        }

        return $objectBuilder;
    }

    protected function getBaseType(): string
    {
        if ($this->itemBuilder !== null) {
            return "z.array({$this->itemBuilder->build()})";
        }

        return "z.array({$this->itemType})";
    }

    /**
     * Set the array item type
     */
    public function of(string $itemType): self
    {
        $this->itemType = $itemType;
        $this->itemBuilder = null; // Clear builder if string type is set

        return $this;
    }

    /**
     * Set the array item using a ZodBuilder
     */
    public function ofBuilder(BuilderInterface $itemBuilder): self
    {
        $this->itemBuilder = $itemBuilder;
        $this->itemType = 'z.any()'; // Fallback, though builder takes precedence

        return $this;
    }

    /**
     * Add exact array length validation
     */
    public function validateLength(?array $parameters = [], ?string $message = null): self
    {
        [$length] = $parameters;
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".length({$length}{$messageStr})";

        $this->replaceRule('length', $rule);

        return $this;
    }

    /**
     * Get the current item type
     */
    public function getItemType(): string
    {
        return $this->itemType;

    }

    /**
     * Get the current item builder if set
     */
    public function getItemBuilder(): ?BuilderInterface
    {
        return $this->itemBuilder;
    }

    /**
     * Check if using a builder for item type
     */
    public function hasItemBuilder(): bool
    {
        return $this->itemBuilder !== null;
    }
}
