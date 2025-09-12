<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;

/**
 * Builder for inline object definitions like z.object({ title: z.string() })
 * Used for array items that have multiple properties
 */
class ZodInlineObjectBuilder extends ZodBuilder
{
    public function __construct(
        private ?UniversalTypeHandler $universalTypeHandler = null
    ) {}

    protected array $properties = [];

    /**
     * Add a property to the object
     */
    public function property(string $name, BuilderInterface $builder): self
    {
        $this->properties[$name] = $builder;

        return $this;
    }

    /**
     * Create an object builder from resolved validation sets for object properties
     */
    public function createObjectBuilderFromProperty(): ZodInlineObjectBuilder
    {
        $objectBuilder = new ZodInlineObjectBuilder;

        foreach ($this->property->toArray() as $propertyName => $validationSet) {
            /** @var ResolvedValidationSet $validationSet */
            if ($this->universalTypeHandler === null) {
                throw new \RuntimeException('UniversalTypeHandler must be injected to create object properties');
            }
            $universalTypeHandler = clone $this->universalTypeHandler;
            $universalTypeHandler->setProperty($this->property);
            $universalTypeHandler->builder->setFieldName($validationSet->fieldName);

            // Apply validations to the property builder
            $universalTypeHandler->applyValidations();

            // Handle optional properties
            if (! $validationSet->isFieldRequired()) {
                $universalTypeHandler->builder->optional();
            }

            // Handle nullable properties
            if ($validationSet->isFieldNullable()) {
                $universalTypeHandler->builder->nullable();
            }

            $objectBuilder->property($propertyName, $universalTypeHandler->builder);
        }

        return $objectBuilder;
    }

    /**
     * Set all properties at once
     */
    public function properties(array $properties): self
    {
        $this->properties = $properties;

        return $this;
    }

    protected function getBaseType(): string
    {
        if (empty($this->properties)) {
            return 'z.object({})';
        }

        $propertyStrings = [];
        foreach ($this->properties as $name => $builder) {
            $propertyStrings[] = "{$name}: {$builder->build()}";
        }

        $propertiesString = implode(', ', $propertyStrings);

        return "z.object({ {$propertiesString} })";
    }

    /**
     * Get all properties
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Check if the object has any properties
     */
    public function hasProperties(): bool
    {
        return ! empty($this->properties);
    }
}
