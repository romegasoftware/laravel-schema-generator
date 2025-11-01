<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\InheritedPostalCodeWithLocalRulesData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class SchemaInheritanceMergeTest extends TestCase
{
    use InteractsWithExtractors;

    #[Test]
    public function it_merges_local_rules_with_inherited_rules_for_schema_generation(): void
    {
        $extractor = $this->getDataExtractor();
        $extracted = $extractor->extract(new ReflectionClass(InheritedPostalCodeWithLocalRulesData::class));

        $postalProperty = $extracted->properties->firstWhere('name', 'postal_code');

        $this->assertNotNull($postalProperty);
        $this->assertFalse($postalProperty->isOptional);
        $this->assertTrue($postalProperty->validations?->isFieldRequired());
    }
}
