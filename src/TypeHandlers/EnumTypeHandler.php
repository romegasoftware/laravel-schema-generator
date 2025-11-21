<?php

namespace RomegaSoftware\LaravelSchemaGenerator\TypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\Traits\Makeable;

class EnumTypeHandler extends BaseTypeHandler
{
    use Makeable;

    public function __construct(ZodBuilderFactory $factory)
    {
        parent::__construct($factory);
    }

    public function canHandle(string $type): bool
    {
        return str_starts_with($type, 'enum:');
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return $property->validations && $this->canHandle($property->validations->inferredType);
    }

    public function handle(SchemaPropertyData $property): BuilderInterface
    {
        $type = $property->validations->inferredType;
        $validations = $property->validations;

        if (str_starts_with($type, 'enum:')) {
            // Handle enum values from validation rules
            $enumValues = substr($type, 5);
            $values = explode(',', $enumValues);
            $builder = $this->factory->createEnumBuilder()
                ->setValues($values);
        } elseif ($validations && $validations->hasValidation('in')) {
            // Handle 'in' rule with explicit values
            $inValidation = $validations->getValidation('in');
            $builder = $this->factory->createEnumBuilder()->setValues($inValidation ? $inValidation->getFirstParameter() : []);
        } else {
            // Fallback - shouldn't happen given canHandle logic
            $builder = $this->factory->createEnumBuilder();
        }

        // Handle optional
        $isOptional = $property->isOptional ?? false;
        if ($isOptional && (! $validations || ! $validations->isFieldRequired())) {
            $builder->optional();
        }

        if (! $validations) {
            return $builder;
        }

        // Check for enum error messages
        $enumMessage = null;

        if (str_starts_with($type, 'enum:')) {
            $enumMessage = $validations->getMessage('Enum');
        }

        if ($enumMessage === null && $validations->hasValidation('In')) {
            $enumMessage = $validations->getMessage('In');
        }

        if ($enumMessage !== null) {
            $builder->message($enumMessage);
        }

        // Handle nullable
        if ($validations->isFieldNullable()) {
            $builder->nullable();
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 300; // Highest priority to handle enums before other types
    }
}
