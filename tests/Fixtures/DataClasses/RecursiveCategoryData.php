<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

#[ValidationSchema]
class RecursiveCategoryData extends Data
{
    public function __construct(
        #[Required]
        public string $name,
        #[DataCollectionOf(self::class)]
        public ?DataCollection $children = null,
    ) {}
}
