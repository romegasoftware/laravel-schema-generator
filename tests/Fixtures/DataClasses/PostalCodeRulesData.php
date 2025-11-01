<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

#[ValidationSchema]
class PostalCodeRulesData extends Data
{
    public function __construct(
        #[Required, StringType, Regex('/^\d{5}(-\d{4})?$/')]
        public string $postal_code,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'postal_code.regex' => 'Postal code must look like 12345 or 12345-6789',
        ];
    }
}
