<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Optional;

#[ValidationSchema]
class AvailabilityRulesContainerData extends Data
{
    public function __construct(
        #[DataCollectionOf(AvailabilityRulePayloadData::class)]
        #[InheritValidationFrom(AvailabilityRulePayloadData::class)]
        public null|Optional|DataCollection $availability_rules = null
    ) {}
}
