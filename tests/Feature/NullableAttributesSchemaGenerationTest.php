<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\DataClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\NullableAttributesData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class NullableAttributesSchemaGenerationTest extends TestCase
{
    protected DataClassExtractor $extractor;

    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = $this->app->make(DataClassExtractor::class);
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_generates_schema_for_nullable_fields(): void
    {
        (new NullableAttributesData)->getValidationRules([]);
        $extracted = $this->extractor->extract(new \ReflectionClass(objectOrClass: NullableAttributesData::class));

        $this->assertInstanceOf(ExtractedSchemaData::class, $extracted);
        $this->assertEquals('NullableAttributesDataSchema', $extracted->name);

        $schema = $this->generator->generate($extracted);

        // Check that the schema contains file validations
        $this->assertStringContainsString('z.object({', $schema);

        // Check that amount field is present with nullable and min validation
        $this->assertStringContainsString('amount:', $schema);
        $this->assertStringContainsString('z.number(', $schema);
        $this->assertStringContainsString('.min(1,', $schema);
        $this->assertStringContainsString('.nullable()', $schema);
        $this->assertStringContainsString('.optional()', $schema);
        
        // Check that reason field is present with nullable and max validation
        $this->assertStringContainsString('reason:', $schema);
        $this->assertStringContainsString('z.string()', $schema);
        $this->assertStringContainsString('.max(255,', $schema);
        $this->assertStringContainsString('.nullable()', $schema);
    }
}
