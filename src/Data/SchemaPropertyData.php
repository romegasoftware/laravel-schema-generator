<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;

class SchemaPropertyData extends Data
{
    public function __construct(
        public string $name,
        public ?Validator $validator,
        public bool $isOptional,
        public ?ResolvedValidationSet $validations,
    ) {}
}
