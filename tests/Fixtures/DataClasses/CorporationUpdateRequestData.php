<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

#[ValidationSchema]
class CorporationUpdateRequestData extends Data
{
    public function __construct(
        public Optional|FeeConfigurationData|null $fee_configuration = null,
    ) {}
}
