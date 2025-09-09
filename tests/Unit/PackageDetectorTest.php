<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Support\PackageDetector;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class PackageDetectorTest extends TestCase
{
    protected PackageDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new PackageDetector;
    }

    #[Test]
    public function it_detects_available_features(): void
    {
        $features = $this->detector->getAvailableFeatures();

        $this->assertIsArray($features);
        $this->assertArrayHasKey('data_classes', $features);
        $this->assertArrayHasKey('typescript_transformer', $features);
        $this->assertArrayHasKey('laravel_typescript_transformer', $features);
        $this->assertIsBool($features['data_classes']);
        $this->assertIsBool($features['typescript_transformer']);
        $this->assertIsBool($features['laravel_typescript_transformer']);
    }

    #[Test]
    public function it_checks_spatie_data_availability(): void
    {
        $hasData = $this->detector->hasSpatieData();
        $this->assertIsBool($hasData);
    }

    #[Test]
    public function it_checks_typescript_transformer_availability(): void
    {
        $hasTransformer = $this->detector->hasTypeScriptTransformer();
        $this->assertIsBool($hasTransformer);
    }

    #[Test]
    public function it_checks_laravel_typescript_transformer_availability(): void
    {
        $hasLaravelTransformer = $this->detector->hasLaravelTypeScriptTransformer();
        $this->assertIsBool($hasLaravelTransformer);
    }

    #[Test]
    public function it_determines_feature_enabled_status(): void
    {
        // Test data classes feature (depends on spatie/laravel-data)
        $isDataEnabled = $this->detector->isFeatureEnabled('data_classes');
        $this->assertIsBool($isDataEnabled);
    }
}
