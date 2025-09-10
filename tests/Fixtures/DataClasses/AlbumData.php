<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

#[ValidationSchema]
#[MergeValidationRules]
class AlbumData extends Data
{
    /**
     * Testing setting the DataCollection from param hints
     *
     * @param  DataCollection<SongData>  $songs
     */
    public function __construct(
        public string $album_title,
        public DataCollection $songs,
    ) {}
}
