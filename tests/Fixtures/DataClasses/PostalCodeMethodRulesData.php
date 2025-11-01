<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

#[ValidationSchema]
class PostalCodeMethodRulesData extends Data
{
    public function __construct(
        public ?string $code,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(?ValidationContext $validationContext = null): array
    {
        return [
            'code' => ['string', 'regex:/^\\d{5}(-\\d{4})?$/'],
        ];
    }
}
