<?php

namespace RomegaSoftware\LaravelZodGenerator\TypeHandlers;

use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\ValidationRulesFactory;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\ValidationRulesInterface;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodArrayBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;

class ArrayTypeHandler implements TypeHandlerInterface
{
    public function __construct(
        protected ?TypeHandlerRegistry $registry = null
    ) {}

    public function canHandle(string $type): bool
    {
        return $type === 'array' || str_starts_with($type, 'array:');
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return $this->canHandle($property->type);
    }

    public function handle(SchemaPropertyData $property): ZodBuilder
    {
        $type = $property->type;
        $validations = $property->validations;

        // Determine array item type
        if (str_starts_with($type, 'array:')) {
            $itemType = substr($type, 6);
            $itemZodType = $this->buildArrayItemType($itemType, $validations);
        } elseif ($validations && $validations->hasValidation('arrayItemValidations')) {
            $itemZodType = $this->buildArrayItemType('string', $validations);
        } else {
            $itemZodType = 'z.any()';
        }

        $builder = new ZodArrayBuilder($itemZodType);

        // Handle optional
        if ($property->isOptional && (! $validations || ! $validations->isRequired())) {
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

        // Add required validation (non-empty array)
        if ($validations->isRequired()) {
            $message = $validations->getCustomMessage('required');
            $builder->nonEmpty($message);
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

    /**
     * Extract item validations from array validations using ValidationRulesFactory
     */
    protected function extractItemValidations(?ValidationRulesInterface $arrayValidations, string $itemType): ?ValidationRulesInterface
    {
        if (! $arrayValidations || ! $arrayValidations->hasValidation('arrayItemValidations')) {
            return null;
        }

        $itemValidations = $arrayValidations->getValidation('arrayItemValidations');

        // Transform wildcard custom messages (*.key) to direct key format for item validations
        $transformedValidations = $itemValidations;
        if (isset($itemValidations['customMessages'])) {
            $transformedMessages = [];
            foreach ($itemValidations['customMessages'] as $key => $message) {
                // Convert "*.regex" to "regex" for item validation
                $transformedKey = str_starts_with($key, '*.') ? substr($key, 2) : $key;
                $transformedMessages[$transformedKey] = $message;
            }
            $transformedValidations['customMessages'] = $transformedMessages;
        }

        // Use ValidationRulesFactory to create proper ValidationRules object
        return ValidationRulesFactory::create($itemType, $transformedValidations);
    }

    /**
     * Build array item type using TypeHandlerRegistry
     */
    protected function buildArrayItemType(string $itemType, ?ValidationRulesInterface $arrayValidations): string
    {
        // If we have a registry, try to use it for proper type handling
        if ($this->registry) {
            // Extract item validations from array validations
            $itemValidations = $this->extractItemValidations($arrayValidations, $itemType);

            // Create a mock property for the array item
            $itemProperty = new SchemaPropertyData(
                name: 'arrayItem',
                type: $itemType,
                isOptional: false, // Array items are not optional by default
                validations: $itemValidations
            );

            // Get the appropriate handler from registry
            $handler = $this->registry->getHandlerForProperty($itemProperty);

            if ($handler) {
                $builder = $handler->handle($itemProperty);

                return $builder->build();
            }
        }

        // Fallback to basic type mapping if no registry or handler found
        return match ($itemType) {
            'string' => 'z.string()',
            'number' => 'z.number()',
            'boolean' => 'z.boolean()',
            default => 'z.any()',
        };
    }
}
