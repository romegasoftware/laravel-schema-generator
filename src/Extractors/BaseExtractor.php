<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use Illuminate\Support\Traits\Macroable;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\ExtractorInterface;

abstract class BaseExtractor implements ExtractorInterface
{
    use Macroable;
}
