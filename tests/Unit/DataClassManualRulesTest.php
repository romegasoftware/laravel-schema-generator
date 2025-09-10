<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\DataClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\AlbumData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\SongData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

/**
 * @deprecated Use Feature/DataClassManualRulesExtractionTest instead
 * This file is kept for backwards compatibility during development
 */
class DataClassManualRulesTest extends TestCase
{
    private DataClassExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = $this->app->make(DataClassExtractor::class);
    }

    #[Test]
    public function it_extracts_manual_rules_from_song_data(): void
    {
        $reflection = new \ReflectionClass(SongData::class);

        $result = $this->extractor->extract($reflection);

        // According to Spatie docs, we should have:
        // - title: required
        // - artist: required

        $titleProperty = $result->properties->toCollection()->firstWhere('name', 'title');
        $this->assertNotNull($titleProperty, 'Title property should exist');

        $artistProperty = $result->properties->toCollection()->firstWhere('name', 'artist');
        $this->assertNotNull($artistProperty, 'Artist property should exist');
    }

    #[Test]
    public function it_extracts_context_based_rules_from_album_data(): void
    {
        $reflection = new \ReflectionClass(AlbumData::class);

        $result = $this->extractor->extract($reflection);

        // According to Spatie docs, we should have:
        // - title: required|string
        // - songs: required|array|min:1|max:10
        // - songs.*.title: required|string
        // - songs.*.artist: required|string

        $titleProperty = $result->properties->toCollection()->firstWhere('name', 'title');
        $this->assertNotNull($titleProperty, 'Title property should exist');
        $this->assertTrue($titleProperty->validations->hasValidation('Required'));

        $songsProperty = $result->properties->toCollection()->firstWhere('name', 'songs');
        $this->assertNotNull($songsProperty, 'Songs property should exist');
        $this->assertEquals('array', $songsProperty->validations->inferredType);

        // Check array validations
        $this->assertTrue($songsProperty->validations->hasValidation('Required'));
        $this->assertTrue($songsProperty->validations->hasValidation('Array'));
        $this->assertTrue($songsProperty->validations->hasValidation('Min'));
        $this->assertTrue($songsProperty->validations->hasValidation('Max'));

        // Check for nested song validations
        $this->assertNotNull($songsProperty->validations->nestedValidations, 'Songs should have nested validations');

        // Check that nested validations have the title and artist properties
        $nestedObjectProperties = $songsProperty->validations->nestedValidations->objectProperties ?? null;
        $this->assertNotNull($nestedObjectProperties, 'Nested songs should have object properties');
        $this->assertArrayHasKey('title', $nestedObjectProperties, 'Song should have title property');
        $this->assertArrayHasKey('artist', $nestedObjectProperties, 'Song should have artist property');

        // Verify the nested title validation
        $songTitleValidation = $nestedObjectProperties['title'];
        $this->assertTrue($songTitleValidation->hasValidation('Required'));
        $this->assertTrue($songTitleValidation->hasValidation('String'));

        // Verify the nested artist validation
        $songArtistValidation = $nestedObjectProperties['artist'];
        $this->assertTrue($songArtistValidation->hasValidation('Required'));
        $this->assertTrue($songArtistValidation->hasValidation('String'));
    }
}
