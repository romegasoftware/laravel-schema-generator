<?php

namespace RomegaSoftware\LaravelZodGenerator\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class ExtractedSchemaData extends Data
{
    public function __construct(
        public string $name,
        #[DataCollectionOf(SchemaPropertyData::class)]
        public ?DataCollection $properties,
        public string $className,
        public string $type,
        /** @var string[] */
        public array $dependencies = [],
    ) {}
}
