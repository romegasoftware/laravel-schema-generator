<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\ExtractorInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorManager;
use RomegaSoftware\LaravelSchemaGenerator\Support\PackageDetector;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use Spatie\LaravelData\DataCollection;

class CustomExtractorTest extends TestCase
{
    #[Test]
    public function it_can_register_custom_extractors_from_config(): void
    {
        // Create a mock custom extractor
        $customExtractor = new class implements ExtractorInterface
        {
            public function canHandle(ReflectionClass $class): bool
            {
                return $class->getName() === 'TestCustomClass';
            }

            public function extract(ReflectionClass $class): ExtractedSchemaData
            {
                $property = new SchemaPropertyData(
                    name: 'custom_field',
                    type: 'string',
                    isOptional: false,
                    validations: ResolvedValidationSet::make('custom_field', [
                        new ResolvedValidation('required', [], null, true, false),
                    ], 'string'),
                );

                return new ExtractedSchemaData(
                    name: 'TestCustomSchema',
                    properties: SchemaPropertyData::collect([$property], DataCollection::class),
                    className: $class->getName(),
                    type: 'custom',
                );
            }

            public function getPriority(): int
            {
                return 200;
            }
        };

        // Mock the app container to return our custom extractor
        $this->app->bind('CustomExtractor', function () use ($customExtractor) {
            return $customExtractor;
        });

        // Set config with custom extractor
        config(['laravel-schema-generator.custom_extractors' => ['CustomExtractor']]);

        // Create ExtractorManager which should register our custom extractor
        $manager = new ExtractorManager($this->app->make(PackageDetector::class));

        // Verify the custom extractor was registered
        $extractors = $manager->getExtractors();
        $hasCustomExtractor = false;

        foreach ($extractors as $extractor) {
            if (get_class($extractor) === get_class($customExtractor)) {
                $hasCustomExtractor = true;
                break;
            }
        }

        $this->assertTrue($hasCustomExtractor, 'Custom extractor should be registered');
    }

    #[Test]
    public function it_can_use_custom_extractor_to_extract_data(): void
    {
        // Create a mock custom extractor for a specific class pattern
        $customExtractor = new class implements ExtractorInterface
        {
            public function canHandle(ReflectionClass $class): bool
            {
                return str_ends_with($class->getName(), 'CustomValidation');
            }

            public function extract(ReflectionClass $class): ExtractedSchemaData
            {
                $property = new SchemaPropertyData(
                    name: 'api_key',
                    type: 'string',
                    isOptional: false,
                    validations: ResolvedValidationSet::make('api_key', [
                        new ResolvedValidation('required', [], null, true, false),
                        new ResolvedValidation('min', [32], null, false, false),
                        new ResolvedValidation('max', [64], null, false, false),
                    ], 'string'),
                );

                return new ExtractedSchemaData(
                    name: 'CustomValidationSchema',
                    properties: SchemaPropertyData::collect([
                        [
                            'name' => 'api_key',
                            'type' => 'string',
                            'isOptional' => false,
                            'validations' => ResolvedValidationSet::make('api_key', [
                                new ResolvedValidation('required', [], null, true, false),
                                new ResolvedValidation('min', [32], null, false, false),
                                new ResolvedValidation('max', [64], null, false, false),
                            ], 'string'),
                        ],
                    ], DataCollection::class),
                    className: $class->getName(),
                    type: 'custom',
                );
            }

            public function getPriority(): int
            {
                return 300; // Higher priority than default extractors
            }
        };

        // Mock the app container
        $this->app->bind('CustomApiExtractor', function () use ($customExtractor) {
            return $customExtractor;
        });

        // Set config
        config(['laravel-schema-generator.custom_extractors' => ['CustomApiExtractor']]);

        // Create a mock class that ends with CustomValidation
        $mockClass = new ReflectionClass(new class {});

        // Use a spy/mock to simulate the class name
        $mockClass = $this->createMock(ReflectionClass::class);
        $mockClass->method('getName')->willReturn('App\\Validators\\ApiKeyCustomValidation');

        $manager = new ExtractorManager($this->app->make(PackageDetector::class));

        // Find extractor for our mock class
        $extractor = $manager->findExtractor($mockClass);

        $this->assertNotNull($extractor);
        $this->assertInstanceOf(get_class($customExtractor), $extractor);

        // Extract data using the custom extractor
        $result = $extractor->extract($mockClass);

        $this->assertEquals('CustomValidationSchema', $result->name);
        $this->assertCount(1, $result->properties);
        $this->assertEquals('api_key', $result->properties[0]->name);
        $this->assertEquals('string', $result->properties[0]->type);
        $this->assertTrue($result->properties[0]->validations->isFieldRequired());
        $this->assertEquals(32, $result->properties[0]->validations->getValidationParameter('min'));
        $this->assertEquals(64, $result->properties[0]->validations->getValidationParameter('max'));
    }

