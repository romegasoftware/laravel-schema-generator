<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

#[ValidationSchema]
class NullableAttributesData extends Data
{
    public function __construct(
        #[Nullable, IntegerType, Min(1)]
        public ?int $amount = null,

        #[Nullable, StringType, Max(255)]
        public ?string $reason = null,
    ) {}
}
