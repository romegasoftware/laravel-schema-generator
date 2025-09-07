<?php

namespace RomegaSoftware\LaravelZodGenerator\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RomegaSoftware\LaravelZodGenerator\LaravelZodGeneratorServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            \Spatie\LaravelData\LaravelDataServiceProvider::class,
            LaravelZodGeneratorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Set up test configuration
        $app['config']->set('laravel-zod-generator.scan_paths', [
            __DIR__.'/Fixtures',
        ]);

        $app['config']->set('laravel-zod-generator.output.path',
            __DIR__.'/temp/zod-schemas.ts'
        );
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $tempDir = __DIR__.'/temp';
        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }

        parent::tearDown();
    }

    protected function deleteDirectory($dir)
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