    #[Test]
    public function it_throws_exception_for_non_existent_custom_extractor_class(): void
    {
        config(['laravel-schema-generator.custom_extractors' => ['NonExistentExtractor']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom extractor class NonExistentExtractor does not exist.');

        new ExtractorManager($this->app->make(PackageDetector::class));
    }

    #[Test]
    public function it_throws_exception_for_invalid_custom_extractor_interface(): void
    {
        // Create a class that doesn't implement ExtractorInterface
        $invalidExtractor = new class
        {
            public function someMethod() {}
        };

        $this->app->bind('InvalidExtractor', function () use ($invalidExtractor) {
            return $invalidExtractor;
        });

        config(['laravel-schema-generator.custom_extractors' => ['InvalidExtractor']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom extractor InvalidExtractor must implement ExtractorInterface.');

        new ExtractorManager($this->app->make(PackageDetector::class));
    }

    #[Test]
    public function custom_extractors_are_sorted_by_priority(): void
    {
        $lowPriorityExtractor = new class implements ExtractorInterface
        {
            public function canHandle(ReflectionClass $class): bool
            {
                return false;
            }

            public function extract(ReflectionClass $class): ExtractedSchemaData
            {
                return new ExtractedSchemaData(
                    name: 'EmptySchema',
                    properties: SchemaPropertyData::collect([], DataCollection::class),
                    className: $class->getName(),
                    type: 'custom',
                );
            }

            public function getPriority(): int
            {
                return 100;
            }
        };

        $highPriorityExtractor = new class implements ExtractorInterface
        {
            public function canHandle(ReflectionClass $class): bool
            {
                return false;
            }

            public function extract(ReflectionClass $class): ExtractedSchemaData
            {
                return new ExtractedSchemaData(
                    name: 'EmptySchema',
                    properties: SchemaPropertyData::collect([], DataCollection::class),
                    className: $class->getName(),
                    type: 'custom',
                );
            }

            public function getPriority(): int
            {
                return 500;
            }
        };

        $this->app->bind('LowPriorityExtractor', function () use ($lowPriorityExtractor) {
            return $lowPriorityExtractor;
        });

        $this->app->bind('HighPriorityExtractor', function () use ($highPriorityExtractor) {
            return $highPriorityExtractor;
        });

        config(['laravel-schema-generator.custom_extractors' => [
            'LowPriorityExtractor',
            'HighPriorityExtractor',
        ]]);

        $manager = new ExtractorManager($this->app->make(PackageDetector::class));
        $extractors = $manager->getExtractors();

        // Find our custom extractors in the list
        $customExtractors = [];
        foreach ($extractors as $extractor) {
            if (get_class($extractor) === get_class($lowPriorityExtractor) ||
                get_class($extractor) === get_class($highPriorityExtractor)) {
                $customExtractors[] = $extractor;
            }
        }

        // Should have both extractors
        $this->assertCount(2, $customExtractors);

        // High priority should come before low priority
        $priorities = array_map(fn ($e) => $e->getPriority(), $customExtractors);
        $this->assertEquals([500, 100], $priorities);
    }
}
