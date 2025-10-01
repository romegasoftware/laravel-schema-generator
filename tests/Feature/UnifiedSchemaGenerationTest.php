<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\UnifiedData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests\UnifiedValidationRequest;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;
use RomegaSoftware\LaravelSchemaGenerator\Writers\ZodTypeScriptWriter;

class UnifiedSchemaGenerationTest extends TestCase
{
    use InteractsWithExtractors;

    #[Test]
    public function it_generates_typescript_for_combined_request_and_data_schemas(): void
    {
        $requestSchema = $this->getRequestExtractor()->extract(new ReflectionClass(UnifiedValidationRequest::class));
        $dataSchema = $this->getDataExtractor()->extract(new ReflectionClass(UnifiedData::class));

        $writer = $this->app->make(ZodTypeScriptWriter::class);
        $content = $writer->generateContent([$requestSchema, $dataSchema]);

        $expected = file_get_contents(__DIR__.'/../Fixtures/Expected/unified-schemas.ts');

        $this->assertSame($expected, $content);
    }
}
