<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\AvailabilityRulesContainerData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class AvailabilityRulesInheritanceTest extends TestCase
{
    use InteractsWithExtractors;

    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_builds_nested_config_object_when_inherited_through_collection(): void
    {
        $extracted = $this->getDataExtractor()->extract(new ReflectionClass(AvailabilityRulesContainerData::class));
        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('availability_rules: z.array(z.object({', $schema);
        $this->assertStringContainsString('config: z.object({', $schema);
        $this->assertStringContainsString('months: z.array(z.number({', $schema);
        $this->assertStringNotContainsString('config.months:', $schema);
    }
}
