<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\AlbumData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class NestedObjectsInArraysTest extends TestCase
{
    use InteractsWithExtractors;

    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_handles_nested_objects_within_array_items(): void
    {
        $extracted = $this->getDataExtractor()->extract(new \ReflectionClass(AlbumData::class));
        $schema = $this->generator->generate($extracted);

        // The album should have an array of songs
        $this->assertStringContainsString('songs: z.array(z.object({', $schema);

        // Within each song object, song_meta_data_custom_name should be an object, not an array
        $this->assertStringContainsString('song_meta_data_custom_name: z.object({', $schema);

        // The nested object properties should be inside the object, not as separate fields
        $this->assertStringContainsString('lengthInSeconds: z.number({error: (val) => (val != undefined && val != null ? \'The songs.*.song_meta_data_custom_name.*.lengthInSeconds field is required.\' : undefined)})', $schema);
        $this->assertStringContainsString('fileFormat: z.enum(["MP3", "WAV"],', $schema);

        // These should NOT exist as separate fields with dot notation
        $this->assertStringNotContainsString('song_meta_data_custom_name.lengthInSeconds:', $schema);
        $this->assertStringNotContainsString('song_meta_data_custom_name.fileFormat:', $schema);

        // The song_meta_data_custom_name should NOT be treated as an array
        $this->assertStringNotContainsString('song_meta_data_custom_name: z.array(', $schema);

        // Producers should still be an array within each song
        $this->assertStringContainsString('producers: z.array(z.object({', $schema);
    }

    #[Test]
    public function it_generates_correct_structure_for_album_data(): void
    {
        $extracted = $this->getDataExtractor()->extract(new \ReflectionClass(AlbumData::class));
        $schema = $this->generator->generate($extracted);

        // Expected structure (simplified for readability):
        // z.object({
        //   album_title: z.string(),
        //   songs: z.array(z.object({
        //     title: z.string(),
        //     artist: z.string().nullable(),
        //     song_meta_data_custom_name: z.object({
        //       lengthInSeconds: z.number(),
        //       fileFormat: z.enum(["MP3", "WAV"])
        //     }),
        //     producers: z.array(z.object({
        //       email: z.email(),
        //       name: z.string()
        //     }))
        //   }))
        // })

        // Check the overall structure
        $this->assertStringContainsString('z.object({', $schema);
        $this->assertStringContainsString('album_title: z.string()', $schema);

        // Verify no flattened properties at the root level
        $this->assertStringNotContainsString('songs.*.song_meta_data_custom_name.lengthInSeconds', $schema);
    }

    #[Test]
    public function it_maintains_validation_messages_for_deeply_nested_properties(): void
    {
        $extracted = $this->getDataExtractor()->extract(new \ReflectionClass(AlbumData::class));
        $schema = $this->generator->generate($extracted);

        // Check that custom messages are preserved for nested properties
        $this->assertStringContainsString('Song length must be greater than 10 seconds', $schema);
        $this->assertStringContainsString('Song length must be less than 5 minutes', $schema);
    }
}
