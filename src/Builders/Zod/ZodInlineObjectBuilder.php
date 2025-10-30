<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
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
        if (! isset($this->property) || $this->property->validations === null) {
            throw new \RuntimeException('SchemaPropertyData with resolved validations is required to create object builder properties.');
        }

        $objectBuilder = new ZodInlineObjectBuilder($this->universalTypeHandler);
        $objectProperties = $this->property->validations->getObjectProperties();

        if (empty($objectProperties)) {
            return $objectBuilder;
        }

        foreach ($objectProperties as $propertyName => $validationSet) {
            /** @var ResolvedValidationSet $validationSet */
            if ($this->universalTypeHandler === null) {
                throw new \RuntimeException('UniversalTypeHandler must be injected to create object properties');
            }

            $nestedProperty = new SchemaPropertyData(
                name: $validationSet->fieldName,
                validator: $this->property->validator,
                isOptional: ! $validationSet->isFieldRequired(),
                validations: $validationSet,
                schemaOverride: null,
            );

            $universalTypeHandler = clone $this->universalTypeHandler;
            $nestedBuilder = $universalTypeHandler->handle($nestedProperty);

            $objectBuilder->property($propertyName, $nestedBuilder);
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
