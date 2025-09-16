<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;

#[ValidationSchema]
#[MergeValidationRules]
class SongMetaData extends Data
{
    public function __construct(
        public int $lengthInSeconds,

        // Test Enum casting
        public FileFormat $fileFormat,
    ) {}

    public static function rules($context = null): array
    {
        return [
            'lengthInSeconds' => ['required', 'min:10', 'max:300'],
        ];
    }

    public static function messages(...$args): array
    {
        return [
            'lengthInSeconds.min' => 'Song length must be greater than 10 seconds',
            'lengthInSeconds.max' => 'Song length must be less than 5 minutes',
        ];
    }
}
