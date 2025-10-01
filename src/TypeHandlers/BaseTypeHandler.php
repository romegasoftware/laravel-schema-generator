<?php

namespace RomegaSoftware\LaravelSchemaGenerator\TypeHandlers;

use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\Traits\Makeable;

abstract class BaseTypeHandler implements TypeHandlerInterface
{
    use Macroable;
    use Makeable;

    public ?SchemaPropertyData $property;

    public ?BuilderInterface $builder;

    public function __construct(
        protected ZodBuilderFactory $factory
    ) {}

    public function setProperty(SchemaPropertyData $schemaProperty): self
    {
        $this->property = $schemaProperty;

        return $this;
    }

    /**
     * Create appropriate ZodBuilder based on inferred type
     */
    public function createBuilderForType(): BuilderInterface
    {
        $type = $this->property->validations->inferredType;

        $this->builder = match (true) {
            $type === 'file' => $this->factory->createFileBuilder(),
            $type === 'boolean' => $this->factory->createBooleanBuilder(),
            $type === 'number' => $this->factory->createNumberBuilder(),
            $type === 'password' => $this->factory->createPasswordBuilder(),
            $type === 'array' => $this->factory->createArrayBuilder()
                ->setProperty($this->property)
                ->setup(),
            $type === 'object' => $this->createObjectBuilderFromProperty(),
            $type === 'email' => $this->factory->createEmailBuilder(),
            $type === 'url' => $this->factory->createUrlBuilder(),
            str_starts_with($type, 'enum:') => $this->factory->createEnumBuilder()
                ->setValues($type),
            default => $this->factory->createStringBuilder(),
        };

        return $this->builder;
    }

    /**
     * Create an object builder from property's objectProperties
     */
    protected function createObjectBuilderFromProperty(): BuilderInterface
    {
        $objectBuilder = $this->factory->createInlineObjectBuilder();

        // Check if we have objectProperties to add
        if ($this->property->validations?->objectProperties) {
            foreach ($this->property->validations->objectProperties as $propName => $propValidation) {
                // Skip flattened properties with dots in their names for TypeScript generation
                // These are used for internal validation structure but shouldn't appear in the schema
                if (str_contains($propName, '.')) {
                    continue;
                }

                // Create a new SchemaPropertyData for the nested property
                $nestedProperty = new SchemaPropertyData(
                    name: $propName,
                    validator: $this->property->validator,
                    isOptional: ! $propValidation->isFieldRequired(),
                    validations: $propValidation
                );

                // Use UniversalTypeHandler to handle the nested property
                $handler = new UniversalTypeHandler($this->factory);
                $nestedBuilder = $handler->handle($nestedProperty);

                $objectBuilder->property($propName, $nestedBuilder);
            }
        }

        return $objectBuilder;
    }

    /**
     * Dynamically apply all validation rules to the builder
     */
    public function applyValidations(): void
    {
        // Apply the validations from the current property
        foreach ($this->property->validations->validations as $validation) {
            $this->applyValidation($validation);
        }
    }

    /**
     * Apply a specific validation rule to the builder
     */
    public function applyValidation(ResolvedValidation $validation): void
    {
        // Skip meta-rules that are handled elsewhere
        if (in_array($validation->rule, ['required', 'nullable', 'optional'])) {
            return;
        }

        $message = $validation->message;

        try {
            $this->applyGenericValidation($validation->rule, $validation->parameters, $message);
        } catch (\Throwable $e) {
            // Silently skip validations that can't be applied - this allows for extensibility
            // Laravel may have validation rules that don't have Zod equivalents yet
        }
    }

    /**
     * Safely call a method on the builder if it exists
     */
    public function callBuilderMethod(string $method, array $args): void
    {
        if (method_exists($this->builder, $method)) {
            $this->builder->$method(...$args);
        }
    }

    /**
     * Apply generic validation by checking if the builder supports the method
     */
    public function applyGenericValidation(string $rule, array $parameters, ?string $customMessage): void
    {
        $formattedMethod = Str::of(ucfirst(
            Str::camel(str_replace('.', '_', $rule))
        ))->prepend('validate');

        // Try to call a method with the rule name
        if (method_exists(object_or_class: $this->builder, method: $formattedMethod)) {
            $args = array_merge([$parameters], [$customMessage]);
            $this->callBuilderMethod($formattedMethod, $args);
        }
    }

    /**
     * Apply digits_between validation
     */
    public function applyDigitsBetweenValidation(array $parameters, ?string $customMessage): void
    {
        if (empty($parameters[0]) || empty($parameters[1])) {
            return;
        }

        $min = (int) $parameters[0];
        $max = (int) $parameters[1];

        $this->callBuilderMethod('digitsBetween', [$min, $max, $customMessage]);
    }

    /**
     * Apply between validation (for files or numbers)
     */
    protected function applyBetweenValidation(array $parameters, ?string $customMessage): void
    {
        if (empty($parameters[0]) || empty($parameters[1])) {
            return;
        }

        $min = (int) $parameters[0];
        $max = (int) $parameters[1];

        $this->callBuilderMethod('between', [$min, $max, $customMessage]);
    }

    /**
     * Apply mimes validation
     */
    protected function applyMimesValidation(array $parameters, ?string $customMessage): void
    {
        if (empty($parameters)) {
            return;
        }

        // The parameters are already extensions, convert them to MIME types
        $mimeTypes = $this->convertExtensionsToMimeTypes($parameters);

        // If no MIME types were mapped, use the extensions as-is (they might already be MIME types)
        if (empty($mimeTypes)) {
            // Try to use them as MIME types directly if they contain /
            foreach ($parameters as $param) {
                if (str_contains($param, '/')) {
                    $mimeTypes[] = $param;
                }
            }
        }

        if (! empty($mimeTypes)) {
            $this->callBuilderMethod('mimes', [$mimeTypes, $customMessage]);
        }
    }

    /**
     * Apply mimetypes validation
     */
    protected function applyMimetypesValidation(array $parameters, ?string $customMessage): void
    {
        if (empty($parameters)) {
            return;
        }

        $this->callBuilderMethod('mimetypes', [$parameters, $customMessage]);
    }

    /**
     * Apply extensions validation
     */
    protected function applyExtensionsValidation(array $parameters, ?string $customMessage): void
    {
        if (empty($parameters)) {
            return;
        }

        $this->callBuilderMethod('extensions', [$parameters, $customMessage]);
    }
}
