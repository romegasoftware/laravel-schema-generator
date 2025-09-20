<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

#[ValidationSchema]
class FeeStructureData extends Data
{
    public function __construct(
        #[Required, Numeric]
        public Optional|float $percentage,
        #[Required, Numeric]
        public Optional|int $fixed_cents,
        #[Numeric]
        public Optional|int|null $cap_cents,
    ) {}
}
