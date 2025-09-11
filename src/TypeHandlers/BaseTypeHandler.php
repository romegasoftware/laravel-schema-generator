<?php

namespace RomegaSoftware\LaravelSchemaGenerator\TypeHandlers;

use Illuminate\Support\Traits\Macroable;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\Traits\Makeable;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodEnumBuilder;

abstract class BaseTypeHandler implements TypeHandlerInterface
{
    use Macroable;
    use Makeable;

    public ?SchemaPropertyData $property;

    public ?BuilderInterface $builder;

    public function __construct(
        protected ZodBuilderFactory $factory
    ) {
    }

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
            $type === 'boolean' => $this->factory->createBooleanBuilder(),
            $type === 'number' => $this->factory->createNumberBuilder(),
            $type === 'array' => $this->factory->createArrayBuilder()
                ->setProperty($this->property)
                ->createArrayBuilder(),
            $type === 'object' => $this->createObjectBuilderFromProperty(),
            $type === 'email' => $this->factory->createEmailBuilder(),
            str_starts_with($type, 'enum:') => $this->factory->createEnumBuilder()->setValues($type),
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
                $nestedProperty = new \RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData(
                    name: $propName,
                    validator: $this->property->validator,
                    isOptional: ! $propValidation->isFieldRequired(),
                    validations: $propValidation
                );

                // Use UniversalTypeHandler to handle the nested property
                $handler = new \RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler($this->factory);
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
            match ($validation->rule) {
                // String validations
                'min' => $this->callBuilderMethod('min', [$validation->getFirstParameter(), $message]),
                'max' => $this->callBuilderMethod('max', [$validation->getFirstParameter(), $message]),
                'size' => $this->callBuilderMethod('length', [$validation->getFirstParameter(), $message]),
                'regex' => $this->applyRegexValidation($validation->parameters, $message),
                'email' => $this->callBuilderMethod('email', [$message]),
                'url' => $this->callBuilderMethod('url', [$message]),
                'uuid' => $this->callBuilderMethod('uuid', [$message]),
                'confirmed' => $this->callBuilderMethod('confirmed', [$message]),

                // Number validations
                'integer' => $this->callBuilderMethod('integer', [$message]),
                'numeric' => null, // Already handled by type inference
                'positive' => $this->callBuilderMethod('positive', [$message]),
                'negative' => $this->callBuilderMethod('negative', [$message]),
                'finite' => $this->callBuilderMethod('finite', [$message]),
                'gte' => $this->callBuilderMethod('gte', [$validation->getFirstParameter(), $message]),
                'lte' => $this->callBuilderMethod('lte', [$validation->getFirstParameter(), $message]),
                'gt' => $this->callBuilderMethod('gt', [$validation->getFirstParameter(), $message]),
                'lt' => $this->callBuilderMethod('lt', [$validation->getFirstParameter(), $message]),
                'multiple_of' => $this->callBuilderMethod('multipleOf', [$validation->getFirstParameter(), $message]),
                'decimal' => $this->applyDecimalValidation($validation->parameters, $message),
                'digits' => $this->callBuilderMethod('digits', [$validation->getFirstParameter(), $message]),
                'digits_between' => $this->applyDigitsBetweenValidation($validation->parameters, $message),
                'max_digits' => $this->callBuilderMethod('maxDigits', [$validation->getFirstParameter(), $message]),
                'min_digits' => $this->callBuilderMethod('minDigits', [$validation->getFirstParameter(), $message]),

                // Array validations
                'array' => null, // Already handled by type inference

                // Boolean validations
                'boolean' => null, // Already handled by type inference

                // Enum validations
                'in' => $this->applyInValidation($validation->parameters, $message),

                // Generic validation - pass through if Laravel accepts it
                default => $this->applyGenericValidation($validation->rule, $validation->parameters, $message),
            };
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
     * Apply regex validation with pattern conversion
     */
    public function applyRegexValidation(array $parameters, ?string $customMessage): void
    {
        if (empty($parameters[0])) {
            return;
        }

        $pattern = $this->convertPhpRegexToJavaScript($parameters[0]);
        $this->callBuilderMethod('regex', [$pattern, $customMessage]);
    }

    /**
     * Convert PHP regex to JavaScript regex
     */
    public function convertPhpRegexToJavaScript(string $phpRegex): string
    {
        // Remove the delimiters (first and last character) if they exist
        if (preg_match('/^\/.*\/$/', $phpRegex)) {
            $pattern = substr($phpRegex, 1, -1);
        } else {
            $pattern = $phpRegex;
        }

        // In JavaScript, dots don't need escaping inside character classes
        $pattern = preg_replace_callback(
            '/\[[^\]]*\]/',
            fn ($matches) => str_replace('\.', '.', $matches[0]),
            $pattern
        );

        // Return as a JavaScript regex literal
        return '/'.$pattern.'/';
    }

    /**
     * Apply in/enum validation
     */
    public function applyInValidation(array $parameters, ?string $customMessage): void
    {
        if ($this->builder instanceof ZodEnumBuilder && ! empty($parameters)) {
            // Enum builder handles this automatically
            return;
        }

        // For other builders, we can't easily add enum validation, so skip
        // This would require a more complex transformation to ZodEnumBuilder
    }

    /**
     * Apply generic validation by checking if the builder supports the method
     */
    public function applyGenericValidation(string $rule, array $parameters, ?string $customMessage): void
    {
        // Try to call a method with the rule name
        if (method_exists($this->builder, $rule)) {
            $args = array_merge($parameters, [$customMessage]);
            $this->callBuilderMethod($rule, $args);
        }

        // Note: This allows for extensibility - custom builders can implement additional methods
        // and they will be automatically called if they exist
    }

    /**
     * Apply decimal validation
     */
    public function applyDecimalValidation(array $parameters, ?string $customMessage): void
    {
        if (empty($parameters[0])) {
            return;
        }

        $min = (int) $parameters[0];
        $max = isset($parameters[1]) ? (int) $parameters[1] : null;

        $this->callBuilderMethod('decimal', [$min, $max, $customMessage]);
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
}
