<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Traits;

use Mockery;
use Mockery\MockInterface;

trait Makeable
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function mock(): MockInterface
    {
        $instance = static::make();

        if ($instance instanceof MockInterface) {
            return $instance;
        }

        return tap(Mockery::getContainer()->mock(static::class), function ($instance): void {
            app()->instance(static::class, $instance);
        });
    }
}
