<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\SongData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class NestedObjectValidationTest extends TestCase
{
    use InteractsWithExtractors;

    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_generates_nested_object_validation_correctly(): void
    {
        $extracted = $this->getDataExtractor()->extract(new \ReflectionClass(SongData::class));
        $schema = $this->generator->generate($extracted);

        // The song_meta_data_custom_name should be an object, not individual properties
        $this->assertStringContainsString('song_meta_data_custom_name: z.object({', $schema);

        // The nested properties should be inside the object
        $this->assertStringContainsString('lengthInSeconds: z.number({ error: \'The song meta data custom name.length in seconds field is required.\' })', $schema);
        $this->assertStringContainsString('fileFormat: z.enum(["MP3", "WAV"],', $schema);

        // These should NOT exist as separate top-level properties
        $this->assertStringNotContainsString('song_meta_data_custom_name.lengthInSeconds:', $schema);
        $this->assertStringNotContainsString('song_meta_data_custom_name.fileFormat:', $schema);

        // The producers array should contain proper TestUserData objects
        $this->assertStringContainsString('producers: z.array(z.object({', $schema);
        $this->assertStringContainsString('email: z.email(', $schema);
        $this->assertStringContainsString('name: z.string(', $schema);
    }

    #[Test]
    public function it_handles_nested_object_with_custom_messages(): void
    {
        $extracted = $this->getDataExtractor()->extract(new \ReflectionClass(SongData::class));
        $schema = $this->generator->generate($extracted);

        // Check that custom messages are properly applied to nested object properties
        $this->assertStringContainsString('Song length must be greater than 10 seconds', $schema);
        $this->assertStringContainsString('Song length must be less than 5 minutes', $schema);
    }

    #[Test]
    public function it_maintains_proper_structure_for_mapped_names(): void
    {
        $extracted = $this->getDataExtractor()->extract(new \ReflectionClass(SongData::class));
        $schema = $this->generator->generate($extracted);

        // The mapped name should be used as the property key
        $this->assertStringContainsString('song_meta_data_custom_name:', $schema);

        // But the original property name should not appear
        $this->assertStringNotContainsString('metaData:', $schema);
    }
}
