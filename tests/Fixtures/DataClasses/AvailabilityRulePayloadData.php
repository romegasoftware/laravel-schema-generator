<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

#[ValidationSchema]
class AvailabilityRulePayloadData extends Data
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        #[Nullable, Numeric]
        public null|Optional|int $id = null,
        #[StringType]
        public string $type = 'months_of_year',
        #[ArrayType]
        public array $config = [],
    ) {}

    public static function rules($validationContext = null): array
    {
        return [
            'config' => ['required', 'array'],
            'config.months' => ['required_if:type,months_of_year', 'array'],
            'config.months.*' => ['integer', 'between:1,12'],
        ];
    }
}
