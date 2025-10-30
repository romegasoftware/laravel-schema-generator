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

    /**
     * @return MockInterface&static
     */
    public static function mock(): MockInterface
    {
        $instance = static::make();

        if ($instance instanceof MockInterface) {
            return $instance;
        }

        /** @var MockInterface&static $mock */
        $mock = Mockery::getContainer()->mock(static::class);
        app()->instance(static::class, $mock);

        return $mock;
    }
}
