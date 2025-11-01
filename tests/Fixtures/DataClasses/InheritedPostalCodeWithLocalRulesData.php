<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

#[ValidationSchema]
class InheritedPostalCodeWithLocalRulesData extends Data
{
    public function __construct(
        #[InheritValidationFrom(PostalCodeMethodRulesData::class, 'code')]
        public ?string $postal_code,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(?ValidationContext $validationContext = null): array
    {
        return [
            'postal_code' => ['required'],
        ];
    }
}
