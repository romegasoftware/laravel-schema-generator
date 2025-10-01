<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data;

use Illuminate\Validation\Validator;

class SchemaPropertyData
{
    public function __construct(
        public string $name,
        public ?Validator $validator,
        public bool $isOptional,
        public ?ResolvedValidationSet $validations,
    ) {}

    /**
     * Create a collection of schema properties without depending on Spatie Data.
     * Accepts arrays for backward compatibility with previous Data::collect behaviour.
     *
     * @param  iterable<SchemaPropertyData|array>  $properties
     */
    public static function collect(iterable $properties = []): SchemaPropertyCollection
    {
        $items = [];

        foreach ($properties as $property) {
            if ($property instanceof self) {
                $items[] = $property;

                continue;
            }

            if (is_array($property)) {
                if (! array_key_exists('name', $property)) {
                    throw new \InvalidArgumentException('SchemaPropertyData array definition must contain a name.');
                }

                $items[] = new self(
                    name: $property['name'],
                    validator: $property['validator'] ?? null,
                    isOptional: $property['isOptional'] ?? false,
                    validations: $property['validations'] ?? null,
                );

                continue;
            }

            throw new \InvalidArgumentException('SchemaPropertyData::collect expects instances of SchemaPropertyData or associative arrays.');
        }

        return SchemaPropertyCollection::make($items);
    }
}
