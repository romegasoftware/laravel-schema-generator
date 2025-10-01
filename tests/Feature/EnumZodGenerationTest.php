<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests\UnifiedValidationRequest;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class EnumZodGenerationTest extends TestCase
{
    use InteractsWithExtractors;

    protected RequestClassExtractor $extractor;

    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_generates_z_enum_for_laravel_enum_rule(): void
    {
        $reflection = new \ReflectionClass(UnifiedValidationRequest::class);
        $extracted = $this->getRequestExtractor()->extract($reflection);
        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('status: z.enum(["pending", "approved", "rejected"], { message: "The metadata.status field is required." })', $schema,
            'Should generate z.enum() for string in rule');
        $this->assertStringNotContainsString('status: z.enum(["pending", "approved", "rejected"], { message: "The metadata.status field is required." }).optional()', $schema,
            'Required status field should not be optional');
    }
}
