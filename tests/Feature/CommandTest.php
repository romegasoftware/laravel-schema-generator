<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class CommandTest extends TestCase
{
    #[Test]
    public function it_writes_combined_schemas_to_configured_output(): void
    {
        config(['laravel-schema-generator.scan_paths' => [
            __DIR__.'/../Fixtures/FormRequests',
            __DIR__.'/../Fixtures/DataClasses',
        ]]);

        $this->artisan('schema:generate')->assertExitCode(0);

        $outputPath = config('laravel-schema-generator.zod.output.path');
        $this->assertFileExists($outputPath);

        $content = file_get_contents($outputPath);
        $this->assertStringContainsString('export const UnifiedValidationRequestSchema', $content);
        $this->assertStringContainsString('export const UnifiedDataSchema', $content);
    }
}
