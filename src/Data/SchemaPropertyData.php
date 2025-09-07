<?php

namespace RomegaSoftware\LaravelZodGenerator\Data;

use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\ValidationRulesInterface;
use Spatie\LaravelData\Data;

class SchemaPropertyData extends Data
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $isOptional,
        public ?ValidationRulesInterface $validations,
    ) {}
}
