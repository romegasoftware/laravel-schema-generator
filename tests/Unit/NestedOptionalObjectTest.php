<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\CorporationUpdateRequestData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class NestedOptionalObjectTest extends TestCase
{
    use InteractsWithExtractors;

    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_preserves_nested_validations_on_optional_data_objects(): void
    {
        $extracted = $this->getDataExtractor()->extract(new ReflectionClass(CorporationUpdateRequestData::class));
        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('fee_configuration: z.object({', $schema);
        $this->assertStringContainsString('platform_card_fees: z.object({', $schema);
        $this->assertStringContainsString('percentage: z.number', $schema);
        $this->assertStringContainsString('fixed_cents: z.number', $schema);
        $this->assertStringContainsString('cap_cents: z.number()', $schema);
    }
}
