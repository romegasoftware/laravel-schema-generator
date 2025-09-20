<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

#[ValidationSchema]
class FeeConfigurationData extends Data
{
    public function __construct(
        public Optional|FeeStructureData $platform_card_fees,
        public Optional|FeeStructureData $platform_ach_fees,
        public Optional|FeeStructureData $stripe_card_fees,
        public Optional|FeeStructureData $stripe_ach_fees,
        public Optional|bool $is_default = true,
    ) {}
}
