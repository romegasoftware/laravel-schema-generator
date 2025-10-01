<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\ExtractorInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyCollection;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorManager;
use RomegaSoftware\LaravelSchemaGenerator\Support\PackageDetector;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class ExtractorManagerTest extends TestCase
{
    protected ExtractorManager $manager;

    protected PackageDetector $packageDetector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->packageDetector = new PackageDetector;
        $this->manager = new ExtractorManager($this->packageDetector);
    }

    #[Test]
    public function it_registers_default_extractors(): void
    {
        $extractors = $this->manager->getExtractors();

        $this->assertNotEmpty($extractors);

        // Should always have RequestClassExtractor
        $hasRequestExtractor = false;
        foreach ($extractors as $extractor) {
            if (get_class($extractor) === 'RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor') {
                $hasRequestExtractor = true;
                break;
            }
        }
        $this->assertTrue($hasRequestExtractor);
    }

    #[Test]
    public function it_can_register_custom_extractor(): void
    {
        $customExtractor = new class implements ExtractorInterface
        {
            public function canHandle(ReflectionClass $class): bool
            {
                return true;
            }

            public function extract(ReflectionClass $class): ExtractedSchemaData
            {
                return new ExtractedSchemaData(
                    name: 'TestSchema',
                    properties: SchemaPropertyCollection::make([]),
                    className: $class->getName(),
                    type: 'test',
                );
            }

            public function getPriority(): int
            {
                return 5;
            }
        };

        $this->manager->register($customExtractor);
        $extractors = $this->manager->getExtractors();

        $this->assertContains($customExtractor, $extractors);
    }

    #[Test]
    public function it_sorts_extractors_by_priority(): void
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
                    name: 'LowPriority',
                    properties: SchemaPropertyCollection::make([]),
                    className: $class->getName(),
                    type: 'test',
                );
            }

            public function getPriority(): int
            {
                return 1;
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
                    name: 'HighPriority',
                    properties: SchemaPropertyCollection::make([]),
                    className: $class->getName(),
                    type: 'test',
                );
            }

            public function getPriority(): int
            {
                return 100;
            }
        };

        $this->manager->register($lowPriorityExtractor);
        $this->manager->register($highPriorityExtractor);

        $extractors = $this->manager->getExtractors();

        // High priority should come first
        $this->assertEquals(100, $extractors[0]->getPriority());
    }

    #[Test]
    public function it_finds_appropriate_extractor(): void
    {
        $mockExtractor = new class implements ExtractorInterface
        {
            public function canHandle(ReflectionClass $class): bool
            {
                return $class->getName() === 'TestClass';
            }

            public function extract(ReflectionClass $class): ExtractedSchemaData
            {
                return new ExtractedSchemaData(
                    name: 'TestSchema',
                    properties: SchemaPropertyCollection::make([]),
                    className: $class->getName(),
                    type: 'test',
                );
            }

            public function getPriority(): int
            {
                return 50;
            }
        };

        $this->manager->register($mockExtractor);

        $testClass = new class {};
        $reflection = new ReflectionClass($testClass);

        $foundExtractor = $this->manager->findExtractor($reflection);
        $this->assertNull($foundExtractor); // Should not find our mock extractor

        // Create a class that matches our mock condition
        eval('class TestClass {}');
        $testReflection = new ReflectionClass('TestClass');

        $foundExtractor = $this->manager->findExtractor($testReflection);
        $this->assertSame($mockExtractor, $foundExtractor);
    }

    #[Test]
    public function it_throws_exception_when_no_extractor_found(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No extractor found for class');

        $testClass = new class {};
        $reflection = new ReflectionClass($testClass);

        // Clear all extractors
        $manager = new class($this->packageDetector) extends ExtractorManager
        {
            public function __construct($packageDetector)
            {
                $this->packageDetector = $packageDetector;
                // Don't call parent constructor to avoid registering default extractors
            }

            public function registerDefaultExtractors(): void
            {
                // Empty - no default extractors
            }
        };

        $manager->extract($reflection);
    }

    #[Test]
    public function it_extracts_schema_information(): void
    {
        $mockExtractor = new class implements ExtractorInterface
        {
            public function canHandle(ReflectionClass $class): bool
            {
                return true;
            }

            public function extract(ReflectionClass $class): ExtractedSchemaData
            {
                return new ExtractedSchemaData(
                    name: 'TestSchema',
                    properties: SchemaPropertyCollection::make([
                        new SchemaPropertyData(
                            name: 'email',
                            validator: null,
                            isOptional: false,
                            validations: ResolvedValidationSet::make('email', [
                                new ResolvedValidation('required', [], null, true, false),
                                new ResolvedValidation('email', [], null, false, false),
                            ], 'string'),
                        ),
                    ]),
                    className: $class->getName(),
                    type: 'test',
                );
            }

            public function getPriority(): int
            {
                return 100; // Higher than default extractors
            }
        };

        $this->manager->register($mockExtractor);

        $testClass = new class {};
        $reflection = new ReflectionClass($testClass);

        $result = $this->manager->extract($reflection);

        $this->assertEquals('TestSchema', $result->name);
        $this->assertCount(1, $result->properties);
        $this->assertEquals('email', $result->properties[0]->name);
    }
}
