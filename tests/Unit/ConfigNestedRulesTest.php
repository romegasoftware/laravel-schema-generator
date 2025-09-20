<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\AvailabilityRulePayloadData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class ConfigNestedRulesTest extends TestCase
{
    use InteractsWithExtractors;

    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_groups_dot_notation_children_under_parent_object(): void
    {
        $extracted = $this->getDataExtractor()->extract(new ReflectionClass(AvailabilityRulePayloadData::class));
        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('config: z.object({', $schema);
        $this->assertStringContainsString('months: z.array(z.number({', $schema);
        $this->assertStringContainsString('.min(1', $schema);
        $this->assertStringContainsString('.max(12', $schema);
        $this->assertStringNotContainsString('config.months:', $schema);
        $this->assertStringNotContainsString('characters', $schema);
    }
}
