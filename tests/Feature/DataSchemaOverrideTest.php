<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\OrderCreateRequestData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\OrderCreateRequestReplacementData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class DataSchemaOverrideTest extends TestCase
{
    use InteractsWithExtractors;

    #[Test]
    public function it_applies_literal_override_on_inherited_data_collections(): void
    {
        $extracted = $this->getDataExtractor()->extract(new ReflectionClass(OrderCreateRequestData::class));

        /** @var ValidationSchemaGenerator $generator */
        $generator = $this->app->make(ValidationSchemaGenerator::class);

        $itemsProperty = $extracted->properties?->firstWhere('name', 'items');
        $this->assertNotNull($itemsProperty, 'items property should exist');
        $this->assertNotNull($itemsProperty->schemaOverride, 'schema override should propagate through inheritance');

        $schema = $generator->generate($extracted);

        $this->assertStringContainsString('items: z.array(OrderItemRequestDataSchema)', $schema);
        $this->assertStringContainsString('.superRefine((items, ctx) => {', $schema);
        $this->assertStringContainsString('You must order at least 12 total units.', $schema);
        $this->assertStringNotContainsString('items.*.item_id', $schema);
        $this->assertStringNotContainsString('items.*.quantity', $schema);
    }

    #[Test]
    public function it_replaces_base_builder_when_literal_has_no_prefix(): void
    {
        $extracted = $this->getDataExtractor()->extract(new ReflectionClass(OrderCreateRequestReplacementData::class));

        /** @var ValidationSchemaGenerator $generator */
        $generator = $this->app->make(ValidationSchemaGenerator::class);

        $schema = $generator->generate($extracted);

        $this->assertStringContainsString(
            'items: z.array(z.object({ quantity: z.number() })).superRefine((items, ctx) => {',
            $schema
        );
        $this->assertStringContainsString('You must order at least 12 total units.', $schema);
        $this->assertStringNotContainsString('OrderItemRequestDataSchema', $schema);
    }
}
