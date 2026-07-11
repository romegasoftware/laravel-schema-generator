<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\DefaultedPropertiesData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\UnifiedData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class DataClassExtractionTest extends TestCase
{
    use InteractsWithExtractors;

    private ExtractedSchemaData $extracted;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extracted = $this->getDataExtractor()->extract(new ReflectionClass(UnifiedData::class));
    }

    #[Test]
    public function it_extracts_schema_metadata(): void
    {
        $this->assertEquals('UnifiedDataSchema', $this->extracted->name);
        $this->assertEquals(UnifiedData::class, $this->extracted->className);
        $this->assertEquals('data', $this->extracted->type);

        $properties = $this->extracted->properties->toCollection();
        $this->assertCount(3, $properties);
        $this->assertNotNull($properties->firstWhere('name', 'account_details'));
        $this->assertNotNull($properties->firstWhere('name', 'projects'));
        $this->assertNotNull($properties->firstWhere('name', 'notes'));
    }

    #[Test]
    public function it_applies_mapped_names_and_optional_state(): void
    {
        $properties = $this->extracted->properties->toCollection();
        $profile = $properties->firstWhere('name', 'account_details');
        $this->assertNotNull($profile);
        $this->assertFalse($profile->isOptional);

        $notes = $properties->firstWhere('name', 'notes');
        $this->assertNotNull($notes);
        $this->assertTrue($notes->isOptional);
        $this->assertTrue($notes->validations->isFieldNullable());
        $this->assertTrue($notes->validations->hasValidation('String'));
        $this->assertTrue($notes->validations->hasValidation('Max'));
    }

    #[Test]
    public function it_treats_constructor_defaults_as_optional_unless_explicitly_required(): void
    {
        $extracted = $this->getDataExtractor()->extract(new ReflectionClass(DefaultedPropertiesData::class));
        $properties = $extracted->properties->toCollection();

        $enabled = $properties->firstWhere('name', 'enabled');
        $this->assertNotNull($enabled);
        $this->assertTrue($enabled->isOptional);
        $this->assertFalse($enabled->validations->isFieldRequired());

        $confirmed = $properties->firstWhere('name', 'confirmed');
        $this->assertNotNull($confirmed);
        $this->assertFalse($confirmed->isOptional);
        $this->assertTrue($confirmed->validations->isFieldRequired());
    }

    #[Test]
    public function it_resolves_nested_objects_and_collections(): void
    {
        $properties = $this->extracted->properties->toCollection();

        $profile = $properties->firstWhere('name', 'account_details');
        $this->assertNotNull($profile);
        $profileObjects = $profile->validations->getObjectProperties();
        $this->assertArrayHasKey('address', $profileObjects);
        $this->assertArrayHasKey('preferences', $profileObjects);
        $this->assertArrayHasKey('status', $profileObjects);

        $projects = $properties->firstWhere('name', 'projects');
        $this->assertNotNull($projects);
        $this->assertEquals('array', $projects->validations->inferredType);
        $this->assertTrue($projects->validations->hasValidation('Array'));
        $this->assertTrue($projects->validations->hasNestedValidations());

        $projectNested = $projects->validations->getNestedValidations();
        $this->assertNotNull($projectNested);
        $this->assertArrayHasKey('metrics', $projectNested->getObjectProperties());
        $this->assertArrayHasKey('schedule', $projectNested->getObjectProperties());
    }

    #[Test]
    public function it_preserves_manual_rules_for_nested_metric_values(): void
    {
        $projects = $this->extracted->properties->toCollection()->firstWhere('name', 'projects');
        $this->assertNotNull($projects);

        $projectItems = $projects->validations->getNestedValidations();
        $this->assertNotNull($projectItems);

        $metricsSet = $projectItems->getObjectProperties()['metrics'] ?? null;
        $this->assertNotNull($metricsSet);

        $metricItems = $metricsSet->getNestedValidations();
        $this->assertNotNull($metricItems);

        $valueSet = $metricItems->getObjectProperties()['value'] ?? null;
        $this->assertNotNull($valueSet);

        $minValidation = $valueSet->getValidation('Min');
        $this->assertNotNull($minValidation);
        $this->assertEquals([0], $minValidation->parameters);
    }

    #[Test]
    public function it_preserves_schedule_comparison_rules(): void
    {
        $projects = $this->extracted->properties->toCollection()->firstWhere('name', 'projects');
        $this->assertNotNull($projects);

        $projectItems = $projects->validations->getNestedValidations();
        $this->assertNotNull($projectItems);

        $scheduleSet = $projectItems->getObjectProperties()['schedule'] ?? null;
        $this->assertNotNull($scheduleSet);

        $scheduleProps = $scheduleSet->getObjectProperties();
        $this->assertArrayHasKey('ends_at', $scheduleProps);

        $endsAt = $scheduleProps['ends_at'];
        $afterOrEqual = $endsAt->getValidation('AfterOrEqual');
        $this->assertNotNull($afterOrEqual);
        $this->assertEquals(['schedule.starts_at'], $afterOrEqual->parameters);
    }
}
