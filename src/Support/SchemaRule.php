<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Support;

use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaFragment;

final class SchemaRule
{
    public static function make(callable $callback): ClosureSchemaRule
    {
        return new ClosureSchemaRule($callback);
    }

    public static function literal(callable $callback, string $literal): ClosureSchemaRule
    {
        return self::make($callback)->replace($literal);
    }

    public static function withFragment(callable $callback, SchemaFragment $fragment): ClosureSchemaRule
    {
        return self::make($callback)->withSchemaFragment($fragment);
    }

    private function __construct() {}
}
