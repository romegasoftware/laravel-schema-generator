<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Data;

#[ValidationSchema]
class InheritedPostalCodeData extends Data
{
    public function __construct(
        #[InheritValidationFrom(PostalCodeRulesData::class)]
        public string $postal_code,
    ) {}
}
