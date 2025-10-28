<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\MultipleOf;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

#[ValidationSchema]
class OrderItemRequestData extends Data
{
    public function __construct(
        #[Required, IntegerType]
        public int $item_id,

        #[Required, IntegerType, Min(1), MultipleOf(3)]
        public int $quantity,
    ) {}
}
