<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\UnifiedData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class NestedSchemaGenerationTest extends TestCase
{
    use InteractsWithExtractors;

    #[Test]
    public function it_generates_nested_objects_and_collections_for_unified_data_class(): void
    {
        $reflection = new ReflectionClass(UnifiedData::class);
        $extracted = $this->getDataExtractor()->extract($reflection);

        $schema = $this->app->make(ValidationSchemaGenerator::class)->generate($extracted);

        $this->assertStringContainsString('account_details: z.object({', $schema);
        $this->assertStringContainsString('address: z.object({', $schema);
        $this->assertStringContainsString('projects: z.array(z.object({', $schema);
        $this->assertStringContainsString('metrics: z.array(z.object({', $schema);
        $this->assertStringContainsString('value: z.number({ error:', $schema);
        $this->assertStringContainsString('starts_at: z.string({ error:', $schema);
        $this->assertStringContainsString('.nullable()', $schema, 'Optional nested fields should emit nullable modifier');
    }
}
