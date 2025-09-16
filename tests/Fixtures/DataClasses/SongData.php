<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

#[ValidationSchema]
#[MergeValidationRules]
class SongData extends Data
{
    public function __construct(
        #[Max(20)]
        public string $title,
        public ?string $artist,

        // Test that we are mapping rules to the correct input name
        #[MapName('song_meta_data_custom_name')]
        public SongMetaData $metaData,

        // Testing setting the DataCollection from attributes, with array
        #[DataCollectionOf(TestUserData::class)]
        public array $producers,
    ) {}

    public static function rules($context = null): array
    {
        return [
            'title' => ['max:20'],
            'artist' => ['required'],
        ];
    }
}
