<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\OrderCreateRequestData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\OrderItemRequestData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;
use RomegaSoftware\LaravelSchemaGenerator\Writers\ZodTypeScriptWriter;

class SeparateFilesWithDependenciesTest extends TestCase
{
    use InteractsWithExtractors;

    protected string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = sys_get_temp_dir().'/laravel-schema-generator-test';

        // Clean output directory
        if (File::isDirectory($this->outputPath)) {
            File::deleteDirectory($this->outputPath);
        }
        File::makeDirectory($this->outputPath, 0755, true);

        // Configure for separate files mode
        config(['laravel-schema-generator.zod.output.separate_files' => true]);
        config(['laravel-schema-generator.zod.output.directory' => $this->outputPath]);
    }

    protected function tearDown(): void
    {
        // Clean up after test
        if (File::isDirectory($this->outputPath)) {
            File::deleteDirectory($this->outputPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_generates_import_for_inherit_validation_from_attribute(): void
    {
        // Extract both schemas
        $itemExtracted = $this->getDataExtractor()->extract(new ReflectionClass(OrderItemRequestData::class));
        $orderExtracted = $this->getDataExtractor()->extract(new ReflectionClass(OrderCreateRequestData::class));

        // Write to separate files
        /** @var ZodTypeScriptWriter $writer */
        $writer = $this->app->make(ZodTypeScriptWriter::class);
        $writer->write([$itemExtracted, $orderExtracted]);

        // Check that OrderCreateRequestDataSchema.ts exists
        $orderSchemaPath = $this->outputPath.'/OrderCreateRequestDataSchema.ts';
        $this->assertFileExists($orderSchemaPath);

        // Check that it contains the import statement
        $orderSchemaContent = File::get($orderSchemaPath);

        $this->assertStringContainsString(
            "import { OrderItemRequestDataSchema } from './OrderItemRequestDataSchema'",
            $orderSchemaContent,
            'OrderCreateRequestDataSchema should import OrderItemRequestDataSchema'
        );

        $this->assertStringContainsString(
            'z.array(OrderItemRequestDataSchema)',
            $orderSchemaContent,
            'OrderCreateRequestDataSchema should use OrderItemRequestDataSchema in array definition'
        );
    }

    #[Test]
    public function it_prevents_self_imports(): void
    {
        // Extract schema
        $extracted = $this->getDataExtractor()->extract(new ReflectionClass(OrderItemRequestData::class));

        // Write to separate file
        /** @var ZodTypeScriptWriter $writer */
        $writer = $this->app->make(ZodTypeScriptWriter::class);
        $writer->write([$extracted]);

        // Check that schema doesn't import itself
        $schemaPath = $this->outputPath.'/OrderItemRequestDataSchema.ts';
        $schemaContent = File::get($schemaPath);

        $this->assertStringNotContainsString(
            "import { OrderItemRequestDataSchema } from './OrderItemRequestDataSchema'",
            $schemaContent,
            'Schema should not import itself'
        );
    }

    #[Test]
    public function it_handles_nested_dependencies(): void
    {
        // Extract both schemas
        $itemExtracted = $this->getDataExtractor()->extract(new ReflectionClass(OrderItemRequestData::class));
        $orderExtracted = $this->getDataExtractor()->extract(new ReflectionClass(OrderCreateRequestData::class));

        // Write to separate files
        /** @var ZodTypeScriptWriter $writer */
        $writer = $this->app->make(ZodTypeScriptWriter::class);
        $writer->write([$itemExtracted, $orderExtracted]);

        // Both files should exist
        $this->assertFileExists($this->outputPath.'/OrderItemRequestDataSchema.ts');
        $this->assertFileExists($this->outputPath.'/OrderCreateRequestDataSchema.ts');

        // OrderCreateRequestData depends on OrderItemRequestData
        $orderSchemaContent = File::get($this->outputPath.'/OrderCreateRequestDataSchema.ts');

        // Should have exactly one import for OrderItemRequestDataSchema
        $importCount = substr_count($orderSchemaContent, "import { OrderItemRequestDataSchema } from './OrderItemRequestDataSchema'");
        $this->assertEquals(1, $importCount, 'Should have exactly one import statement');
    }
}
