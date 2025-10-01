<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data;

class ExtractedSchemaData
{
    public function __construct(
        public string $name,
        public ?SchemaPropertyCollection $properties,
        public string $className,
        public string $type,
        /** @var string[] */
        public array $dependencies = [],
    ) {}
}
