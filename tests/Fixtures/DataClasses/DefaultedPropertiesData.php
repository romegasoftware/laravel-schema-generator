<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

#[ValidationSchema]
class DefaultedPropertiesData extends Data
{
    public function __construct(
        #[BooleanType]
        public bool $enabled = false,

        #[Required, BooleanType]
        public bool $confirmed = false,
    ) {}
}
