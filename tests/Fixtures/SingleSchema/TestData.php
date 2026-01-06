<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\SingleSchema;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

#[ValidationSchema]
class TestData extends Data
{
    public function __construct(
        #[StringType]
        public string $name,
    ) {}
}
